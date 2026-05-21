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
            'title' => 'Ting Hao | Admin Dashboard',
            'dashboardRole' => 'Admin',
            'dashboardIntro' => 'Full system control for accounts, inventory, suppliers, reports, backup, and settings.',
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
            'title' => 'Ting Hao | Staff Dashboard',
            'dashboardRole' => 'Staff',
            'dashboardIntro' => 'Daily operation access for inventory viewing, stock movement, alerts, suppliers, and reports.',
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
                'label' => 'Ingredients',
                'value' => Ingredient::count(),
                'hint' => 'Total active records',
            ],
            [
                'label' => 'Low Stock',
                'value' => Ingredient::lowStock()->count(),
                'hint' => 'Needs attention',
            ],
            [
                'label' => 'Expiring',
                'value' => Ingredient::expiringWithin(30)->count(),
                'hint' => 'Within 30 days',
            ],
            [
                'label' => 'Suppliers',
                'value' => Supplier::count(),
                'hint' => 'Source records',
            ],
            [
                'label' => 'Movements',
                'value' => StockMovement::count(),
                'hint' => 'Stock ledger entries',
            ],
            [
                'label' => 'Restock',
                'value' => RestockRequest::where('status', '!=', RestockRequest::STATUS_COMPLETED)->count(),
                'hint' => 'Open requests',
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
     * @param Collection<int, Ingredient> $ingredients
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
                'unit' => $ingredient->unit,
            ])
            ->values()
            ->all();
    }
}
