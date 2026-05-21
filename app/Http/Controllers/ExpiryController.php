<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExpiryController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('filter')->toString() ?: 'expiring';

        $ingredients = Ingredient::query()
            ->with('category')
            ->when($filter === 'expired', fn ($query) => $query->expired())
            ->when($filter !== 'expired', fn ($query) => $query->expiringWithin(30))
            ->orderBy('expiry_date')
            ->get();

        return view('expiry.index', [
            'title' => 'Ting Hao | Expiry Tracking',
            'ingredients' => $ingredients,
            'filter' => $filter,
        ]);
    }

    public function removeExpired(Request $request, Ingredient $ingredient): RedirectResponse
    {
        abort_unless($ingredient->expiry_date && $ingredient->expiry_date->isPast(), 422, 'Only expired ingredients can be removed.');

        if ((float) $ingredient->quantity <= 0) {
            return back()->with('status', 'Expired ingredient already has zero stock.');
        }

        DB::transaction(function () use ($ingredient, $request): void {
            $lockedIngredient = Ingredient::query()
                ->whereKey($ingredient->id)
                ->lockForUpdate()
                ->firstOrFail();

            $quantityBefore = (float) $lockedIngredient->quantity;

            StockMovement::create([
                'ingredient_id' => $lockedIngredient->id,
                'type' => StockMovement::TYPE_OUT,
                'quantity' => $quantityBefore,
                'quantity_before' => $quantityBefore,
                'quantity_after' => 0,
                'reason' => 'Expired item removal',
                'notes' => 'Removed from stock through expiry management.',
                'created_by' => $request->user()->id,
            ]);

            $lockedIngredient->update([
                'quantity' => 0,
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('status', 'Expired stock removed and recorded in stock history.');
    }
}
