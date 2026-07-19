<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();

        $suppliers = Supplier::query()
            ->select(['id', 'name', 'contact_person', 'phone', 'email', 'created_at'])
            ->withCount('ingredients')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('suppliers.index', [
            'title' => 'Ting Hao | Suppliers',
            'suppliers' => $suppliers,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('suppliers.create', [
            'title' => 'Ting Hao | Add Supplier',
            'supplier' => new Supplier(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->validateSupplier($request));

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('status', 'Supplier added successfully.');
    }

    public function show(Supplier $supplier): View
    {
        return view('suppliers.show', [
            'title' => "Ting Hao | {$supplier->name}",
            'supplier' => $supplier->load([
                'ingredients.category',
                'purchaseOrders' => fn ($query) => $query->latest()->take(5),
            ]),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.edit', [
            'title' => "Ting Hao | Edit {$supplier->name}",
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validateSupplier($request, $supplier));

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('status', 'Supplier updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSupplier(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplier)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
