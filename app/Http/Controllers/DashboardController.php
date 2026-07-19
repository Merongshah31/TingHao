<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\ExpiryLossRecommendation;
use App\Models\ApprovalRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RestockRequest;
use App\Models\StockMovement;
use App\Models\SupplierEmailDraft;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Services\Stock\StockPredictionInputBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public const CACHE_KEY = 'dashboard.summary.v3';

    public function __construct(
        private readonly StockPredictionInputBuilder $predictionInputBuilder,
    ) {
    }

    public function redirect(Request $request): RedirectResponse
    {
        if ($request->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('staff.dashboard');
    }

    public function admin(): View
    {
        return view('dashboard', [
            'title' => 'Ting Hao | '.__('messages.admin_dashboard'),
            'dashboardRole' => __('messages.admin'),
            'dashboardIntro' => __('messages.full_system_control'),
            'dashboardItems' => [
                'Create and manage user accounts',
                'Manage ingredient records',
                'Monitor stock movement and reports',
                'Configure system settings',
            ],
            'metrics' => $this->metrics($data = $this->dashboardData()),
            'analytics' => $this->analytics($data),
            'autopilotActions' => $this->autopilotActions(),
            'stockPredictionSignals' => $this->stockPredictionSignals(),
        ]);
    }

    public function staff(): View
    {
        return view('dashboard', [
            'title' => 'Ting Hao | '.__('messages.staff_dashboard'),
            'dashboardRole' => __('messages.staff'),
            'dashboardIntro' => __('messages.welcome_staff'),
            'dashboardItems' => [
                'View inventory and add ingredients',
                'Record stock in and stock out',
                'Check low-stock and expiry alerts',
                'View supplier details and inventory reports',
            ],
            'metrics' => $this->metrics($data = $this->dashboardData()),
            'analytics' => $this->analytics($data),
            'autopilotActions' => $this->autopilotActions(),
            'stockPredictionSignals' => $this->stockPredictionSignals(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardData(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function (): array {
            $today = now()->toDateString();
            $expiringUntil = now()->addDays(30)->toDateString();

            $ingredientStats = Ingredient::query()
                ->selectRaw('COUNT(*) as total_count')
                ->selectRaw('SUM(CASE WHEN quantity <= minimum_stock THEN 1 ELSE 0 END) as low_stock_count')
                ->selectRaw('SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < ? THEN 1 ELSE 0 END) as expired_count', [$today])
                ->selectRaw('SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date >= ? AND expiry_date <= ? THEN 1 ELSE 0 END) as expiring_count', [$today, $expiringUntil])
                ->selectRaw('COALESCE(SUM(quantity * COALESCE(cost_price, 0)), 0) as inventory_value')
                ->first();

            $movementStats = StockMovement::query()
                ->selectRaw('COUNT(*) as total_count')
                ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN quantity ELSE 0 END), 0) as stock_in', [StockMovement::TYPE_IN])
                ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN quantity ELSE 0 END), 0) as stock_out', [StockMovement::TYPE_OUT])
                ->first();

            return [
                'ingredientCount' => (int) ($ingredientStats->total_count ?? 0),
                'lowStockCount' => (int) ($ingredientStats->low_stock_count ?? 0),
                'expiredCount' => (int) ($ingredientStats->expired_count ?? 0),
                'expiringCount' => (int) ($ingredientStats->expiring_count ?? 0),
                'inventoryValue' => (float) ($ingredientStats->inventory_value ?? 0),
                'supplierCount' => Supplier::count(),
                'movementCount' => (int) ($movementStats->total_count ?? 0),
                'stockIn' => (float) ($movementStats->stock_in ?? 0),
                'stockOut' => (float) ($movementStats->stock_out ?? 0),
                'openRestockCount' => RestockRequest::query()
                    ->where('status', '!=', RestockRequest::STATUS_COMPLETED)
                    ->count(),
                'pendingAgentApprovalCount' => ApprovalRequest::query()
                    ->where('status', ApprovalRequest::STATUS_PENDING)
                    ->count(),
                'pendingSupplierEmailDraftCount' => SupplierEmailDraft::query()
                    ->where('status', SupplierEmailDraft::STATUS_DRAFT)
                    ->count(),
                'openSupplierReturnCount' => SupplierReturn::query()
                    ->whereIn('status', SupplierReturn::OPEN_STATUSES)
                    ->count(),
                'purchaseOrderShortageCount' => PurchaseOrder::query()
                    ->whereHas('items', fn ($query) => $query->where('shortage_quantity', '>', 0))
                    ->count(),
                'purchaseOrderDamagedCount' => PurchaseOrder::query()
                    ->whereHas('items', fn ($query) => $query->where('damaged_quantity', '>', 0))
                    ->count(),
                'receivingDiscrepancyCount' => PurchaseOrderItem::query()
                    ->whereRaw('ROUND(received_quantity, 2) != ROUND(accepted_quantity + damaged_quantity + shortage_quantity, 2)')
                    ->count(),
                'expiryLossImpact' => $this->expiryLossImpact(),
                'lowStockItems' => $this->lowStockItems(),
                'recentMovements' => StockMovement::query()
                    ->select(['id', 'ingredient_id', 'type', 'quantity', 'created_by', 'created_at'])
                    ->with([
                        'ingredient:id,name',
                        'creator:id,name',
                    ])
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(fn (StockMovement $movement): array => [
                        'id' => $movement->id,
                        'type' => $movement->type,
                        'quantity' => (float) $movement->quantity,
                        'ingredient_name' => $movement->ingredient?->name,
                        'creator_name' => $movement->creator?->name,
                        'created_at' => $movement->created_at?->format('d M, H:i'),
                    ])
                    ->values()
                    ->all(),
            ];
        });
    }

    /**
     * @return array<int, array{title: string, summary: string, status: string, url: string, action: string}>
     */
    private function autopilotActions(): array
    {
        $actions = [];
        $user = auth()->user();

        $lowStockIngredient = Ingredient::query()
            ->select(['id', 'name', 'unit', 'quantity', 'minimum_stock'])
            ->lowStock()
            ->orderByRaw('(quantity - minimum_stock) asc')
            ->first();

        if ($lowStockIngredient) {
            $shortage = max(0, (float) $lowStockIngredient->minimum_stock - (float) $lowStockIngredient->quantity);
            $actions[] = [
                'title' => __('messages.autopilot_low_stock_title'),
                'summary' => __('messages.autopilot_low_stock_summary', [
                    'ingredient' => $lowStockIngredient->name,
                    'quantity' => number_format($shortage, 2),
                    'unit' => $lowStockIngredient->unit,
                ]),
                'status' => __('messages.needs_attention'),
                'url' => route('stock-planner.index', ['view' => 'cards']),
                'action' => __('messages.review'),
            ];
        }

        $pendingApproval = PurchaseOrder::query()
            ->select(['id', 'po_number', 'supplier_id', 'agent_run_id', 'status'])
            ->with(['supplier:id,name', 'agentRun:id,input_type', 'items:id,purchase_order_id,ingredient_id,description', 'items.ingredient:id,name'])
            ->where('status', PurchaseOrder::STATUS_PENDING_APPROVAL)
            ->when($user && ! $user->isAdmin(), fn ($query) => $query->where('requested_by', $user->id))
            ->latest()
            ->first();

        if ($pendingApproval) {
            $stockPredictionItem = $pendingApproval->items
                ->map(fn ($item): ?string => $item->ingredient?->name ?? $item->description)
                ->filter()
                ->first();
            $createdFromStockPrediction = $pendingApproval->agentRun?->input_type === 'stock_prediction_restock';

            $actions[] = [
                'title' => $createdFromStockPrediction && $stockPredictionItem
                    ? "{$stockPredictionItem} restock plan waiting approval"
                    : __('messages.autopilot_po_approval_title'),
                'summary' => $createdFromStockPrediction
                    ? 'PO draft created from stock prediction for '.$pendingApproval->po_number.'.'
                    : __('messages.autopilot_po_approval_summary', [
                        'po' => $pendingApproval->po_number,
                        'supplier' => $pendingApproval->supplier?->name ?? __('messages.not_set'),
                    ]),
                'status' => __('messages.pending_approval'),
                'url' => route('purchase-orders.show', $pendingApproval),
                'action' => $user?->isAdmin() ? __('messages.approve') : __('messages.review'),
            ];
        }

        $approvedPurchaseOrderNeedingDraft = PurchaseOrder::query()
            ->select(['id', 'po_number', 'supplier_id', 'status'])
            ->with('supplier:id,name')
            ->where('status', PurchaseOrder::STATUS_APPROVED)
            ->when($user && ! $user->isAdmin(), fn ($query) => $query->where('requested_by', $user->id))
            ->whereDoesntHave('supplierEmailDrafts')
            ->latest()
            ->first();

        if ($approvedPurchaseOrderNeedingDraft) {
            $actions[] = [
                'title' => 'Approved PO needs supplier email draft',
                'summary' => $approvedPurchaseOrderNeedingDraft->po_number.' is approved for '.($approvedPurchaseOrderNeedingDraft->supplier?->name ?? __('messages.not_set')).'. Generate a Qwen draft for admin review.',
                'status' => __('messages.needs_email_draft'),
                'url' => route('purchase-orders.show', $approvedPurchaseOrderNeedingDraft),
                'action' => __('messages.review'),
            ];
        }

        $emailDraft = SupplierEmailDraft::query()
            ->select(['id', 'purchase_order_id', 'supplier_id', 'subject', 'status'])
            ->with(['supplier:id,name', 'purchaseOrder:id,po_number'])
            ->where('status', SupplierEmailDraft::STATUS_DRAFT)
            ->when($user && ! $user->isAdmin(), fn ($query) => $query->whereHas('purchaseOrder', fn ($purchaseOrderQuery) => $purchaseOrderQuery->where('requested_by', $user->id)))
            ->latest()
            ->first();

        if ($emailDraft) {
            $actions[] = [
                'title' => __('messages.autopilot_email_draft_title'),
                'summary' => __('messages.autopilot_email_draft_summary', [
                    'supplier' => $emailDraft->supplier?->name ?? __('messages.not_set'),
                ]),
                'status' => __('messages.draft'),
                'url' => route('supplier-email-drafts.show', $emailDraft),
                'action' => __('messages.review'),
            ];
        }

        $approvedEmailDraft = SupplierEmailDraft::query()
            ->select(['id', 'purchase_order_id', 'supplier_id', 'subject', 'status'])
            ->with(['supplier:id,name', 'purchaseOrder:id,po_number'])
            ->where('status', SupplierEmailDraft::STATUS_APPROVED)
            ->when($user && ! $user->isAdmin(), fn ($query) => $query->whereHas('purchaseOrder', fn ($purchaseOrderQuery) => $purchaseOrderQuery->where('requested_by', $user->id)))
            ->latest()
            ->first();

        if ($approvedEmailDraft) {
            $actions[] = [
                'title' => 'Supplier email ready to mark as sent',
                'summary' => ($approvedEmailDraft->purchaseOrder?->po_number ?? 'Approved PO').' email draft is approved. Admin can mark it sent after manual supplier contact.',
                'status' => __('messages.approved'),
                'url' => route('supplier-email-drafts.show', $approvedEmailDraft),
                'action' => __('messages.review'),
            ];
        }

        $expiryRecommendation = ExpiryLossRecommendation::query()
            ->select(['id', 'ingredient_id', 'potential_loss', 'days_until_expiry', 'status'])
            ->with('ingredient:id,name')
            ->whereIn('status', ExpiryLossRecommendation::OPEN_STATUSES)
            ->orderByDesc('potential_loss')
            ->first();

        if ($expiryRecommendation) {
            $actions[] = [
                'title' => __('messages.autopilot_expiry_risk_title'),
                'summary' => __('messages.autopilot_expiry_risk_summary', [
                    'ingredient' => $expiryRecommendation->ingredient?->name ?? __('messages.deleted_ingredient'),
                    'loss' => number_format((float) $expiryRecommendation->potential_loss, 2),
                ]),
                'status' => __('messages.needs_attention'),
                'url' => route('expiry-loss-recommendations.show', $expiryRecommendation),
                'action' => __('messages.view'),
            ];
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, string|int>>
     */
    private function metrics(array $data): array
    {
        return [
            [
                'label' => __('messages.total_items'),
                'value' => $data['ingredientCount'],
                'hint' => __('messages.total_active_records'),
                'icon' => 'package-2',
                'tone' => 'green',
            ],
            [
                'label' => __('messages.low_stock'),
                'value' => $data['lowStockCount'],
                'hint' => __('messages.needs_attention'),
                'icon' => 'triangle-alert',
                'tone' => 'amber',
            ],
            [
                'label' => __('messages.expiring'),
                'value' => $data['expiringCount'],
                'hint' => __('messages.within_30_days'),
                'icon' => 'calendar-clock',
                'tone' => 'red',
            ],
            [
                'label' => __('messages.suppliers'),
                'value' => $data['supplierCount'],
                'hint' => __('messages.approved_partners'),
                'icon' => 'truck',
                'tone' => 'blue',
            ],
            [
                'label' => __('messages.movements'),
                'value' => $data['movementCount'],
                'hint' => __('messages.stock_ledger_entries'),
                'icon' => 'arrow-left-right',
                'tone' => 'violet',
            ],
            [
                'label' => __('messages.records'),
                'value' => $data['ingredientCount'] + $data['supplierCount'] + $data['movementCount'],
                'hint' => __('messages.records_count', [
                    'count' => $data['openRestockCount'],
                ]),
                'icon' => 'database',
                'tone' => 'slate',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function analytics(array $data): array
    {
        $totalIngredients = max((int) $data['ingredientCount'], 1);
        $attentionCount = (int) $data['lowStockCount'] + (int) $data['expiredCount'] + (int) $data['expiringCount'];
        $healthyCount = max(0, (int) $data['ingredientCount'] - (int) $data['lowStockCount']);
        $stockIn = (float) $data['stockIn'];
        $stockOut = (float) $data['stockOut'];
        $movementTotal = max((float) $stockIn + (float) $stockOut, 1);

        return [
            'inventoryValue' => (float) $data['inventoryValue'],
            'stockHealthPercent' => round(($healthyCount / $totalIngredients) * 100),
            'attentionPercent' => round(($attentionCount / $totalIngredients) * 100),
            'stockInPercent' => round(((float) $stockIn / $movementTotal) * 100),
            'stockOutPercent' => round(((float) $stockOut / $movementTotal) * 100),
            'stockIn' => $stockIn,
            'stockOut' => $stockOut,
            'expiryLossImpact' => $data['expiryLossImpact'],
            'pendingAgentApprovalCount' => (int) $data['pendingAgentApprovalCount'],
            'pendingSupplierEmailDraftCount' => (int) $data['pendingSupplierEmailDraftCount'],
            'openSupplierReturnCount' => (int) $data['openSupplierReturnCount'],
            'purchaseOrderShortageCount' => (int) $data['purchaseOrderShortageCount'],
            'purchaseOrderDamagedCount' => (int) $data['purchaseOrderDamagedCount'],
            'receivingDiscrepancyCount' => (int) $data['receivingDiscrepancyCount'],
            'lowStockItems' => $data['lowStockItems'],
            'recentMovements' => $data['recentMovements'],
        ];
    }

    /**
     * @return array<int, array<string, string|float|int>>
     */
    private function lowStockItems(): array
    {
        return Ingredient::query()
            ->select(['id', 'name', 'unit', 'quantity', 'minimum_stock'])
            ->lowStock()
            ->orderByRaw('(quantity - minimum_stock) asc')
            ->take(4)
            ->get()
            ->map(fn (Ingredient $ingredient): array => [
                'name' => $ingredient->name,
                'id' => $ingredient->id,
                'quantity' => (float) $ingredient->quantity,
                'minimum' => (float) $ingredient->minimum_stock,
                'percent' => (float) $ingredient->minimum_stock > 0
                    ? min(100, round(((float) $ingredient->quantity / (float) $ingredient->minimum_stock) * 100))
                    : 0,
                'shortage' => max(0, (float) $ingredient->minimum_stock - (float) $ingredient->quantity),
                'unit' => $ingredient->unit,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{total_potential_loss: float, at_risk_count: int, open_count: int, highest_risk_name: string|null, highest_risk_loss: float|null}
     */
    private function expiryLossImpact(): array
    {
        $openRecommendations = ExpiryLossRecommendation::query()
            ->select(['id', 'ingredient_id', 'potential_loss'])
            ->with('ingredient:id,name')
            ->whereIn('status', ExpiryLossRecommendation::OPEN_STATUSES)
            ->get();

        $highestRisk = $openRecommendations
            ->sortByDesc(fn (ExpiryLossRecommendation $recommendation): float => (float) ($recommendation->potential_loss ?? 0))
            ->first();

        return [
            'total_potential_loss' => (float) $openRecommendations->sum(fn (ExpiryLossRecommendation $recommendation): float => (float) ($recommendation->potential_loss ?? 0)),
            'at_risk_count' => $openRecommendations->pluck('ingredient_id')->unique()->count(),
            'open_count' => $openRecommendations->count(),
            'highest_risk_name' => $highestRisk?->ingredient?->name,
            'highest_risk_loss' => $highestRisk?->potential_loss !== null ? (float) $highestRisk->potential_loss : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stockPredictionSignals(): array
    {
        $importantActions = [
            'add_stock_now',
            'add_stock_soon',
            'do_not_buy',
            'use_before_expiry',
        ];

        return Ingredient::query()
            ->select(['id', 'name', 'unit', 'quantity', 'minimum_stock', 'expiry_date'])
            ->orderByRaw('CASE WHEN quantity <= minimum_stock THEN 0 ELSE 1 END')
            ->orderBy('expiry_date')
            ->orderBy('name')
            ->take(8)
            ->get()
            ->map(function (Ingredient $ingredient): array {
                $prediction = Cache::get($this->predictionInputBuilder->cacheKey($ingredient), []);

                return [
                    'ingredient' => $ingredient,
                    'prediction' => $prediction,
                    'business_summary' => $this->stockPredictionBusinessSummary($ingredient, $prediction),
                ];
            })
            ->filter(fn (array $signal): bool => ($signal['prediction']['available'] ?? false)
                && in_array($signal['prediction']['recommended_action'] ?? null, $importantActions, true))
            ->take(3)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $prediction
     */
    private function stockPredictionBusinessSummary(Ingredient $ingredient, array $prediction): string
    {
        $action = $prediction['recommended_action'] ?? 'monitor';

        if (in_array($action, ['add_stock_now', 'add_stock_soon'], true)) {
            $suggestedQuantity = is_numeric($prediction['suggested_quantity'] ?? null)
                ? (float) $prediction['suggested_quantity']
                : 0.0;

            if ($suggestedQuantity <= 0) {
                $suggestedQuantity = max(
                    (float) $ingredient->minimum_stock - (float) $ingredient->quantity,
                    (float) $ingredient->minimum_stock,
                    1.0,
                );
            }

            return 'Suggested: '.number_format($suggestedQuantity, 2).' '.$ingredient->unit.'.';
        }

        return match ($action) {
            'do_not_buy', 'buy_less' => 'Current stock is sufficient. No purchase suggested.',
            'use_before_expiry' => 'No purchase suggested. Use current stock before expiry.',
            default => 'No purchase suggested. Continue monitoring stock usage.',
        };
    }
}
