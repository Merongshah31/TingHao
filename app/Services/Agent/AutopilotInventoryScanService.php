<?php

namespace App\Services\Agent;

use App\Models\AgentRun;
use App\Models\AgentToolCall;
use App\Models\Ingredient;
use App\Models\User;
use App\Services\Stock\StockPredictionApiClient;
use App\Services\Stock\StockPredictionInputBuilder;
use Illuminate\Support\Facades\Cache;

class AutopilotInventoryScanService
{
    private const RESTOCK_ACTIONS = ['add_stock_now', 'add_stock_soon'];

    public function __construct(
        private readonly StockPredictionApiClient $predictionClient,
        private readonly StockPredictionInputBuilder $inputBuilder,
        private readonly SupplierComparisonService $supplierComparison,
        private readonly PredictionRestockPlanningService $restockPlanning,
        private readonly ReasoningActivityService $reasoningActivity,
    ) {}

    /**
     * @return array{run: AgentRun, duplicate: bool, predictions: int, drafts: int}
     */
    public function scan(): array
    {
        $dedupeMinutes = max(1, (int) config('autopilot.scan_dedupe_minutes', 30));
        $recentRun = AgentRun::query()
            ->where('input_type', 'autopilot_inventory_scan')
            ->where('created_at', '>=', now()->subMinutes($dedupeMinutes))
            ->latest()
            ->first();

        if ($recentRun) {
            return ['run' => $recentRun, 'duplicate' => true, 'predictions' => 0, 'drafts' => 0];
        }

        $user = User::query()->where('role', User::ROLE_ADMIN)->where('status', User::STATUS_ACTIVE)->oldest()->first()
            ?? User::query()->where('status', User::STATUS_ACTIVE)->oldest()->first();

        if (! $user) {
            throw new \RuntimeException('Autopilot scan requires at least one active user for audit ownership.');
        }

        $ingredients = Ingredient::query()
            ->with(['supplier', 'category'])
            ->where(function ($query): void {
                $query->whereColumn('quantity', '<=', 'minimum_stock')
                    ->orWhere(function ($expiryQuery): void {
                        $expiryQuery->whereNotNull('expiry_date')
                            ->whereDate('expiry_date', '<=', now()->addDays(7)->toDateString());
                    });
            })
            ->orderBy('id')
            ->get();

        $run = AgentRun::create([
            'user_id' => $user->id,
            'input_text' => 'Scheduled proactive inventory and expiry scan.',
            'input_type' => 'autopilot_inventory_scan',
            'status' => AgentRun::STATUS_COMPLETED,
            'qwen_mocked' => false,
        ]);

        $scanTool = $this->tool($run, 'scan_inventory', [], [
            'ingredient_count' => $ingredients->count(),
            'low_stock_count' => $ingredients->filter->isLowStock()->count(),
            'expiry_risk_count' => $ingredients->filter(fn (Ingredient $ingredient): bool => $ingredient->expiry_date !== null && $ingredient->expiry_date->lte(now()->addDays(7)))->count(),
        ]);
        $this->reasoningActivity->observe($run, 'Proactive inventory scan', 'Laravel inspected low-stock ingredients and expiry risks without using Qwen.', $scanTool->output_payload);

        $recommendations = [];
        $drafts = [];

        foreach ($ingredients as $ingredient) {
            $input = $this->inputBuilder->build($ingredient);
            $prediction = $this->predictionFor($ingredient, $input);
            $predictionTool = $this->tool($run, 'predict_stock_action', ['ingredient_id' => $ingredient->id], [
                'recommended_action' => $prediction['recommended_action'] ?? 'unavailable',
                'suggested_quantity' => $prediction['suggested_quantity'] ?? null,
                'confidence' => $prediction['confidence'] ?? null,
                'risk_level' => $prediction['risk_level'] ?? null,
                'reason_codes' => $prediction['reason_codes'] ?? [],
            ], ($prediction['available'] ?? false) ? 'completed' : 'failed');
            $this->reasoningActivity->toolResult($run, 'FastAPI stock prediction', 'Prediction evidence was recorded for '.$ingredient->name.'.', $predictionTool);

            $action = (string) ($prediction['recommended_action'] ?? 'monitor');
            $comparison = in_array($action, self::RESTOCK_ACTIONS, true)
                ? $this->supplierComparison->compare($ingredient)
                : ['recommended_supplier' => null, 'suppliers' => [], 'decision_factors' => []];

            if (in_array($action, self::RESTOCK_ACTIONS, true)) {
                $comparisonTool = $this->tool($run, 'compare_suppliers', ['ingredient_id' => $ingredient->id], $comparison);
                $this->reasoningActivity->toolResult($run, 'Supplier comparison', 'Eligible suppliers were compared using available price, lead-time, receiving, quality, and contact history.', $comparisonTool);
            }

            $recommendation = [
                'ingredient_id' => $ingredient->id,
                'ingredient' => $ingredient->name,
                'prediction' => $this->predictionSnapshot($prediction, $input),
                'supplier_comparison' => $comparison,
                'purchase_order_id' => null,
            ];

            if ($this->shouldCreateDraft($prediction)) {
                $result = $this->restockPlanning->plan($user, $ingredient, $prediction, $input);
                $recommendation['purchase_order_id'] = $result['purchase_order']?->id;
                if ($result['purchase_order']) {
                    $drafts[] = $result['purchase_order']->id;
                }
                $this->tool($run, 'prepare_autopilot_po_draft', ['ingredient_id' => $ingredient->id], [
                    'status' => $result['status'],
                    'purchase_order_id' => $result['purchase_order']?->id,
                    'message' => $result['message'],
                ], $result['purchase_order'] ? 'completed' : 'skipped');
            }

            $recommendations[] = $recommendation;
        }

        $summary = 'Autopilot scanned '.$ingredients->count().' ingredient(s), recorded '.count($recommendations).' prediction result(s), and prepared '.count($drafts).' pending approval draft(s).';
        $this->reasoningActivity->finalSummary($run, 'Autopilot scan completed', $summary);
        $run->update([
            'final_summary' => $summary,
            'parsed_intent' => [
                'intent' => 'proactive_inventory_monitoring',
                'source' => 'scheduled_command',
                'po_draft_enabled' => (bool) config('autopilot.po_draft_enabled', false),
                'recommendations' => $recommendations,
                'purchase_order_ids' => $drafts,
            ],
        ]);

        return ['run' => $run->fresh(['toolCalls', 'reasoningSteps']), 'duplicate' => false, 'predictions' => count($recommendations), 'drafts' => count($drafts)];
    }

    /** @param array<string, mixed> $input */
    private function predictionFor(Ingredient $ingredient, array $input): array
    {
        $key = $this->inputBuilder->cacheKey($ingredient);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $this->predictionClient->applyBusinessRules($cached, $this->inputBuilder->businessFacts($ingredient));
        }

        $prediction = $this->predictionClient->predict($input);
        Cache::put($key, $prediction, now()->addMinutes(max(1, (int) config('stock_prediction.cache_minutes', 30))));

        return $prediction;
    }

    /** @param array<string, mixed> $prediction */
    private function shouldCreateDraft(array $prediction): bool
    {
        if (! config('autopilot.po_draft_enabled', false) || ! in_array($prediction['recommended_action'] ?? null, self::RESTOCK_ACTIONS, true)) {
            return false;
        }

        $confidence = (float) ($prediction['confidence'] ?? 0);
        $confidence = $confidence > 1 ? $confidence / 100 : $confidence;

        return $confidence >= (float) config('autopilot.minimum_confidence', 0.75);
    }

    /** @param array<string, mixed> $prediction @param array<string, mixed> $input */
    private function predictionSnapshot(array $prediction, array $input): array
    {
        return [
            'recommended_action' => $prediction['recommended_action'] ?? null,
            'estimated_days_until_stockout' => $prediction['estimated_days_until_stockout'] ?? null,
            'suggested_quantity' => $prediction['suggested_quantity'] ?? null,
            'risk_level' => $prediction['risk_level'] ?? null,
            'confidence' => $prediction['confidence'] ?? null,
            'reason_codes' => $prediction['reason_codes'] ?? [],
            'current_quantity' => $input['current_quantity'] ?? null,
            'minimum_stock' => $input['minimum_stock'] ?? null,
            'pending_po_quantity' => $input['pending_po_quantity'] ?? null,
        ];
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $output */
    private function tool(AgentRun $run, string $name, array $input, array $output, string $status = 'completed'): AgentToolCall
    {
        return $run->toolCalls()->create([
            'tool_name' => $name,
            'input_payload' => $input,
            'output_payload' => $output,
            'status' => $status,
        ]);
    }
}
