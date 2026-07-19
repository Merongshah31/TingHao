<?php

namespace App\Http\Controllers;

use App\Mail\PurchaseOrderMail;
use App\Models\ApprovalRequest;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockAllocation;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Services\Agent\AgentWorkflowAuditService;
use App\Services\Agent\HumanApprovalGuardService;
use App\Services\Agent\ReasoningActivityService;
use App\Services\Agent\SupplierEmailDeliveryService;
use App\Services\Stock\StockPredictionApiClient;
use App\Services\Stock\StockPredictionInputBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('purchase-orders.index', [
            'title' => 'Ting Hao | '.__('messages.purchase_orders'),
            'purchaseOrders' => PurchaseOrder::query()
                ->with(['supplier', 'requestedBy:id,name', 'approvalRequest:id,purchase_order_id,status', 'agentRun:id,input_type'])
                ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('requested_by', $request->user()->id))
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

    public function suggestions(
        Request $request,
        StockPredictionInputBuilder $inputBuilder,
        StockPredictionApiClient $predictionClient,
    ): JsonResponse {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'order_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $supplier = isset($data['supplier_id']) ? Supplier::find($data['supplier_id']) : null;
        $ingredient = isset($data['ingredient_id']) ? Ingredient::find($data['ingredient_id']) : null;
        $quantitySuggestion = $ingredient
            ? $this->quantitySuggestion($ingredient, $inputBuilder, $predictionClient)
            : ['value' => null, 'source' => null];
        $priceSuggestion = $ingredient
            ? $this->unitPriceSuggestion($ingredient, $supplier)
            : ['value' => null, 'source' => null];
        $deliverySuggestion = ($supplier && isset($data['order_date']))
            ? $this->deliverySuggestion($supplier, $data['order_date'])
            : ['date' => null, 'lead_time_days' => null, 'source' => null];

        return response()->json([
            'suggested_quantity' => $quantitySuggestion['value'],
            'unit' => $ingredient?->unit,
            'suggested_unit_price' => $priceSuggestion['value'],
            'expected_delivery_date' => $deliverySuggestion['date'],
            'lead_time_days' => $deliverySuggestion['lead_time_days'],
            'source' => [
                'quantity' => $quantitySuggestion['source'],
                'price' => $priceSuggestion['source'],
                'delivery' => $deliverySuggestion['source'],
            ],
        ]);
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
                'requested_by' => $request->user()->id,
            ]);

            $purchaseOrder->items()->createMany($items);

            return $purchaseOrder;
        });

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('status', __('messages.purchase_order_created'));
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder, SupplierEmailDeliveryService $emailDelivery): View
    {
        abort_if(! $request->user()->isAdmin() && $purchaseOrder->requested_by !== $request->user()->id, 403);

        return view('purchase-orders.show', [
            'title' => "Ting Hao | {$purchaseOrder->po_number}",
            'purchaseOrder' => $purchaseOrder->load([
                'supplier',
                'creator',
                'requestedBy',
                'approvedBy',
                'agentRun.reasoningSteps.relatedToolCall',
                'approvalRequest.reviewedBy',
                'latestSupplierEmailDraft.approvedBy',
                'items.ingredient',
                'items.stockAllocations.stockLocation',
                'items.supplierReturns',
                'stockAllocations.stockLocation',
                'supplierReturns.ingredient',
            ]),
            'emailDelivery' => $emailDelivery->configuration(),
        ]);
    }

    public function receiveForm(Request $request, PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        $this->authorizePurchaseOrderAccess($request, $purchaseOrder);

        if (! $purchaseOrder->canReceiveStock()) {
            return redirect()
                ->route('purchase-orders.show', $purchaseOrder)
                ->withErrors(['receive' => __('messages.purchase_order_must_be_confirmed_before_receiving')]);
        }

        return view('purchase-orders.receive', [
            'title' => 'Ting Hao | '.__('messages.goods_receiving'),
            'purchaseOrder' => $purchaseOrder->load(['supplier', 'items.ingredient']),
            'stockLocations' => $this->receivingStockLocations(),
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
        abort_unless($purchaseOrder->status === PurchaseOrder::STATUS_DRAFT, 422);

        $purchaseOrder->load(['supplier', 'items.ingredient']);

        if (! $purchaseOrder->supplier?->email) {
            return back()->withErrors(['email' => __('messages.supplier_email_missing')]);
        }

        try {
            Mail::to($purchaseOrder->supplier->email)->send(new PurchaseOrderMail($purchaseOrder));
        } catch (Throwable) {
            // SMTP can be configured later; keep the workflow usable by recording the reviewed email step.
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_SENT,
            'sent_at' => now(),
            'email_to' => $purchaseOrder->supplier->email,
        ]);

        return back()->with('status', __('messages.purchase_order_email_sent'));
    }

    public function confirm(PurchaseOrder $purchaseOrder, AgentWorkflowAuditService $audit): RedirectResponse
    {
        abort_unless($purchaseOrder->canBeConfirmed(), 422);

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $audit->record(
            $purchaseOrder,
            'record_supplier_confirmation',
            ['purchase_order_id' => $purchaseOrder->id],
            ['status' => PurchaseOrder::STATUS_CONFIRMED, 'confirmed_at' => $purchaseOrder->confirmed_at?->toIso8601String()],
            'Supplier confirmation recorded',
            'Admin recorded supplier confirmation before goods receiving.'
        );

        return back()->with('status', __('messages.supplier_confirmation_recorded'));
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, AgentWorkflowAuditService $audit): RedirectResponse
    {
        $this->authorizePurchaseOrderAccess($request, $purchaseOrder);

        if (! $purchaseOrder->canReceiveStock()) {
            return back()->withErrors([
                'receive' => __('messages.purchase_order_must_be_confirmed_before_receiving'),
            ])->withInput();
        }

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.received_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.accepted_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.returned_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.shortage_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.quality_status' => ['nullable', Rule::in(PurchaseOrderItem::QUALITY_STATUSES)],
            'items.*.receiving_notes' => ['nullable', 'string', 'max:2000'],
            'items.*.allocations' => ['nullable', 'array'],
            'items.*.allocations.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($purchaseOrder, $data, $request): void {
                $items = $purchaseOrder->items()
                    ->with('ingredient')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $locations = $this->receivingStockLocations(lockForUpdate: true)->keyBy('id');

                $processedItems = 0;

                foreach ($data['items'] as $itemId => $receivingData) {
                    $item = $items->get((int) $itemId);

                    if (! $item) {
                        continue;
                    }

                    $breakdown = $this->receivingBreakdown($receivingData);

                    if ($this->emptyReceivingBreakdown($breakdown)) {
                        continue;
                    }

                    if ($breakdown['received'] > $this->remainingQuantity($item)) {
                        throw new \DomainException(__('messages.receiving_quantity_exceeds_remaining'));
                    }

                    $this->validateReceivingBreakdown($breakdown, $locations);
                    $this->receiveItem($item, $breakdown, $purchaseOrder, $request->user()->id, $locations);
                    $processedItems++;
                }

                if ($processedItems === 0) {
                    throw new \DomainException(__('messages.goods_receiving_requires_quantity'));
                }

                $freshItems = $purchaseOrder->items()->lockForUpdate()->get();

                $purchaseOrder->update([
                    'status' => $this->allItemsReceived($freshItems)
                        ? PurchaseOrder::STATUS_RECEIVED
                        : PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    'received_at' => now(),
                ]);
            });
        } catch (\DomainException $exception) {
            return back()->withErrors(['items' => $exception->getMessage()])->withInput($data);
        }

        Cache::forget(DashboardController::CACHE_KEY);

        $purchaseOrder->refresh()->load('items');
        $audit->record(
            $purchaseOrder,
            'record_goods_receiving',
            ['purchase_order_id' => $purchaseOrder->id, 'recorded_by' => $request->user()->id],
            [
                'status' => $purchaseOrder->status,
                'received_quantity' => round((float) $purchaseOrder->items->sum('received_quantity'), 2),
                'accepted_quantity' => round((float) $purchaseOrder->items->sum('accepted_quantity'), 2),
                'damaged_quantity' => round((float) $purchaseOrder->items->sum('damaged_quantity'), 2),
                'returned_quantity' => round((float) $purchaseOrder->items->sum('returned_quantity'), 2),
                'shortage_quantity' => round((float) $purchaseOrder->items->sum('shortage_quantity'), 2),
                'received_at' => $purchaseOrder->received_at?->toIso8601String(),
            ],
            'Goods receiving recorded',
            'Accepted stock and any damage, return, or shortage evidence were recorded after staff submitted receiving.'
        );

        return back()->with('status', __('messages.stock_received_successfully'));
    }

    public function close(PurchaseOrder $purchaseOrder, AgentWorkflowAuditService $audit): RedirectResponse
    {
        abort_unless($purchaseOrder->status === PurchaseOrder::STATUS_RECEIVED, 422);

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $audit->record(
            $purchaseOrder,
            'close_purchase_order_workflow',
            ['purchase_order_id' => $purchaseOrder->id],
            ['status' => PurchaseOrder::STATUS_CLOSED, 'closed_at' => $purchaseOrder->closed_at?->toIso8601String()],
            'Procurement workflow completed',
            'Admin closed the purchase order after goods receiving verification.'
        );

        return back()->with('status', __('messages.purchase_order_closed_successfully'));
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, HumanApprovalGuardService $guard, ReasoningActivityService $reasoningActivity, AgentWorkflowAuditService $audit): RedirectResponse
    {
        $guard->assertAdminCanApprove($request->user(), HumanApprovalGuardService::ACTION_PURCHASE_ORDER_APPROVAL);
        abort_unless($purchaseOrder->status === PurchaseOrder::STATUS_PENDING_APPROVAL, 422);

        DB::transaction(function () use ($purchaseOrder, $request): void {
            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
            ]);

            $purchaseOrder->approvalRequest?->update([
                'status' => ApprovalRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'review_notes' => null,
            ]);
        });
        if ($purchaseOrder->agentRun) {
            $reasoningActivity->humanCheckpoint($purchaseOrder->agentRun, 'Purchase order approved by admin', 'Human approval was completed by '.$request->user()->name.'. The agent did not approve this purchase order autonomously.', [
                'purchase_order_id' => $purchaseOrder->id,
                'status' => PurchaseOrder::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
            ]);
        }

        $audit->record(
            $purchaseOrder,
            'approve_purchase_order',
            ['purchase_order_id' => $purchaseOrder->id],
            ['status' => PurchaseOrder::STATUS_APPROVED, 'approved_by' => $request->user()->id],
            'Purchase order approval recorded',
            'Admin approved the purchase order at the mandatory human checkpoint.'
        );

        return back()->with('status', 'Purchase order approved. The supplier email draft remains an explicit admin action.');
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder, HumanApprovalGuardService $guard, ReasoningActivityService $reasoningActivity, AgentWorkflowAuditService $audit): RedirectResponse
    {
        $guard->assertAdminCanApprove($request->user(), HumanApprovalGuardService::ACTION_PURCHASE_ORDER_APPROVAL);
        abort_unless($purchaseOrder->status === PurchaseOrder::STATUS_PENDING_APPROVAL, 422);

        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($purchaseOrder, $request, $data): void {
            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_REJECTED,
                'approved_by' => null,
            ]);

            $purchaseOrder->approvalRequest?->update([
                'status' => ApprovalRequest::STATUS_REJECTED,
                'reviewed_by' => $request->user()->id,
                'review_notes' => $data['review_notes'] ?? null,
            ]);
        });
        if ($purchaseOrder->agentRun) {
            $reasoningActivity->humanCheckpoint($purchaseOrder->agentRun, 'Purchase order rejected by admin', 'Human reviewer rejected the purchase order draft. The agent cannot override this decision.', [
                'purchase_order_id' => $purchaseOrder->id,
                'status' => PurchaseOrder::STATUS_REJECTED,
                'reviewed_by' => $request->user()->id,
                'review_notes' => $data['review_notes'] ?? null,
            ]);
        }

        $audit->record(
            $purchaseOrder,
            'reject_purchase_order',
            ['purchase_order_id' => $purchaseOrder->id],
            [
                'status' => PurchaseOrder::STATUS_REJECTED,
                'reviewed_by' => $request->user()->id,
                'review_notes' => $data['review_notes'] ?? null,
            ],
            'Purchase order rejection recorded',
            'Admin rejected the purchase order at the mandatory human checkpoint.'
        );

        return back()->with('status', 'Purchase order rejected.');
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
     * @return array{value: float, source: string}
     */
    private function quantitySuggestion(
        Ingredient $ingredient,
        StockPredictionInputBuilder $inputBuilder,
        StockPredictionApiClient $predictionClient,
    ): array {
        $cached = Cache::get($inputBuilder->cacheKey($ingredient));
        $suggestedQuantity = null;

        if (is_array($cached)) {
            $prediction = $predictionClient->applyBusinessRules($cached, $inputBuilder->businessFacts($ingredient));
            $cachedQuantity = $prediction['suggested_quantity'] ?? null;

            if (is_numeric($cachedQuantity) && is_finite((float) $cachedQuantity) && (float) $cachedQuantity > 0) {
                $suggestedQuantity = (float) $cachedQuantity;
            }
        }

        if ($suggestedQuantity !== null) {
            return ['value' => round($suggestedQuantity, 2), 'source' => 'stock_planner_prediction'];
        }

        $minimumStock = max(0, (float) $ingredient->minimum_stock);
        $currentQuantity = max(0, (float) $ingredient->quantity);

        return [
            'value' => round(max(($minimumStock * 2) - $currentQuantity, $minimumStock, 1), 2),
            'source' => 'stock_level_fallback',
        ];
    }

    /**
     * @return array{value: float|null, source: string|null}
     */
    private function unitPriceSuggestion(Ingredient $ingredient, ?Supplier $supplier): array
    {
        $prices = PurchaseOrderItem::query()
            ->where('ingredient_id', $ingredient->id)
            ->where('unit_price', '>', 0)
            ->whereHas('purchaseOrder', fn ($query) => $query->whereNotIn('status', [
                PurchaseOrder::STATUS_REJECTED,
                PurchaseOrder::STATUS_CANCELLED,
            ]));

        if ($supplier) {
            $supplierPrice = (clone $prices)
                ->whereHas('purchaseOrder', fn ($query) => $query->where('supplier_id', $supplier->id))
                ->latest('id')
                ->value('unit_price');

            if (is_numeric($supplierPrice) && (float) $supplierPrice > 0) {
                return ['value' => round((float) $supplierPrice, 2), 'source' => 'latest_supplier_po'];
            }
        }

        $latestPrice = (clone $prices)->latest('id')->value('unit_price');

        if (is_numeric($latestPrice) && (float) $latestPrice > 0) {
            return ['value' => round((float) $latestPrice, 2), 'source' => 'latest_ingredient_po'];
        }

        if (is_numeric($ingredient->cost_price) && (float) $ingredient->cost_price > 0) {
            return ['value' => round((float) $ingredient->cost_price, 2), 'source' => 'ingredient_cost_price'];
        }

        return ['value' => null, 'source' => null];
    }

    /**
     * @return array{date: string, lead_time_days: int, source: string}
     */
    private function deliverySuggestion(Supplier $supplier, string $orderDate): array
    {
        $historicalLeadTimes = PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('status', [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED])
            ->whereNotNull('order_date')
            ->where(fn ($query) => $query->whereNotNull('received_at')->orWhereNotNull('closed_at'))
            ->get(['order_date', 'received_at', 'closed_at'])
            ->map(function (PurchaseOrder $purchaseOrder): ?int {
                $completedAt = $purchaseOrder->received_at ?? $purchaseOrder->closed_at;

                if (! $completedAt || ! $purchaseOrder->order_date) {
                    return null;
                }

                $days = $purchaseOrder->order_date->startOfDay()->diffInDays($completedAt->startOfDay(), false);

                return $days >= 0 ? (int) $days : null;
            })
            ->filter(fn (?int $days): bool => $days !== null)
            ->values();

        if ($historicalLeadTimes->isNotEmpty()) {
            $leadTimeDays = max(0, (int) round((float) $historicalLeadTimes->average()));
            $source = 'supplier_po_history';
        } else {
            $defaultLeadTime = $this->supplierDefaultLeadTime($supplier);
            $leadTimeDays = $defaultLeadTime ?? 2;
            $source = $defaultLeadTime !== null
                ? 'supplier_default_lead_time'
                : 'two_day_fallback';
        }

        return [
            'date' => CarbonImmutable::createFromFormat('Y-m-d', $orderDate)->addDays($leadTimeDays)->format('Y-m-d'),
            'lead_time_days' => $leadTimeDays,
            'source' => $source,
        ];
    }

    private function supplierDefaultLeadTime(Supplier $supplier): ?int
    {
        foreach (['default_lead_time_days', 'lead_time_days'] as $attribute) {
            $value = $supplier->getAttributes()[$attribute] ?? null;

            if (is_numeric($value) && (int) $value >= 0) {
                return (int) $value;
            }
        }

        return null;
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

    private function authorizePurchaseOrderAccess(Request $request, PurchaseOrder $purchaseOrder): void
    {
        abort_if(! $request->user()->isAdmin() && $purchaseOrder->requested_by !== $request->user()->id, 403);
    }

    /**
     * @return Collection<int, StockLocation>
     */
    private function receivingStockLocations(bool $lockForUpdate = false)
    {
        foreach ($this->defaultStockLocations() as $location) {
            StockLocation::updateOrCreate(
                ['name' => $location['name']],
                [
                    'type' => $location['type'],
                    'notes' => $location['notes'],
                    'is_active' => true,
                ]
            );
        }

        return StockLocation::query()
            ->where('is_active', true)
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->orderByRaw("CASE type WHEN 'storage' THEN 1 WHEN 'production' THEN 2 WHEN 'front' THEN 3 WHEN 'quarantine' THEN 4 ELSE 5 END")
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, array{name: string, type: string, notes: string}>
     */
    private function defaultStockLocations(): array
    {
        return [
            ['name' => 'Store Room', 'type' => StockLocation::TYPE_STORAGE, 'notes' => 'Main usable stock storage.'],
            ['name' => 'Production Area', 'type' => StockLocation::TYPE_PRODUCTION, 'notes' => 'Stock released to bakery production.'],
            ['name' => 'Front Counter', 'type' => StockLocation::TYPE_FRONT, 'notes' => 'Stock held near the sales counter.'],
            ['name' => 'Quarantine / Damaged', 'type' => StockLocation::TYPE_QUARANTINE, 'notes' => 'Damaged or rejected stock waiting for supplier return.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{received: float, accepted: float, damaged: float, returned: float, shortage: float, quality_status: string|null, notes: string|null, allocations: array<int, float>}
     */
    private function receivingBreakdown(array $data): array
    {
        return [
            'received' => round((float) ($data['received_quantity'] ?? 0), 2),
            'accepted' => round((float) ($data['accepted_quantity'] ?? 0), 2),
            'damaged' => round((float) ($data['damaged_quantity'] ?? 0), 2),
            'returned' => round((float) ($data['returned_quantity'] ?? 0), 2),
            'shortage' => round((float) ($data['shortage_quantity'] ?? 0), 2),
            'quality_status' => $data['quality_status'] ?? null,
            'notes' => $data['receiving_notes'] ?? null,
            'allocations' => collect($data['allocations'] ?? [])
                ->mapWithKeys(fn ($quantity, $locationId): array => [(int) $locationId => round((float) $quantity, 2)])
                ->filter(fn (float $quantity): bool => $quantity > 0)
                ->all(),
        ];
    }

    /**
     * @param  array{received: float, accepted: float, damaged: float, returned: float, shortage: float, allocations: array<int, float>}  $breakdown
     */
    private function emptyReceivingBreakdown(array $breakdown): bool
    {
        return $breakdown['received'] <= 0
            && $breakdown['accepted'] <= 0
            && $breakdown['damaged'] <= 0
            && $breakdown['returned'] <= 0
            && $breakdown['shortage'] <= 0
            && array_sum($breakdown['allocations']) <= 0;
    }

    /**
     * @param  array{received: float, accepted: float, damaged: float, returned: float, shortage: float, allocations: array<int, float>}  $breakdown
     * @param  Collection<int, StockLocation>  $locations
     */
    private function validateReceivingBreakdown(array $breakdown, $locations): void
    {
        $accountedQuantity = round($breakdown['accepted'] + $breakdown['damaged'] + $breakdown['shortage'], 2);

        if (round($breakdown['received'], 2) !== $accountedQuantity) {
            throw new \DomainException(__('messages.quantity_mismatch_error'));
        }

        $usableAllocation = collect($breakdown['allocations'])
            ->sum(function (float $quantity, int $locationId) use ($locations): float {
                $location = $locations->get($locationId);

                if (! $location instanceof StockLocation) {
                    throw new \DomainException(__('messages.stock_allocation_location_error'));
                }

                return $location->isQuarantine() ? 0 : $quantity;
            });

        if (round((float) $usableAllocation, 2) !== round($breakdown['accepted'], 2)) {
            throw new \DomainException(__('messages.accepted_allocation_mismatch_error'));
        }

        $quarantineAllocation = collect($breakdown['allocations'])
            ->sum(function (float $quantity, int $locationId) use ($locations): float {
                $location = $locations->get($locationId);

                return $location?->isQuarantine() ? $quantity : 0;
            });

        if (round((float) $breakdown['returned'], 2) > round((float) $breakdown['damaged'], 2)
            || round((float) $quarantineAllocation, 2) > round((float) $breakdown['damaged'], 2)) {
            throw new \DomainException(__('messages.damaged_return_mismatch_error'));
        }
    }

    /**
     * @param  array{received: float, accepted: float, damaged: float, returned: float, shortage: float, quality_status: string|null, notes: string|null, allocations: array<int, float>}  $breakdown
     * @param  Collection<int, StockLocation>  $locations
     */
    private function receiveItem(PurchaseOrderItem $item, array $breakdown, PurchaseOrder $purchaseOrder, int $userId, $locations): void
    {
        $ingredient = Ingredient::query()
            ->whereKey($item->ingredient_id)
            ->lockForUpdate()
            ->firstOrFail();

        $quantityBefore = (float) $ingredient->quantity;
        $quantityAfter = $quantityBefore + $breakdown['accepted'];

        if ($breakdown['accepted'] > 0) {
            StockMovement::create([
                'ingredient_id' => $ingredient->id,
                'type' => StockMovement::TYPE_IN,
                'quantity' => $breakdown['accepted'],
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reason' => "PO Received Accepted Stock: {$purchaseOrder->po_number}",
                'notes' => $breakdown['notes'] ?: ($item->description ?: $ingredient->name),
                'created_by' => $userId,
            ]);

            $ingredient->update([
                'quantity' => $quantityAfter,
                'updated_by' => $userId,
            ]);
        }

        $item->update([
            'received_quantity' => round((float) $item->received_quantity + $breakdown['received'], 2),
            'accepted_quantity' => round((float) $item->accepted_quantity + $breakdown['accepted'], 2),
            'damaged_quantity' => round((float) $item->damaged_quantity + $breakdown['damaged'], 2),
            'returned_quantity' => round((float) $item->returned_quantity + $breakdown['returned'], 2),
            'shortage_quantity' => round((float) $item->shortage_quantity + $breakdown['shortage'], 2),
            'quality_status' => $breakdown['quality_status'] ?: $this->inferQualityStatus($breakdown),
            'receiving_notes' => $breakdown['notes'],
        ]);

        foreach ($breakdown['allocations'] as $locationId => $quantity) {
            $location = $locations->get($locationId);

            StockAllocation::create([
                'ingredient_id' => $ingredient->id,
                'stock_location_id' => $location->id,
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_item_id' => $item->id,
                'quantity' => $quantity,
                'movement_type' => StockMovement::TYPE_IN,
                'notes' => $breakdown['notes'],
                'created_by' => $userId,
            ]);
        }

        if ($breakdown['damaged'] > 0 || $breakdown['returned'] > 0) {
            SupplierReturn::create([
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_item_id' => $item->id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'ingredient_id' => $ingredient->id,
                'return_number' => $this->nextSupplierReturnNumber(),
                'damaged_quantity' => $breakdown['damaged'],
                'returned_quantity' => $breakdown['returned'],
                'reason' => $breakdown['notes'] ?: __('messages.damaged_stock'),
                'status' => SupplierReturn::STATUS_PENDING,
                'created_by' => $userId,
            ]);
        }
    }

    /**
     * @param  array{accepted: float, damaged: float, returned: float, shortage: float}  $breakdown
     */
    private function inferQualityStatus(array $breakdown): string
    {
        if ($breakdown['returned'] > 0) {
            return PurchaseOrderItem::QUALITY_RETURNED;
        }

        if ($breakdown['damaged'] > 0 && $breakdown['accepted'] <= 0) {
            return PurchaseOrderItem::QUALITY_DAMAGED;
        }

        if ($breakdown['shortage'] > 0 && $breakdown['accepted'] <= 0) {
            return PurchaseOrderItem::QUALITY_SHORTAGE;
        }

        if ($breakdown['damaged'] > 0 || $breakdown['shortage'] > 0) {
            return PurchaseOrderItem::QUALITY_PARTIALLY_ACCEPTED;
        }

        return PurchaseOrderItem::QUALITY_ACCEPTED;
    }

    private function nextSupplierReturnNumber(): string
    {
        $year = now()->format('Y');
        $next = SupplierReturn::query()
            ->where('return_number', 'like', "SR-{$year}-%")
            ->count() + 1;

        do {
            $returnNumber = sprintf('SR-%s-%04d', $year, $next++);
        } while (SupplierReturn::where('return_number', $returnNumber)->exists());

        return $returnNumber;
    }

    private function remainingQuantity(PurchaseOrderItem $item): float
    {
        return max(0, round((float) $item->quantity - (float) $item->received_quantity, 2));
    }

    /**
     * @param  iterable<int, PurchaseOrderItem>  $items
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
