<?php

namespace App\Services\Procurement;

use App\Contracts\AI\StructuredDecisionProvider;
use App\Models\Ingredient;
use App\Services\Agent\SupplierComparisonService;

class GptProcurementReviewService
{
    public function __construct(
        private readonly StructuredDecisionProvider $provider,
        private readonly SupplierComparisonService $supplierComparison,
    ) {}

    /**
     * @param  array<int, mixed>  $pendingPurchaseOrders
     * @param  array<string, mixed>|null  $supplierComparisonResults
     * @param  array<string, mixed>|null  $stockPrediction
     * @return array{recommended_supplier_id: int|null, recommended_quantity: float, risk_level: string, reasoning_summary: string, cost_observation: string, delivery_risk: string, stockout_risk: string, confidence: float, human_approval_required: true}
     */
    public function review(
        Ingredient $ingredient,
        float|int $currentStock,
        float|int $minimumStock,
        float|int $averageDailyUsage,
        array $pendingPurchaseOrders = [],
        ?array $supplierComparisonResults = null,
        ?array $stockPrediction = null,
    ): array {
        $comparison = $supplierComparisonResults ?? $this->supplierComparison->compare($ingredient);
        $context = [
            'ingredient' => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'sku' => $ingredient->sku,
                'unit' => $ingredient->unit,
            ],
            'stock' => [
                'current' => (float) $currentStock,
                'minimum' => (float) $minimumStock,
                'average_daily_usage' => (float) $averageDailyUsage,
            ],
            'pending_purchase_orders' => $this->normalizePendingOrders($pendingPurchaseOrders),
            'supplier_comparison' => $this->normalizeComparison($comparison),
        ];

        if ($stockPrediction !== null) {
            $context['stock_prediction'] = $stockPrediction;
        }

        $response = $this->provider->generateJson(
            'Review procurement context and return only the requested JSON object. Do not provide chain-of-thought. Keep reasoning_summary concise. A human must approve any procurement action.',
            json_encode($context, JSON_THROW_ON_ERROR),
            ['temperature' => 0.1, 'max_tokens' => 500],
        );

        return $this->normalizeResponse($response['json'] ?? [], $comparison);
    }

    /** @param array<int, mixed> $orders */
    private function normalizePendingOrders(array $orders): array
    {
        return array_values(array_map(static function (mixed $order): array {
            if (is_object($order)) {
                return [
                    'id' => $order->id ?? null,
                    'supplier_id' => $order->supplier_id ?? null,
                    'status' => $order->status ?? null,
                    'quantity' => $order->quantity ?? null,
                ];
            }

            return is_array($order) ? array_intersect_key($order, array_flip(['id', 'supplier_id', 'status', 'quantity'])) : [];
        }, $orders));
    }

    /** @param array<string, mixed> $comparison */
    private function normalizeComparison(array $comparison): array
    {
        return [
            'recommended_supplier' => $this->supplierFacts($comparison['recommended_supplier'] ?? null),
            'suppliers' => array_values(array_map(fn (mixed $supplier): array => $this->supplierFacts($supplier), $comparison['suppliers'] ?? [])),
            'decision_factors' => array_values(array_filter($comparison['decision_factors'] ?? [], 'is_string')),
        ];
    }

    /** @return array<string, mixed> */
    private function supplierFacts(mixed $supplier): array
    {
        if (! is_array($supplier)) {
            return [];
        }

        return array_intersect_key($supplier, array_flip([
            'id', 'name', 'assigned_supplier', 'latest_item_price', 'average_historical_price',
            'estimated_lead_time_days', 'completed_item_records', 'quality_exception_rate',
            'contact_available', 'has_history', 'history_label', 'decision_factors',
        ]));
    }

    /** @param array<string, mixed> $response @param array<string, mixed> $comparison */
    private function normalizeResponse(array $response, array $comparison): array
    {
        $supplierIds = collect($comparison['suppliers'] ?? [])->map(fn (mixed $supplier) => is_array($supplier) ? $supplier['id'] ?? null : null)->filter(fn ($id): bool => is_numeric($id))->map(fn ($id): int => (int) $id)->values();
        $supplierId = is_numeric($response['recommended_supplier_id'] ?? null) ? (int) $response['recommended_supplier_id'] : null;
        $quantity = is_numeric($response['recommended_quantity'] ?? null) ? (float) $response['recommended_quantity'] : 0.0;
        $confidence = is_numeric($response['confidence'] ?? null) ? (float) $response['confidence'] : -1.0;
        $risk = $response['risk_level'] ?? null;

        if ($supplierId === null || ! $supplierIds->contains($supplierId) || $quantity < 1 || $confidence < 0 || $confidence > 1 || ! in_array($risk, ['low', 'medium', 'high', 'critical'], true)) {
            return $this->fallback();
        }

        return [
            'recommended_supplier_id' => $supplierId,
            'recommended_quantity' => $quantity,
            'risk_level' => $risk,
            'reasoning_summary' => $this->concise($response['reasoning_summary'] ?? ''),
            'cost_observation' => $this->concise($response['cost_observation'] ?? ''),
            'delivery_risk' => $this->concise($response['delivery_risk'] ?? ''),
            'stockout_risk' => $this->concise($response['stockout_risk'] ?? ''),
            'confidence' => $confidence,
            'human_approval_required' => true,
        ];
    }

    /** @return array{recommended_supplier_id: null, recommended_quantity: 0.0, risk_level: 'high', reasoning_summary: string, cost_observation: string, delivery_risk: string, stockout_risk: string, confidence: 0.0, human_approval_required: true} */
    private function fallback(): array
    {
        return [
            'recommended_supplier_id' => null,
            'recommended_quantity' => 0.0,
            'risk_level' => 'high',
            'reasoning_summary' => 'Procurement review could not be validated safely.',
            'cost_observation' => '',
            'delivery_risk' => '',
            'stockout_risk' => '',
            'confidence' => 0.0,
            'human_approval_required' => true,
        ];
    }

    private function concise(mixed $value): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, 500) : '';
    }
}
