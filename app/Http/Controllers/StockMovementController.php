<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->string('type')->toString();
        $ingredientId = $request->integer('ingredient');

        $movements = StockMovement::query()
            ->with(['ingredient', 'creator'])
            ->when(in_array($type, [StockMovement::TYPE_IN, StockMovement::TYPE_OUT], true), fn ($query) => $query->where('type', $type))
            ->when($ingredientId > 0, fn ($query) => $query->where('ingredient_id', $ingredientId))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('stock.index', [
            'title' => 'Ting Hao | Stock History',
            'movements' => $movements,
            'ingredients' => Ingredient::orderBy('name')->get(),
            'selectedType' => $type,
            'selectedIngredient' => $ingredientId,
        ]);
    }

    public function create(Ingredient $ingredient, string $type): View
    {
        abort_unless(in_array($type, [StockMovement::TYPE_IN, StockMovement::TYPE_OUT], true), 404);

        return view('stock.create', [
            'title' => 'Ting Hao | Record Stock',
            'ingredient' => $ingredient,
            'type' => $type,
            'typeLabel' => $type === StockMovement::TYPE_IN ? 'Stock In' : 'Stock Out',
        ]);
    }

    public function store(Request $request, Ingredient $ingredient, string $type): RedirectResponse
    {
        abort_unless(in_array($type, [StockMovement::TYPE_IN, StockMovement::TYPE_OUT], true), 404);

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($ingredient, $type, $data, $request): void {
            $lockedIngredient = Ingredient::query()
                ->whereKey($ingredient->id)
                ->lockForUpdate()
                ->firstOrFail();

            $quantityBefore = (float) $lockedIngredient->quantity;
            $movementQuantity = (float) $data['quantity'];
            $quantityAfter = $type === StockMovement::TYPE_IN
                ? $quantityBefore + $movementQuantity
                : $quantityBefore - $movementQuantity;

            if ($quantityAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stock out quantity cannot be greater than current stock.',
                ]);
            }

            StockMovement::create([
                'ingredient_id' => $lockedIngredient->id,
                'type' => $type,
                'quantity' => $movementQuantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $lockedIngredient->update([
                'quantity' => $quantityAfter,
                'updated_by' => $request->user()->id,
            ]);
        });

        return redirect()
            ->route('inventory.show', $ingredient)
            ->with('status', "{$this->movementLabel($type)} recorded successfully.");
    }

    private function movementLabel(string $type): string
    {
        return $type === StockMovement::TYPE_IN ? 'Stock in' : 'Stock out';
    }
}
