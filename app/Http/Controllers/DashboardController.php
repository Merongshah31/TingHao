<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\RestockRequest;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
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
            'metrics' => $this->metrics(),
            'analytics' => $this->analytics(),
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
            'metrics' => $this->metrics(),
            'analytics' => $this->analytics(),
        ]);
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function metrics(): array
    {
        return [
            [
                'label' => __('messages.total_items'),
                'value' => Ingredient::count(),
                'hint' => __('messages.total_active_records'),
                'icon' => 'package-2',
                'tone' => 'green',
            ],
            [
                'label' => __('messages.low_stock'),
                'value' => Ingredient::lowStock()->count(),
                'hint' => __('messages.needs_attention'),
                'icon' => 'triangle-alert',
                'tone' => 'amber',
            ],
            [
                'label' => __('messages.expiring'),
                'value' => Ingredient::expiringWithin(30)->count(),
                'hint' => __('messages.within_30_days'),
                'icon' => 'calendar-clock',
                'tone' => 'red',
            ],
            [
                'label' => __('messages.suppliers'),
                'value' => Supplier::count(),
                'hint' => __('messages.approved_partners'),
                'icon' => 'truck',
                'tone' => 'blue',
            ],
            [
                'label' => __('messages.movements'),
                'value' => StockMovement::count(),
                'hint' => __('messages.stock_ledger_entries'),
                'icon' => 'arrow-left-right',
                'tone' => 'violet',
            ],
            [
                'label' => __('messages.records'),
                'value' => Ingredient::count() + Supplier::count() + StockMovement::count(),
                'hint' => __('messages.records_count', [
                    'count' => RestockRequest::where('status', '!=', RestockRequest::STATUS_COMPLETED)->count(),
                ]),
                'icon' => 'database',
                'tone' => 'slate',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analytics(): array
    {
        $ingredients = Ingredient::query()
            ->select(['id', 'name', 'unit', 'quantity', 'minimum_stock', 'cost_price', 'expiry_date'])
            ->get();

        $totalIngredients = max($ingredients->count(), 1);
        $healthyCount = $ingredients->filter(fn (Ingredient $ingredient): bool => ! $ingredient->isLowStock())->count();
        $lowStockCount = $ingredients->filter(fn (Ingredient $ingredient): bool => $ingredient->isLowStock())->count();
        $expiredCount = Ingredient::expired()->count();
        $expiringCount = Ingredient::expiringWithin(30)->count();
        $inventoryValue = $ingredients->sum(
            fn (Ingredient $ingredient): float => (float) $ingredient->quantity * (float) ($ingredient->cost_price ?? 0)
        );

        $stockIn = StockMovement::where('type', StockMovement::TYPE_IN)->sum('quantity');
        $stockOut = StockMovement::where('type', StockMovement::TYPE_OUT)->sum('quantity');
        $movementTotal = max((float) $stockIn + (float) $stockOut, 1);

        return [
            'inventoryValue' => $inventoryValue,
            'stockHealthPercent' => round(($healthyCount / $totalIngredients) * 100),
            'attentionPercent' => round((($lowStockCount + $expiredCount + $expiringCount) / $totalIngredients) * 100),
            'stockInPercent' => round(((float) $stockIn / $movementTotal) * 100),
            'stockOutPercent' => round(((float) $stockOut / $movementTotal) * 100),
            'stockIn' => $stockIn,
            'stockOut' => $stockOut,
            'lowStockItems' => $this->lowStockItems($ingredients),
            'recentMovements' => StockMovement::with(['ingredient', 'creator'])->latest()->take(5)->get(),
        ];
    }

    /**
     * @param  Collection<int, Ingredient>  $ingredients
     * @return array<int, array<string, string|float>>
     */
    private function lowStockItems(Collection $ingredients): array
    {
        return $ingredients
            ->filter(fn (Ingredient $ingredient): bool => $ingredient->isLowStock())
            ->sortBy(fn (Ingredient $ingredient): float => (float) $ingredient->quantity - (float) $ingredient->minimum_stock)
            ->take(4)
            ->map(fn (Ingredient $ingredient): array => [
                'name' => $ingredient->name,
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
}
