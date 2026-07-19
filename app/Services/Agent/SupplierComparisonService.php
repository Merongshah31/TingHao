<?php

namespace App\Services\Agent;

use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class SupplierComparisonService
{
    /**
     * @return array{recommended_supplier: array<string, mixed>|null, suppliers: array<int, array<string, mixed>>, decision_factors: array<int, string>}
     */
    public function compare(Ingredient $ingredient): array
    {
        $supplierIds = collect([$ingredient->supplier_id])
            ->merge(Supplier::query()
                ->whereHas('purchaseOrders.items', fn ($query) => $query->where('ingredient_id', $ingredient->id))
                ->pluck('id'));

        if ($ingredient->category_id) {
            $supplierIds = $supplierIds->merge(Supplier::query()
                ->whereHas('ingredients', fn ($query) => $query->where('category_id', $ingredient->category_id))
                ->pluck('id'));
        }

        $suppliers = Supplier::query()
            ->whereIn('id', $supplierIds->filter()->unique())
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier): array => $this->supplierFacts($ingredient, $supplier))
            ->sort(function (array $left, array $right): int {
                return [
                    ! $left['has_history'],
                    $left['quality_exception_rate'] ?? INF,
                    $left['estimated_lead_time_days'] ?? INF,
                    $left['average_historical_price'] ?? INF,
                    ! $left['contact_available'],
                    ! $left['assigned_supplier'],
                    $left['name'],
                ] <=> [
                    ! $right['has_history'],
                    $right['quality_exception_rate'] ?? INF,
                    $right['estimated_lead_time_days'] ?? INF,
                    $right['average_historical_price'] ?? INF,
                    ! $right['contact_available'],
                    ! $right['assigned_supplier'],
                    $right['name'],
                ];
            })
            ->values()
            ->map(fn (array $supplier, int $index): array => [...$supplier, 'rank' => $index + 1]);

        $recommended = $suppliers->first();

        return [
            'recommended_supplier' => $recommended,
            'suppliers' => $suppliers->all(),
            'decision_factors' => $recommended['decision_factors'] ?? ['No eligible supplier is linked to this ingredient or its purchase history.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierFacts(Ingredient $ingredient, Supplier $supplier): array
    {
        $items = PurchaseOrderItem::query()
            ->with('purchaseOrder:id,supplier_id,status,order_date,received_at,closed_at')
            ->where('ingredient_id', $ingredient->id)
            ->where('unit_price', '>=', 0)
            ->whereHas('purchaseOrder', fn ($query) => $query
                ->where('supplier_id', $supplier->id)
                ->whereNotIn('status', [PurchaseOrder::STATUS_REJECTED, PurchaseOrder::STATUS_CANCELLED]))
            ->orderByDesc('id')
            ->get();

        $completedItems = $items->filter(fn (PurchaseOrderItem $item): bool => in_array(
            $item->purchaseOrder?->status,
            [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED],
            true
        ));
        $prices = $items->pluck('unit_price')->filter(fn ($price): bool => is_numeric($price) && (float) $price > 0);
        $leadTimes = $this->leadTimes($completedItems);
        $received = (float) $completedItems->sum('received_quantity');
        $exceptions = (float) $completedItems->sum(fn (PurchaseOrderItem $item): float => (float) $item->damaged_quantity + (float) $item->returned_quantity + (float) $item->shortage_quantity
        );
        $exceptionRate = $received > 0 ? round($exceptions / $received, 4) : null;
        $contactAvailable = filled($supplier->email) || filled($supplier->phone);
        $hasHistory = $items->isNotEmpty();

        $factors = [];
        if ($supplier->id === $ingredient->supplier_id) {
            $factors[] = 'Currently assigned to this ingredient.';
        }
        if ($prices->isNotEmpty()) {
            $factors[] = 'Latest recorded item price is RM '.number_format((float) $prices->first(), 2).'.';
        }
        if ($leadTimes->isNotEmpty()) {
            $factors[] = 'Average completed-order lead time is '.(int) round((float) $leadTimes->average()).' day(s).';
        }
        if ($received > 0) {
            $factors[] = $exceptions > 0
                ? number_format($exceptions, 2).' units were recorded as damaged, returned, or short.'
                : 'No damage, return, or shortage was recorded in completed item history.';
        }
        if ($contactAvailable) {
            $factors[] = 'Supplier contact details are available.';
        }
        if (! $hasHistory) {
            $factors[] = 'Insufficient history.';
        }

        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'assigned_supplier' => $supplier->id === $ingredient->supplier_id,
            'latest_item_price' => $prices->isNotEmpty() ? round((float) $prices->first(), 2) : null,
            'average_historical_price' => $prices->isNotEmpty() ? round((float) $prices->average(), 2) : null,
            'estimated_lead_time_days' => $leadTimes->isNotEmpty() ? (int) round((float) $leadTimes->average()) : null,
            'completed_item_records' => $completedItems->count(),
            'damaged_quantity' => round((float) $completedItems->sum('damaged_quantity'), 2),
            'returned_quantity' => round((float) $completedItems->sum('returned_quantity'), 2),
            'shortage_quantity' => round((float) $completedItems->sum('shortage_quantity'), 2),
            'quality_exception_rate' => $exceptionRate,
            'contact_available' => $contactAvailable,
            'has_history' => $hasHistory,
            'history_label' => $hasHistory ? 'History available' : 'Insufficient history',
            'decision_factors' => $factors,
        ];
    }

    /**
     * @param  Collection<int, PurchaseOrderItem>  $items
     * @return Collection<int, int>
     */
    private function leadTimes(Collection $items): Collection
    {
        return $items->map(function (PurchaseOrderItem $item): ?int {
            $purchaseOrder = $item->purchaseOrder;
            $completedAt = $purchaseOrder?->received_at ?? $purchaseOrder?->closed_at;

            if (! $purchaseOrder?->order_date || ! $completedAt) {
                return null;
            }

            $days = $purchaseOrder->order_date->startOfDay()->diffInDays($completedAt->startOfDay(), false);

            return $days >= 0 ? (int) $days : null;
        })->filter(fn (?int $days): bool => $days !== null)->values();
    }
}
