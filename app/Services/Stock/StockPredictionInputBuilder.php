<?php

namespace App\Services\Stock;

use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;

class StockPredictionInputBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Ingredient $ingredient): array
    {
        return [
            'ingredient' => $ingredient->name,
            'current_quantity' => (float) $ingredient->quantity,
            'unit' => $ingredient->unit,
            'minimum_stock' => (float) $ingredient->minimum_stock,
            'stock_out_last_7_days' => $this->stockOutTotal($ingredient, 7),
            'stock_out_last_14_days' => $this->stockOutTotal($ingredient, 14),
            'stock_out_last_30_days' => $this->stockOutTotal($ingredient, 30),
            'expiry_days_remaining' => $this->expiryDaysRemaining($ingredient),
            'pending_po_quantity' => $this->pendingPurchaseOrderQuantity($ingredient),
            'supplier_lead_time_days' => 2,
            'weekend_near' => $this->weekendNear(),
            'festival_near' => false,
        ];
    }

    public function cacheKey(Ingredient $ingredient): string
    {
        return 'stock_prediction.ingredient.'.$ingredient->id.'.v1';
    }

    /**
     * Inventory facts used to safely re-check cached prediction display rules.
     *
     * @return array<string, float|int|null>
     */
    public function businessFacts(Ingredient $ingredient): array
    {
        return [
            'current_quantity' => (float) $ingredient->quantity,
            'minimum_stock' => (float) $ingredient->minimum_stock,
            'expiry_days_remaining' => $this->expiryDaysRemaining($ingredient),
        ];
    }

    private function stockOutTotal(Ingredient $ingredient, int $days): float
    {
        return (float) StockMovement::query()
            ->where('ingredient_id', $ingredient->id)
            ->where('type', StockMovement::TYPE_OUT)
            ->where('created_at', '>=', now()->subDays($days))
            ->sum('quantity');
    }

    private function expiryDaysRemaining(Ingredient $ingredient): ?int
    {
        if (! $ingredient->expiry_date) {
            return null;
        }

        return (int) ceil(now()->startOfDay()->diffInDays($ingredient->expiry_date->startOfDay(), false));
    }

    private function pendingPurchaseOrderQuantity(Ingredient $ingredient): float
    {
        $pendingStatuses = [
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_PENDING_APPROVAL,
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_SENT,
            PurchaseOrder::STATUS_CONFIRMED,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        ];

        return (float) (PurchaseOrderItem::query()
            ->where('ingredient_id', $ingredient->id)
            ->whereHas('purchaseOrder', fn ($query) => $query->whereIn('status', $pendingStatuses))
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity > COALESCE(received_quantity, 0) THEN quantity - COALESCE(received_quantity, 0) ELSE 0 END), 0) as pending_quantity')
            ->value('pending_quantity') ?? 0);
    }

    private function weekendNear(): bool
    {
        $today = now();

        return $today->isFriday() || $today->isSaturday() || $today->isSunday();
    }
}
