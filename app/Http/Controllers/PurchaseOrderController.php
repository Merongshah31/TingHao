<?php

namespace App\Http\Controllers;

use App\Mail\PurchaseOrderMail;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(): View
    {
        return view('purchase-orders.index', [
            'title' => 'Ting Hao | '.__('messages.purchase_orders'),
            'purchaseOrders' => PurchaseOrder::query()
                ->with('supplier')
                ->latest()
                ->paginate(12),
        ]);
    }

    public function create(): View
    {
        return $this->formView(new PurchaseOrder([
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]));
    }

    public function createFromLowStock(): View
    {
        $prefillItems = Ingredient::query()
            ->lowStock()
            ->orderBy('name')
            ->get()
            ->map(function (Ingredient $ingredient): array {
                $suggestedQuantity = (float) $ingredient->minimum_stock - (float) $ingredient->quantity;

                if ($suggestedQuantity < 1) {
                    $suggestedQuantity = max((float) $ingredient->minimum_stock, 1);
                }

                return [
                    'ingredient_id' => $ingredient->id,
                    'description' => $ingredient->name,
                    'quantity' => $suggestedQuantity,
                    'unit' => $ingredient->unit,
                    'unit_price' => (float) ($ingredient->cost_price ?? 0),
                ];
            })
            ->values()
            ->all();

        return $this->formView(new PurchaseOrder([
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]), $prefillItems);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePurchaseOrder($request);

        $purchaseOrder = DB::transaction(function () use ($data, $request): PurchaseOrder {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $items = $this->preparedItems($data['items'] ?? []);

            $purchaseOrder = PurchaseOrder::create([
                'po_number' => $this->nextPoNumber(),
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'order_date' => $data['order_date'] ?? null,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'subtotal' => collect($items)->sum('line_total'),
                'notes' => $data['notes'] ?? null,
                'email_to' => $supplier->email,
                'created_by' => $request->user()->id,
            ]);

            $purchaseOrder->items()->createMany($items);

            return $purchaseOrder;
        });

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('status', __('messages.purchase_order_created'));
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        return view('purchase-orders.show', [
            'title' => "Ting Hao | {$purchaseOrder->po_number}",
            'purchaseOrder' => $purchaseOrder->load(['supplier', 'creator', 'items.ingredient']),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        return $this->formView($purchaseOrder->load('items'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $this->validatePurchaseOrder($request, true);

        DB::transaction(function () use ($purchaseOrder, $data): void {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $items = $this->preparedItems($data['items'] ?? []);

            $purchaseOrder->update([
                'supplier_id' => $supplier->id,
                'status' => $data['status'],
                'order_date' => $data['order_date'] ?? null,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'subtotal' => collect($items)->sum('line_total'),
                'notes' => $data['notes'] ?? null,
                'email_to' => $supplier->email,
            ]);

            $purchaseOrder->items()->delete();
            $purchaseOrder->items()->createMany($items);
        });

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('status', __('messages.purchase_order_updated'));
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->delete();

        return redirect()
            ->route('purchase-orders.index')
            ->with('status', __('messages.purchase_order_deleted'));
    }

    public function sendEmail(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->load(['supplier', 'items.ingredient']);

        if (! $purchaseOrder->supplier?->email) {
            return back()->withErrors(['email' => __('messages.supplier_email_missing')]);
        }

        Mail::to($purchaseOrder->supplier->email)->send(new PurchaseOrderMail($purchaseOrder));

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_SENT,
            'sent_at' => now(),
            'email_to' => $purchaseOrder->supplier->email,
        ]);

        return back()->with('status', __('messages.purchase_order_email_sent'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $prefillItems
     */
    private function formView(PurchaseOrder $purchaseOrder, array $prefillItems = []): View
    {
        $items = $prefillItems;

        if ($purchaseOrder->exists) {
            $items = $purchaseOrder->items
                ->map(fn ($item): array => [
                    'ingredient_id' => $item->ingredient_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                ])
                ->all();
        }

        while (count($items) < 5) {
            $items[] = [
                'ingredient_id' => '',
                'description' => '',
                'quantity' => '',
                'unit' => '',
                'unit_price' => '',
            ];
        }

        return view($purchaseOrder->exists ? 'purchase-orders.edit' : 'purchase-orders.create', [
            'title' => 'Ting Hao | '.($purchaseOrder->exists ? __('messages.edit_purchase_order') : __('messages.create_purchase_order')),
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => Supplier::orderBy('name')->get(),
            'ingredients' => Ingredient::orderBy('name')->get(),
            'items' => $items,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePurchaseOrder(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'status' => [$isUpdate ? 'required' : 'nullable', Rule::in(PurchaseOrder::STATUSES)],
            'order_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array'],
            'items.*.ingredient_id' => ['nullable', 'exists:ingredients,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function preparedItems(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item): bool => ! empty($item['ingredient_id']) && ! empty($item['quantity']))
            ->map(function (array $item): array {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) ($item['unit_price'] ?? 0);

                return [
                    'ingredient_id' => $item['ingredient_id'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $quantity,
                    'unit' => $item['unit'] ?? null,
                    'unit_price' => $unitPrice,
                    'line_total' => $quantity * $unitPrice,
                ];
            })
            ->values()
            ->tap(fn ($items) => abort_if($items->isEmpty(), 422, __('messages.purchase_order_requires_items')))
            ->all();
    }

    private function nextPoNumber(): string
    {
        $year = now()->format('Y');
        $count = PurchaseOrder::where('po_number', 'like', "PO-{$year}-%")->count() + 1;

        return sprintf('PO-%s-%04d', $year, $count);
    }
}
