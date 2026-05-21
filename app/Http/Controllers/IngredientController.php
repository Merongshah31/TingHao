<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IngredientController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $categoryId = $request->integer('category');

        $ingredients = Ingredient::query()
            ->with(['category', 'supplier'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('inventory.index', [
            'title' => 'Ting Hao | Inventory',
            'ingredients' => $ingredients,
            'categories' => Category::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'search' => $search,
            'selectedCategory' => $categoryId,
        ]);
    }

    public function create(): View
    {
        return view('inventory.create', [
            'title' => 'Ting Hao | Add Ingredient',
            'ingredient' => new Ingredient(),
            'categories' => Category::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateIngredient($request);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $ingredient = Ingredient::create($data);

        return redirect()
            ->route('inventory.show', $ingredient)
            ->with('status', 'Ingredient added successfully.');
    }

    public function show(Ingredient $ingredient): View
    {
        return view('inventory.show', [
            'title' => "Ting Hao | {$ingredient->name}",
            'ingredient' => $ingredient->load(['category', 'supplier', 'creator', 'updater']),
            'recentMovements' => $ingredient->stockMovements()->latest()->take(5)->get(),
        ]);
    }

    public function edit(Ingredient $ingredient): View
    {
        return view('inventory.edit', [
            'title' => "Ting Hao | Edit {$ingredient->name}",
            'ingredient' => $ingredient,
            'categories' => Category::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $this->validateIngredient($request, $ingredient);
        $data['updated_by'] = $request->user()->id;

        $ingredient->update($data);

        return redirect()
            ->route('inventory.show', $ingredient)
            ->with('status', 'Ingredient updated successfully.');
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        $ingredient->delete();

        return redirect()
            ->route('inventory.index')
            ->with('status', 'Ingredient deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateIngredient(Request $request, ?Ingredient $ingredient = null): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('ingredients', 'sku')->ignore($ingredient)],
            'unit' => ['required', 'string', 'max:30'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
