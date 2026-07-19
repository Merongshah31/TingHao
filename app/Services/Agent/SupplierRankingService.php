<?php

namespace App\Services\Agent;

use App\Models\Supplier;
use Illuminate\Support\Str;

class SupplierRankingService
{
    /**
     * @param  array<string, mixed>  $parsedIntent
     * @param  array<int, array<string, mixed>>  $matchedIngredients
     * @return array{recommended_supplier: array<string, mixed>|null, ranked_suppliers: array<int, array<string, mixed>>}
     */
    public function rank(array $parsedIntent, array $matchedIngredients): array
    {
        $parsedSupplierName = (string) ($parsedIntent['supplier_name'] ?? '');
        $linkedSupplierIds = collect($matchedIngredients)->pluck('supplier_id')->filter()->unique()->values();

        $suppliers = Supplier::query()
            ->select(['id', 'name', 'email', 'phone', 'notes'])
            ->when($linkedSupplierIds->isNotEmpty(), function ($query) use ($linkedSupplierIds, $parsedSupplierName): void {
                $query->whereIn('id', $linkedSupplierIds)
                    ->when(filled($parsedSupplierName), function ($query) use ($parsedSupplierName): void {
                        $cleanName = trim(preg_replace('/^supplier\s+/i', '', $parsedSupplierName) ?? $parsedSupplierName);
                        $query->orWhere('name', 'like', '%'.$parsedSupplierName.'%')
                            ->orWhere('name', 'like', '%'.$cleanName.'%');
                    });
            }, function ($query) use ($parsedSupplierName): void {
                if (filled($parsedSupplierName)) {
                    $cleanName = trim(preg_replace('/^supplier\s+/i', '', $parsedSupplierName) ?? $parsedSupplierName);
                    $query->where('name', 'like', '%'.$parsedSupplierName.'%')
                        ->orWhere('name', 'like', '%'.$cleanName.'%');
                }
            })
            ->orderBy('name')
            ->limit(12)
            ->get();

        if ($suppliers->isEmpty()) {
            $suppliers = Supplier::query()
                ->select(['id', 'name', 'email', 'phone', 'notes'])
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        $ranked = $suppliers
            ->map(function (Supplier $supplier) use ($linkedSupplierIds, $parsedSupplierName): array {
                $score = 0;
                $breakdown = [];

                if ($linkedSupplierIds->contains($supplier->id)) {
                    $score += 50;
                    $breakdown[] = 'Linked to matched ingredient.';
                }

                if (filled($supplier->email)) {
                    $score += 20;
                    $breakdown[] = 'Has email.';
                }

                if (filled($supplier->phone)) {
                    $score += 10;
                    $breakdown[] = 'Has phone.';
                }

                if (filled($parsedSupplierName)) {
                    $haystack = Str::lower($supplier->name.' '.$supplier->notes);
                    $cleanName = Str::lower(trim(preg_replace('/^supplier\s+/i', '', $parsedSupplierName) ?? $parsedSupplierName));

                    if (Str::contains($haystack, Str::lower($parsedSupplierName)) || Str::contains($haystack, $cleanName)) {
                        $score += 30;
                        $breakdown[] = 'Matches parsed supplier hint.';
                    }
                }

                if ($breakdown === []) {
                    $breakdown[] = 'Fallback supplier because no linked supplier matched.';
                }

                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'email' => $supplier->email,
                    'phone' => $supplier->phone,
                    'notes' => $supplier->notes,
                    'score' => $score,
                    'score_breakdown' => $breakdown,
                    'explanation' => implode(' ', $breakdown),
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->all();

        return [
            'recommended_supplier' => $ranked[0] ?? null,
            'ranked_suppliers' => $ranked,
        ];
    }
}
