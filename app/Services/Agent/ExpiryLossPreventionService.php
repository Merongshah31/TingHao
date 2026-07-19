<?php

namespace App\Services\Agent;

use App\Models\AgentRun;
use App\Models\AgentToolCall;
use App\Models\ExpiryLossRecommendation;
use App\Models\Ingredient;
use App\Models\User;
use App\Services\Qwen\QwenClient;
use Illuminate\Database\Eloquent\Collection;

class ExpiryLossPreventionService
{
    public function __construct(
        private readonly QwenClient $qwenClient,
        private readonly ReasoningActivityService $reasoningActivity,
    ) {
    }

    /**
     * @return array{agent_run: AgentRun, recommendations: Collection<int, ExpiryLossRecommendation>, scanned_count: int, total_potential_loss: float, qwen_mocked: bool}
     */
    public function scan(User $user): array
    {
        $agentRun = AgentRun::create([
            'user_id' => $user->id,
            'input_text' => 'Scan ingredients expiring within 7 days and calculate potential RM loss.',
            'input_type' => 'expiry_loss_scan',
            'status' => AgentRun::STATUS_COMPLETED,
            'qwen_mocked' => filter_var(config('qwen.mock_mode', true), FILTER_VALIDATE_BOOLEAN) || ! $this->qwenClient->isConfigured(),
        ]);
        $this->reasoningActivity->observe($agentRun, 'Expiry scan requested', 'Admin requested a 7-day expiry loss scan.', [
            'window_days' => 7,
            'stock_required' => true,
        ]);

        $ingredients = $this->expiringIngredients();
        $scanToolCall = $this->logToolCall($agentRun, 'scan_expiring_ingredients', [
            'window_days' => 7,
            'exclude_expired' => true,
            'exclude_zero_quantity' => true,
        ], [
            'matched_count' => $ingredients->count(),
            'ingredient_ids' => $ingredients->pluck('id')->all(),
        ]);
        $this->reasoningActivity->toolAction($agentRun, 'Scan expiring inventory', 'Checking real ingredient records for non-expired stock expiring within 7 days.', $scanToolCall);
        $this->reasoningActivity->toolResult($agentRun, 'Expiring inventory result', 'Found '.$ingredients->count().' ingredient(s) at risk in the 7-day window.', $scanToolCall);

        $recommendations = new Collection();
        $qwenMocked = false;
        $calculations = [];

        foreach ($ingredients as $ingredient) {
            $calculation = $this->calculateLoss($ingredient);
            $calculations[$ingredient->id] = $calculation;
            $calculateToolCall = $this->logToolCall($agentRun, 'calculate_expiry_loss', [
                'ingredient_id' => $ingredient->id,
                'quantity' => (float) $ingredient->quantity,
                'cost_price' => $ingredient->cost_price !== null ? (float) $ingredient->cost_price : null,
            ], $calculation);
            $this->reasoningActivity->toolResult($agentRun, 'Potential RM loss calculated', $ingredient->name.' has potential loss '.($calculation['potential_loss'] !== null ? 'RM '.number_format((float) $calculation['potential_loss'], 2) : 'unavailable because cost price is missing').'.', $calculateToolCall);
        }

        $qwenResults = $this->generateRecommendations($ingredients, $calculations);

        foreach ($ingredients as $ingredient) {
            $calculation = $calculations[$ingredient->id];
            $qwenResult = $qwenResults[$ingredient->id] ?? [
                ...$this->fallbackRecommendation($ingredient, $calculation),
                'mocked' => true,
                'error' => 'Recommendation result missing from batch response.',
                'qwen_metadata' => [],
            ];
            $qwenMocked = $qwenMocked || $qwenResult['mocked'];

            $recommendToolCall = $this->logToolCall($agentRun, 'generate_expiry_recommendation', [
                'ingredient_id' => $ingredient->id,
                'ingredient_name' => $ingredient->name,
                'days_until_expiry' => $calculation['days_until_expiry'],
                'potential_loss' => $calculation['potential_loss'],
            ], [
                'recommendation_title' => $qwenResult['recommendation_title'],
                'qwen_mocked' => $qwenResult['mocked'],
                'qwen_error' => $qwenResult['error'],
                'qwen_metadata' => $qwenResult['qwen_metadata'] ?? [],
            ]);
            $this->reasoningActivity->decision($agentRun, 'Expiry action recommended', $qwenResult['recommendation_title'].': '.$qwenResult['recommendation_body'], [
                'ingredient_id' => $ingredient->id,
                'potential_loss' => $calculation['potential_loss'],
            ]);
            $this->reasoningActivity->toolResult($agentRun, 'Expiry recommendation generated', 'Recommendation text was generated without using expired stock or invented sales data.', $recommendToolCall);

            $recommendation = $this->saveRecommendation($agentRun, $ingredient, $calculation, $qwenResult);
            $recommendations->push($recommendation);

            $saveToolCall = $this->logToolCall($agentRun, 'save_expiry_recommendation', [
                'ingredient_id' => $ingredient->id,
                'expiry_date' => $ingredient->expiry_date?->toDateString(),
            ], [
                'recommendation_id' => $recommendation->id,
                'status' => $recommendation->status,
                'deduped_active_record' => ! $recommendation->wasRecentlyCreated,
            ]);
            $this->reasoningActivity->humanCheckpoint($agentRun, 'Admin follow-up required', 'Admin must review, dismiss, or complete this expiry recommendation before it is considered handled.', [
                'expiry_loss_recommendation_id' => $recommendation->id,
                'status' => $recommendation->status,
            ]);
            $this->reasoningActivity->toolResult($agentRun, 'Expiry recommendation saved', 'The recommendation was saved for human review.', $saveToolCall);
        }

        $totalPotentialLoss = (float) $recommendations
            ->sum(fn (ExpiryLossRecommendation $recommendation): float => (float) ($recommendation->potential_loss ?? 0));

        $summary = $recommendations->isEmpty()
            ? 'No ingredients are expiring within 7 days with stock on hand.'
            : 'Identified RM '.number_format($totalPotentialLoss, 2).' potential expiry loss from '.$recommendations->count().' ingredient(s) expiring within 7 days.';

        $agentRun->update([
            'parsed_intent' => [
                'intent' => 'expiry_loss_prevention',
                'window_days' => 7,
                'matched_ingredients' => $recommendations
                    ->map(fn (ExpiryLossRecommendation $recommendation): array => [
                        'recommendation_id' => $recommendation->id,
                        'ingredient_id' => $recommendation->ingredient_id,
                        'ingredient_name' => $recommendation->ingredient?->name,
                        'quantity_at_risk' => (float) $recommendation->quantity_at_risk,
                        'unit' => $recommendation->unit,
                        'potential_loss' => $recommendation->potential_loss !== null ? (float) $recommendation->potential_loss : null,
                        'expiry_date' => $recommendation->expiry_date?->toDateString(),
                        'days_until_expiry' => $recommendation->days_until_expiry,
                    ])
                    ->values()
                    ->all(),
                'total_potential_loss' => $totalPotentialLoss,
            ],
            'final_summary' => $summary,
            'qwen_mocked' => $qwenMocked || (bool) $agentRun->qwen_mocked,
        ]);
        $this->reasoningActivity->finalSummary($agentRun, 'Expiry loss scan summary', $summary);

        return [
            'agent_run' => $agentRun->load(['toolCalls', 'reasoningSteps', 'expiryLossRecommendations.ingredient']),
            'recommendations' => $recommendations->load(['ingredient', 'agentRun']),
            'scanned_count' => $ingredients->count(),
            'total_potential_loss' => $totalPotentialLoss,
            'qwen_mocked' => $qwenMocked || (bool) $agentRun->qwen_mocked,
        ];
    }

    /**
     * @return Collection<int, Ingredient>
     */
    private function expiringIngredients(): Collection
    {
        return Ingredient::query()
            ->select(['id', 'name', 'sku', 'unit', 'quantity', 'cost_price', 'expiry_date'])
            ->where('quantity', '>', 0)
            ->expiringWithin(7)
            ->orderBy('expiry_date')
            ->orderByDesc('quantity')
            ->get();
    }

    /**
     * @return array{quantity_at_risk: float, unit: string|null, cost_price: float|null, potential_loss: float|null, expiry_date: string|null, days_until_expiry: int|null, loss_note: string}
     */
    private function calculateLoss(Ingredient $ingredient): array
    {
        $costPrice = $ingredient->cost_price !== null ? (float) $ingredient->cost_price : null;
        $quantity = (float) $ingredient->quantity;
        $potentialLoss = $costPrice !== null ? round($quantity * $costPrice, 2) : null;

        return [
            'quantity_at_risk' => $quantity,
            'unit' => $ingredient->unit,
            'cost_price' => $costPrice,
            'potential_loss' => $potentialLoss,
            'expiry_date' => $ingredient->expiry_date?->toDateString(),
            'days_until_expiry' => $ingredient->expiry_date
                ? (int) now()->startOfDay()->diffInDays($ingredient->expiry_date->copy()->startOfDay(), false)
                : null,
            'loss_note' => $potentialLoss === null
                ? 'Cost price is unavailable, so RM impact cannot be calculated yet.'
                : 'Potential loss calculated as quantity at risk multiplied by cost price.',
        ];
    }

    /**
     * @param  array<string, mixed>  $calculation
     * @return array{recommendation_title: string, recommendation_body: string, mocked: bool, error: string|null}
     */
    private function generateRecommendations(Collection $ingredients, array $calculations): array
    {
        if ($ingredients->isEmpty()) {
            return [];
        }

        $payload = [
            'ingredients' => $ingredients
                ->map(fn (Ingredient $ingredient): array => [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'sku' => $ingredient->sku,
                    'quantity' => $calculations[$ingredient->id]['quantity_at_risk'],
                    'unit' => $calculations[$ingredient->id]['unit'],
                    'cost_price' => $calculations[$ingredient->id]['cost_price'],
                    'potential_loss' => $calculations[$ingredient->id]['potential_loss'],
                    'expiry_date' => $calculations[$ingredient->id]['expiry_date'],
                    'days_until_expiry' => $calculations[$ingredient->id]['days_until_expiry'],
                ])
                ->values()
                ->all(),
        ];

        $response = $this->qwenClient->generateJson($this->systemPrompt(), json_encode($payload), [
            'max_tokens' => (int) config('qwen.max_tokens.expiry', 350),
            'temperature' => (float) config('qwen.temperature', 0.2),
        ]);

        $fallbacks = $ingredients
            ->mapWithKeys(fn (Ingredient $ingredient): array => [
                $ingredient->id => [
                    ...$this->fallbackRecommendation($ingredient, $calculations[$ingredient->id]),
                    'mocked' => $response['mocked'],
                    'error' => $response['error'],
                    'qwen_metadata' => $response['metadata'] ?? [],
                ],
            ])
            ->all();

        if ($response['mocked'] || $response['json'] === []) {
            return $fallbacks;
        }

        $items = $response['json']['recommendations'] ?? [];
        if (! is_array($items)) {
            return $fallbacks;
        }

        foreach ($items as $item) {
            if (! is_array($item) || ! is_numeric($item['ingredient_id'] ?? null)) {
                continue;
            }

            $ingredientId = (int) $item['ingredient_id'];
            if (! isset($fallbacks[$ingredientId])) {
                continue;
            }

            $fallbacks[$ingredientId] = [
                'recommendation_title' => filled($item['recommendation_title'] ?? null)
                    ? trim((string) $item['recommendation_title'])
                    : $fallbacks[$ingredientId]['recommendation_title'],
                'recommendation_body' => filled($item['recommendation_body'] ?? null)
                    ? trim((string) $item['recommendation_body'])
                    : $fallbacks[$ingredientId]['recommendation_body'],
                'mocked' => false,
                'error' => $response['error'],
                'qwen_metadata' => $response['metadata'] ?? [],
            ];
        }

        return $fallbacks;
    }

    /**
     * @param  array<string, mixed>  $calculation
     */
    private function saveRecommendation(AgentRun $agentRun, Ingredient $ingredient, array $calculation, array $qwenResult): ExpiryLossRecommendation
    {
        return ExpiryLossRecommendation::query()->updateOrCreate(
            [
                'ingredient_id' => $ingredient->id,
                'expiry_date' => $ingredient->expiry_date?->toDateString(),
                'status' => ExpiryLossRecommendation::STATUS_ACTIVE,
            ],
            [
                'agent_run_id' => $agentRun->id,
                'quantity_at_risk' => $calculation['quantity_at_risk'],
                'unit' => $calculation['unit'],
                'cost_price' => $calculation['cost_price'],
                'potential_loss' => $calculation['potential_loss'],
                'days_until_expiry' => $calculation['days_until_expiry'],
                'recommendation_title' => $qwenResult['recommendation_title'],
                'recommendation_body' => $qwenResult['recommendation_body'],
            ]
        )->load(['ingredient', 'agentRun']);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are TingHao Agent helping a bakery reduce ingredient expiry loss.
Use only the provided ingredient data.
Do not invent sales numbers, customer demand, recipes, or POS results.
Do not recommend using expired ingredients. The provided ingredient is not expired.
Mention urgency based on days until expiry.
Suggest practical bakery actions such as daily production priority, promotion, staff alert, reducing next reorder quantity, or bundle/promo item.
Keep the recommendation short and business-friendly.
Return compact JSON only:
{"recommendations":[{"ingredient_id":1,"recommendation_title":"...","recommendation_body":"..."}]}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $calculation
     * @return array{recommendation_title: string, recommendation_body: string}
     */
    private function fallbackRecommendation(Ingredient $ingredient, array $calculation): array
    {
        $days = (int) ($calculation['days_until_expiry'] ?? 0);
        $lossText = $calculation['potential_loss'] !== null
            ? 'Potential loss is RM '.number_format((float) $calculation['potential_loss'], 2).'.'
            : 'Cost price is unavailable, so update the ingredient cost to calculate RM impact.';

        return [
            'recommendation_title' => 'Prioritize '.$ingredient->name.' before expiry',
            'recommendation_body' => "{$ingredient->name} expires in {$days} day(s). Prioritize it in daily production, alert staff to use this batch first, consider a small promotion or bundle item, and reduce the next reorder quantity until this stock is cleared. {$lossText}",
        ];
    }

    /**
     * @param  array<string, mixed>|null  $input
     * @param  array<string, mixed>|null  $output
     */
    private function logToolCall(AgentRun $agentRun, string $toolName, ?array $input, ?array $output, string $status = 'completed'): AgentToolCall
    {
        return $agentRun->toolCalls()->create([
            'tool_name' => $toolName,
            'input_payload' => $input,
            'output_payload' => $output,
            'status' => $status,
        ]);
    }
}
