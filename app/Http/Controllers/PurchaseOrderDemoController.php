<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\PurchaseOrderDemo;
use App\Models\PurchaseOrderDemoItem;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderDemoController extends Controller
{
    public function index(): View
    {
        return view('purchase-order-demo.index', [
            'title' => 'Ting Hao | '.__('messages.purchase_order_demo'),
            'purchaseOrders' => PurchaseOrderDemo::query()
                ->latest()
                ->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('purchase-order-demo.create', [
            'title' => 'Ting Hao | '.__('messages.create_demo_po'),
            'items' => $this->demoItems(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'supplier_email' => ['nullable', 'email', 'max:255'],
            'expected_delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array'],
            'items.*.ingredient_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $purchaseOrder = DB::transaction(function () use ($data, $request): PurchaseOrderDemo {
            $items = collect($data['items'])->map(function (array $item): array {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $ingredientId = Ingredient::where('name', $item['ingredient_name'])->value('id');

                return [
                    'ingredient_id' => $ingredientId,
                    'ingredient_name' => $item['ingredient_name'],
                    'quantity' => $quantity,
                    'unit' => $item['unit'] ?? null,
                    'unit_price' => $unitPrice,
                    'line_total' => $quantity * $unitPrice,
                    'received_quantity' => 0,
                ];
            })->all();

            $purchaseOrder = PurchaseOrderDemo::create([
                'po_number' => $this->nextDemoPoNumber(),
                'supplier_name' => $data['supplier_name'],
                'supplier_email' => $data['supplier_email'] ?? null,
                'status' => PurchaseOrderDemo::STATUS_DRAFT,
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'subtotal' => collect($items)->sum('line_total'),
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $purchaseOrder->items()->createMany($items);

            return $purchaseOrder;
        });

        return redirect()->route('po-demo.show', $purchaseOrder);
    }

    public function show(PurchaseOrderDemo $po): View
    {
        return view('purchase-order-demo.show', [
            'title' => "Ting Hao | {$po->po_number}",
            'po' => $po->load(['items', 'creator']),
        ]);
    }

    public function sendEmailDemo(PurchaseOrderDemo $po): RedirectResponse
    {
        abort_unless($po->status === PurchaseOrderDemo::STATUS_DRAFT, 422);

        $po->update([
            'status' => PurchaseOrderDemo::STATUS_EMAIL_SENT,
            'email_sent_at' => now(),
        ]);

        return back()->with('status', __('messages.demo_email_sent_successfully'));
    }

    public function confirmDemo(PurchaseOrderDemo $po): RedirectResponse
    {
        abort_unless($po->status === PurchaseOrderDemo::STATUS_EMAIL_SENT, 422);

        $po->update([
            'status' => PurchaseOrderDemo::STATUS_SUPPLIER_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        return back()->with('status', __('messages.supplier_confirmation_recorded'));
    }

    public function receiveDemo(Request $request, PurchaseOrderDemo $po): RedirectResponse
    {
        abort_unless(in_array($po->status, [
            PurchaseOrderDemo::STATUS_SUPPLIER_CONFIRMED,
            PurchaseOrderDemo::STATUS_PARTIALLY_RECEIVED,
        ], true), 422);

        $mode = $request->input('mode', 'full');

        DB::transaction(function () use ($po, $mode, $request): void {
            $items = $po->items()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($mode === 'partial') {
                $item = $items->first(fn (PurchaseOrderDemoItem $item): bool => $this->remainingQuantity($item) > 0);

                if ($item) {
                    $quantityToReceive = min(
                        $this->remainingQuantity($item),
                        max(round((float) $item->quantity / 2, 2), 0.01)
                    );

                    $this->receiveItem($item, $quantityToReceive, $po, $request->user()->id);
                }

                $po->update([
                    'status' => $this->allItemsReceived($items->fresh()) ? PurchaseOrderDemo::STATUS_RECEIVED : PurchaseOrderDemo::STATUS_PARTIALLY_RECEIVED,
                    'received_at' => now(),
                ]);

                return;
            }

            foreach ($items as $item) {
                $quantityToReceive = $this->remainingQuantity($item);

                if ($quantityToReceive > 0) {
                    $this->receiveItem($item, $quantityToReceive, $po, $request->user()->id);
                }
            }

            $po->update([
                'status' => $this->allItemsReceived($items->fresh()) ? PurchaseOrderDemo::STATUS_RECEIVED : PurchaseOrderDemo::STATUS_PARTIALLY_RECEIVED,
                'received_at' => now(),
            ]);
        });

        return back()->with('status', __('messages.stock_received_demo_successfully'));
    }

    public function closeDemo(PurchaseOrderDemo $po): RedirectResponse
    {
        abort_unless($po->status === PurchaseOrderDemo::STATUS_RECEIVED, 422);

        $po->update([
            'status' => PurchaseOrderDemo::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return back()->with('status', __('messages.purchase_order_closed_successfully'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function demoItems(): array
    {
        return [
            ['ingredient_name' => 'Brown Sugar', 'quantity' => 15, 'unit' => 'kg', 'unit_price' => 3.50],
            ['ingredient_name' => 'Cake Flour', 'quantity' => 20, 'unit' => 'kg', 'unit_price' => 4.20],
            ['ingredient_name' => 'Instant Yeast', 'quantity' => 10, 'unit' => 'pack', 'unit_price' => 2.80],
        ];
    }

    private function nextDemoPoNumber(): string
    {
        $year = now()->format('Y');
        $count = PurchaseOrderDemo::where('po_number', 'like', "PO-DEMO-{$year}-%")->count() + 1;

        return sprintf('PO-DEMO-%s-%03d', $year, $count);
    }

    private function receiveItem(PurchaseOrderDemoItem $item, float $quantityToReceive, PurchaseOrderDemo $po, int $userId): void
    {
        $ingredient = $this->lockIngredientForItem($item);

        if ($ingredient) {
            $quantityBefore = (float) $ingredient->quantity;
            $quantityAfter = $quantityBefore + $quantityToReceive;

            StockMovement::create([
                'ingredient_id' => $ingredient->id,
                'type' => StockMovement::TYPE_IN,
                'quantity' => $quantityToReceive,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reason' => "Purchase Order Received: {$po->po_number}",
                'notes' => $item->ingredient_name,
                'created_by' => $userId,
            ]);

            $ingredient->update([
                'quantity' => $quantityAfter,
                'updated_by' => $userId,
            ]);

            if (! $item->ingredient_id) {
                $item->ingredient_id = $ingredient->id;
            }
        }

        $item->received_quantity = round((float) $item->received_quantity + $quantityToReceive, 2);
        $item->quality_status = 'accepted';
        $item->save();
    }

    private function lockIngredientForItem(PurchaseOrderDemoItem $item): ?Ingredient
    {
        if ($item->ingredient_id) {
            return Ingredient::query()
                ->whereKey($item->ingredient_id)
                ->lockForUpdate()
                ->first();
        }

        return Ingredient::query()
            ->where('name', $item->ingredient_name)
            ->lockForUpdate()
            ->first();
    }

    private function remainingQuantity(PurchaseOrderDemoItem $item): float
    {
        return max(0, round((float) $item->quantity - (float) $item->received_quantity, 2));
    }

    /**
     * @param  iterable<int, PurchaseOrderDemoItem>  $items
     */
    private function allItemsReceived(iterable $items): bool
    {
        foreach ($items as $item) {
            if ($this->remainingQuantity($item) > 0) {
                return false;
            }
        }

        return true;
    }
}
