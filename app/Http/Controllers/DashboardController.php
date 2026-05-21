<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\RestockRequest;
use App\Models\StockMovement;
use App\Models\Supplier;
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
}
