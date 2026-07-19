<?php

namespace App\Services\Agent;

use Illuminate\Support\Str;

class RestockPlanningService
{
    /**
     * @param  array<string, mixed>  $parsedIntent
     * @param  array<int, array<string, mixed>>  $matchedIngredients
     * @return array<int, array<string, mixed>>
     */
    public function plan(array $parsedIntent, array $matchedIngredients): array
    {
        $parsedIngredients = collect($parsedIntent['ingredients'] ?? []);

        return collect($matchedIngredients)
            ->map(function (array $ingredient) use ($parsedIngredients): array {
                $parsedIngredient = $parsedIngredients->first(function ($parsed) use ($ingredient): bool {
                    return is_array($parsed)
                        && filled($parsed['name'] ?? null)
                        && Str::contains(Str::lower((string) $ingredient['name']), Str::lower((string) $parsed['name']));
                });

                $currentQuantity = (float) ($ingredient['current_quantity'] ?? 0);
                $minimumStock = (float) ($ingredient['minimum_stock'] ?? 0);
                $parsedQuantity = is_array($parsedIngredient) && is_numeric($parsedIngredient['quantity'] ?? null)
                    ? (float) $parsedIngredient['quantity']
                    : null;

                $recommendedQuantity = $parsedQuantity !== null && $parsedQuantity > 0
                    ? $parsedQuantity
                    : max(($minimumStock * 2) - $currentQuantity, $minimumStock);

                $recommendedQuantity = max(0, round($recommendedQuantity, 2));
                $lowStock = $currentQuantity <= $minimumStock;

                return [
                    'ingredient_id' => $ingredient['id'],
                    'ingredient_name' => $ingredient['name'],
                    'unit' => $ingredient['unit'] ?? null,
                    'current_quantity' => $currentQuantity,
                    'minimum_stock' => $minimumStock,
                    'low_stock' => $lowStock,
                    'parsed_quantity' => $parsedQuantity,
                    'recommended_quantity' => $recommendedQuantity,
                    'estimated_unit_price' => (float) ($ingredient['estimated_unit_price'] ?? 0),
                    'supplier_id' => $ingredient['supplier_id'] ?? null,
                    'reasoning' => $parsedQuantity !== null && $parsedQuantity > 0
                        ? "Used parsed request quantity of {$parsedQuantity} ".($ingredient['unit'] ?? 'units').'.'
                        : "Recommended max(minimum stock x 2 - current quantity, minimum stock): max({$minimumStock} x 2 - {$currentQuantity}, {$minimumStock}).",
                ];
            })
            ->filter(fn (array $plan): bool => $plan['recommended_quantity'] > 0)
            ->values()
            ->all();
    }
}
