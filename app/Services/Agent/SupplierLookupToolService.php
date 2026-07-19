<?php

namespace App\Services\Agent;

use App\Models\Supplier;

class SupplierLookupToolService
{
    /**
     * @param  array<int, array<string, mixed>>  $matchedIngredients
     * @return array<int, array<string, mixed>>
     */
    public function lookup(?string $supplierName, array $matchedIngredients): array
    {
        $query = Supplier::query()
            ->select(['id', 'name', 'email', 'phone', 'notes']);

        if (filled($supplierName)) {
            $cleanSupplierName = trim(preg_replace('/^supplier\s+/i', '', (string) $supplierName) ?? (string) $supplierName);

            $query->where(function ($query) use ($supplierName, $cleanSupplierName): void {
                $query->where('name', 'like', '%'.$supplierName.'%')
                    ->orWhere('name', 'like', '%'.$cleanSupplierName.'%');
            });
        } else {
            $supplierIds = collect($matchedIngredients)->pluck('supplier_id')->filter()->unique()->values();

            if ($supplierIds->isEmpty()) {
                return [];
            }

            $query->whereIn('id', $supplierIds);
        }

        return $query
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Supplier $supplier): array => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'notes' => $supplier->notes,
            ])
            ->values()
            ->all();
    }
}
