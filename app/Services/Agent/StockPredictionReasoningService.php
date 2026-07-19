<?php

namespace App\Services\Agent;

use App\Models\Ingredient;
use App\Services\Qwen\QwenClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StockPredictionReasoningService
{
    public function __construct(private readonly QwenClient $qwenClient)
    {
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $ingredientFacts
     * @return array<string, mixed>
     */
    public function explain(Ingredient $ingredient, array $prediction, array $ingredientFacts, bool $force = false): array
    {
        if (! ($prediction['available'] ?? false)) {
            return $this->unavailable('Prediction is available only after the FastAPI service returns a result.');
        }

        $payload = $this->payload($ingredient, $prediction, $ingredientFacts);
        $cacheKey = $this->cacheKey($ingredient, $payload);
        $hadCachedExplanation = Cache::has($cacheKey);
        $cached = Cache::get($cacheKey);

        if (! $force && is_array($cached)) {
            return [
                ...$cached,
                'cache_hit' => true,
                'qwen_metadata' => [
                    ...($cached['qwen_metadata'] ?? []),
                    'cache_hit' => true,
                ],
            ];
        }

        if ($force && $hadCachedExplanation) {
            Cache::forget($cacheKey);
        }

        $response = $this->qwenClient->generateJson($this->systemPrompt(), json_encode($payload), [
            'max_tokens' => (int) config('qwen.max_tokens.stock_reasoning', 300),
            'temperature' => (float) config('qwen.temperature', 0.2),
        ]);

        if ($response['mocked']) {
            $explanation = $this->mockExplanation($payload);
        } elseif ($response['json'] !== [] && $response['error'] === null) {
            $explanation = $this->normalizeExplanation($response['json'], $payload);
        } else {
            return $this->unavailable('Prediction is available, but AI explanation is temporarily unavailable.', [
                ...($response['metadata'] ?? []),
                'cache_hit' => false,
                'error' => $response['error'],
            ]);
        }

        $result = [
            'available' => true,
            'message' => null,
            'title' => $explanation['title'],
            'summary' => $explanation['summary'],
            'business_reason' => $explanation['business_reason'],
            'recommended_next_step' => $explanation['recommended_next_step'],
            'warning' => $explanation['warning'],
            'user_friendly_action' => $explanation['user_friendly_action'],
            'confidence_label' => $explanation['confidence_label'],
            'cache_hit' => false,
            'cache_replaced' => $force && $hadCachedExplanation,
            'snapshot_hash' => $this->snapshotHash($payload),
            'qwen_metadata' => [
                ...($response['metadata'] ?? []),
                'cache_hit' => false,
                'cache_replaced' => $force && $hadCachedExplanation,
                'error' => $response['error'],
            ],
        ];

        Cache::put(
            $cacheKey,
            $result,
            now()->addMinutes(max(1, (int) config('qwen.stock_reasoning_cache_minutes', 30))),
        );

        return $result;
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $ingredientFacts
     * @return array<string, mixed>
     */
    public function cachedExplanation(Ingredient $ingredient, array $prediction, array $ingredientFacts): array
    {
        if (! ($prediction['available'] ?? false)) {
            return $this->unavailable('Prediction is available only after the FastAPI service returns a result.');
        }

        $payload = $this->payload($ingredient, $prediction, $ingredientFacts);
        $cached = Cache::get($this->cacheKey($ingredient, $payload));

        if (is_array($cached)) {
            return [
                ...$cached,
                'cache_hit' => true,
                'qwen_metadata' => [
                    ...($cached['qwen_metadata'] ?? []),
                    'cache_hit' => true,
                ],
            ];
        }

        return $this->unavailable('Click Explain with Qwen to generate a business explanation for this prediction.', [
            'cache_hit' => false,
            'snapshot_hash' => $this->snapshotHash($payload),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function unavailable(string $message = 'Prediction is available, but AI explanation is temporarily unavailable.', array $metadata = []): array
    {
        return [
            'available' => false,
            'message' => $message,
            'title' => null,
            'summary' => null,
            'business_reason' => null,
            'recommended_next_step' => null,
            'warning' => null,
            'user_friendly_action' => null,
            'confidence_label' => null,
            'cache_hit' => false,
            'snapshot_hash' => null,
            'qwen_metadata' => [
                'model' => config('qwen.model', 'qwen-plus'),
                'mock_mode' => $this->qwenClient->isMockMode(),
                'server_side_configured' => $this->qwenClient->isConfigured(),
                'http_status' => null,
                'latency_ms' => null,
                'input_tokens' => null,
                'output_tokens' => null,
                'total_tokens' => null,
                'max_tokens' => (int) config('qwen.max_tokens.stock_reasoning', 300),
                'temperature' => (float) config('qwen.temperature', 0.2),
                'cache_hit' => false,
                ...$metadata,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $ingredientFacts
     * @return array<string, mixed>
     */
    private function payload(Ingredient $ingredient, array $prediction, array $ingredientFacts): array
    {
        return [
            'ingredient' => $ingredient->name,
            'current_quantity' => $ingredientFacts['current_quantity'] ?? null,
            'unit' => $ingredientFacts['unit'] ?? $ingredient->unit,
            'minimum_stock' => $ingredientFacts['minimum_stock'] ?? null,
            'recommended_action' => $prediction['recommended_action'] ?? null,
            'estimated_days_until_stockout' => $prediction['estimated_days_until_stockout'] ?? null,
            'suggested_quantity' => $prediction['suggested_quantity'] ?? null,
            'risk_level' => $prediction['risk_level'] ?? null,
            'confidence' => $prediction['confidence'] ?? null,
            'reason_codes' => collect($prediction['reason_codes'] ?? [])->values()->all(),
            'calculation_summary' => [
                'average_daily_usage' => data_get($prediction, 'calculation_summary.average_daily_usage'),
                'pending_po_quantity' => $ingredientFacts['pending_po_quantity'] ?? data_get($prediction, 'calculation_summary.pending_po_quantity'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cacheKey(Ingredient $ingredient, array $payload): string
    {
        return 'stock_prediction.qwen_explanation.v2.'.$ingredient->id.'.'.$this->snapshotHash($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function snapshotHash(array $payload): string
    {
        return sha1(json_encode($payload));
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You explain Ting Hao stock prediction results for business users.
Return JSON only with these keys: title, summary, business_reason, recommended_next_step, warning, user_friendly_action, confidence_label.
Use clear, professional, simple English only.
Do not use Malay words or mixed Malay-English wording in any field.
No markdown. No chain-of-thought. Do not recalculate the prediction. Do not invent missing values. Use only the provided FastAPI prediction facts.
Do not invent customer behavior, competitors, sales, demand, or market activity unless those facts are explicitly provided.
Do not use "ASAP" unless the provided facts explicitly say urgency is high.
If confidence is low, tell the user to monitor or review manually.
If recommended_action is do_not_buy, explain why buying now is not recommended.
If recommended_action is use_before_expiry, never suggest using expired stock.
If recommended_action is add_stock_now or add_stock_soon, suggest preparing a purchase order draft for admin approval, not automatic purchase.
Keep the response short.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $payload
     * @return array<string, string|null>
     */
    private function normalizeExplanation(array $json, array $payload): array
    {
        $fallback = $this->mockExplanation($payload);

        return [
            'title' => $this->cleanText($json['title'] ?? null, allowAsap: false) ?: $fallback['title'],
            'summary' => $this->cleanText($json['summary'] ?? null, allowAsap: false) ?: $fallback['summary'],
            'business_reason' => $this->cleanText($json['business_reason'] ?? null, allowAsap: false) ?: $fallback['business_reason'],
            'recommended_next_step' => $this->cleanText($json['recommended_next_step'] ?? null, allowAsap: false) ?: $fallback['recommended_next_step'],
            'warning' => $this->cleanText($json['warning'] ?? null, allowAsap: false),
            'user_friendly_action' => $this->cleanText($json['user_friendly_action'] ?? null, allowAsap: false) ?: $fallback['user_friendly_action'],
            'confidence_label' => $this->cleanText($json['confidence_label'] ?? null, allowAsap: false) ?: $fallback['confidence_label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string|null>
     */
    private function mockExplanation(array $payload): array
    {
        $ingredient = (string) ($payload['ingredient'] ?? 'This item');
        $action = (string) ($payload['recommended_action'] ?? 'monitor');
        $unit = (string) ($payload['unit'] ?? '');
        $suggested = $payload['suggested_quantity'] ?? null;
        $stockoutDays = $payload['estimated_days_until_stockout'] ?? null;
        $confidence = is_numeric($payload['confidence'] ?? null) ? (float) $payload['confidence'] : null;
        $currentQuantity = $payload['current_quantity'] ?? null;
        $minimumStock = $payload['minimum_stock'] ?? null;
        $suggestedQuantity = is_numeric($suggested) && (float) $suggested > 0
            ? number_format((float) $suggested, 2).' '.$unit
            : null;
        $currentStockText = is_numeric($currentQuantity)
            ? number_format((float) $currentQuantity, 2).' '.$unit
            : 'the current stock';
        $minimumStockText = is_numeric($minimumStock)
            ? number_format((float) $minimumStock, 2).' '.$unit
            : 'the minimum stock level';

        $confidenceLabel = match (true) {
            $confidence !== null && $confidence >= 0.8 => 'High',
            $confidence !== null && $confidence >= 0.6 => 'Medium',
            default => 'Low',
        };

        return match ($action) {
            'add_stock_now' => [
                'title' => $ingredient.' Stock Alert',
                'summary' => $ingredient.' is at '.$currentStockText.' while the minimum stock level is '.$minimumStockText.'.',
                'business_reason' => 'Restocking helps reduce the risk of production disruption based on the current stock and prediction result.',
                'recommended_next_step' => 'Prepare a purchase order draft'.($suggestedQuantity ? ' for '.$suggestedQuantity : '').' and send it for admin approval.',
                'warning' => 'Do not wait too long because the current stock is already below the minimum level.',
                'user_friendly_action' => 'Plan Restock',
                'confidence_label' => $confidenceLabel,
            ],
            'add_stock_soon' => [
                'title' => $ingredient.' Restock Planning',
                'summary' => $ingredient.' may run low'.($stockoutDays !== null ? ' within '.$stockoutDays.' day(s)' : '').' based on the prediction result.',
                'business_reason' => 'Planning early gives the supplier enough lead time and reduces last-minute buying.',
                'recommended_next_step' => 'Prepare a purchase order draft'.($suggestedQuantity ? ' for '.$suggestedQuantity : '').' for admin review before stock becomes critical.',
                'warning' => null,
                'user_friendly_action' => 'Plan Restock',
                'confidence_label' => $confidenceLabel,
            ],
            'buy_less' => [
                'title' => 'Buy less '.$ingredient,
                'summary' => $ingredient.' stock is higher than the normal minimum level.',
                'business_reason' => 'Buying less helps control cash flow and reduce overstock risk.',
                'recommended_next_step' => 'Review usage first before placing a smaller order.',
                'warning' => 'Avoid overbuying unless confirmed demand is coming.',
                'user_friendly_action' => 'Buy Less',
                'confidence_label' => $confidenceLabel,
            ],
            'do_not_buy' => [
                'title' => 'Do not buy '.$ingredient.' now',
                'summary' => $ingredient.' stock is still high compared to the minimum level.',
                'business_reason' => 'Buying more now may increase overstock and waste risk.',
                'recommended_next_step' => 'Monitor stock movement before placing a new order.',
                'warning' => 'Avoid unnecessary purchase until usage increases.',
                'user_friendly_action' => 'Monitor',
                'confidence_label' => $confidenceLabel,
            ],
            'use_before_expiry' => [
                'title' => 'Use '.$ingredient.' before expiry',
                'summary' => $ingredient.' has expiry risk and should be checked before buying more.',
                'business_reason' => 'Using valid stock first helps reduce waste and protect margin.',
                'recommended_next_step' => 'Review expiry plan and use safe, non-expired stock first.',
                'warning' => 'Do not use expired stock.',
                'user_friendly_action' => 'View Expiry Save Plan',
                'confidence_label' => $confidenceLabel,
            ],
            default => [
                'title' => 'Monitor '.$ingredient,
                'summary' => $ingredient.' does not need urgent purchase action now.',
                'business_reason' => 'Stock condition looks manageable based on the prediction facts.',
                'recommended_next_step' => 'Continue monitoring stock movement and refresh prediction when needed.',
                'warning' => $confidenceLabel === 'Low' ? 'Review manually because confidence is low.' : null,
                'user_friendly_action' => 'Monitor',
                'confidence_label' => $confidenceLabel,
            ],
        };
    }

    private function cleanText(mixed $value, bool $allowAsap = false): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || $this->containsDisallowedLanguage($value) || (! $allowAsap && preg_match('/\bASAP\b/i', $value))) {
            return null;
        }

        return Str::limit($value, 220, '');
    }

    private function containsDisallowedLanguage(string $value): bool
    {
        return (bool) preg_match(
            '/\b(terima kasih|sila|untuk|dan|dengan|adalah|akan|perlu|boleh|harus|kerana|jika|daripada|dari|kepada|ini|itu|stok|segera|sekarang|barang|bekalan|pembelian|pesanan|kelulusan|pentadbir|risiko|pengeluaran|jualan|pelanggan|permintaan|lebih|kurang|tinggi|rendah|naik|turun|habis|beli|jangan|guna|sebelum|tamat|tempoh|pembekal|draf|lulus|hantar)\b/i',
            $value,
        );
    }
}
