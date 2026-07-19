<?php

namespace App\Services\Agent;

use App\Models\Ingredient;

class InventoryLookupToolService
{
    /**
     * @param  array<int, array<string, mixed>>  $ingredients
     * @return array<int, array<string, mixed>>
     */
    public function lookup(array $ingredients): array
    {
        $names = collect($ingredients)
            ->pluck('name')
            ->filter()
            ->map(fn ($name) => trim((string) $name))
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return [];
        }

        return Ingredient::query()
            ->select(['id', 'supplier_id', 'name', 'sku', 'unit', 'quantity', 'minimum_stock', 'cost_price'])
            ->with('supplier:id,name,email,phone,notes')
            ->where(function ($query) use ($names): void {
                foreach ($names as $name) {
                    $query->orWhere('name', 'like', '%'.$name.'%')
                        ->orWhere('sku', 'like', '%'.$name.'%');
                }
            })
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (Ingredient $ingredient): array => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'sku' => $ingredient->sku,
                'current_quantity' => (float) $ingredient->quantity,
                'minimum_stock' => (float) $ingredient->minimum_stock,
                'unit' => $ingredient->unit,
                'estimated_unit_price' => (float) ($ingredient->cost_price ?? 0),
                'supplier_id' => $ingredient->supplier_id,
                'supplier_name' => $ingredient->supplier?->name,
                'supplier_email' => $ingredient->supplier?->email,
                'supplier_phone' => $ingredient->supplier?->phone,
                'low_stock' => $ingredient->isLowStock(),
            ])
            ->values()
            ->all();
    }
}
