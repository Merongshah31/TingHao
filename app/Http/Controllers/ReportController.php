<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index', [
            'title' => 'Ting Hao | Reports',
            'totalIngredients' => Ingredient::count(),
            'lowStockCount' => Ingredient::lowStock()->count(),
            'expiringCount' => Ingredient::expiringWithin(30)->count(),
            'expiredCount' => Ingredient::expired()->count(),
            'stockInCount' => StockMovement::where('type', StockMovement::TYPE_IN)->count(),
            'stockOutCount' => StockMovement::where('type', StockMovement::TYPE_OUT)->count(),
        ]);
    }

    public function inventory(Request $request): View
    {
        $categoryId = $request->integer('category');

        return view('reports.inventory', [
            'title' => 'Ting Hao | Inventory Report',
            'categories' => Category::query()->select(['id', 'name'])->orderBy('name')->get(),
            'selectedCategory' => $categoryId,
            'ingredients' => Ingredient::query()
                ->select(['id', 'category_id', 'supplier_id', 'name', 'unit', 'quantity', 'minimum_stock'])
                ->with([
                    'category:id,name',
                    'supplier:id,name',
                ])
                ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function stock(Request $request): View
    {
        $type = $request->string('type')->toString();
        $from = $request->date('from');
        $to = $request->date('to');

        return view('reports.stock', [
            'title' => 'Ting Hao | Stock Movement Report',
            'selectedType' => $type,
            'from' => $from?->format('Y-m-d'),
            'to' => $to?->format('Y-m-d'),
            'movements' => StockMovement::query()
                ->select(['id', 'ingredient_id', 'type', 'quantity', 'quantity_before', 'quantity_after', 'created_by', 'created_at'])
                ->with([
                    'ingredient:id,name,unit',
                    'creator:id,name',
                ])
                ->when(in_array($type, [StockMovement::TYPE_IN, StockMovement::TYPE_OUT], true), fn ($query) => $query->where('type', $type))
                ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function lowStock(): View
    {
        return view('reports.low-stock', [
            'title' => 'Ting Hao | Low Stock Report',
            'ingredients' => Ingredient::query()
                ->select(['id', 'category_id', 'supplier_id', 'name', 'unit', 'quantity', 'minimum_stock'])
                ->with([
                    'category:id,name',
                    'supplier:id,name',
                ])
                ->lowStock()
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function expiry(Request $request): View
    {
        $filter = $request->string('filter')->toString() ?: 'expiring';

        return view('reports.expiry', [
            'title' => 'Ting Hao | Expiry Report',
            'filter' => $filter,
            'ingredients' => Ingredient::query()
                ->select(['id', 'category_id', 'supplier_id', 'name', 'unit', 'quantity', 'expiry_date'])
                ->with([
                    'category:id,name',
                    'supplier:id,name',
                ])
                ->when($filter === 'expired', fn ($query) => $query->expired())
                ->when($filter !== 'expired', fn ($query) => $query->expiringWithin(30))
                ->orderBy('expiry_date')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function generatedSummary(): View
    {
        return view('reports.generated-summary', array_merge($this->generatedSummaryData(), [
            'title' => 'Ting Hao | Generated Summary Report',
            'recentMovements' => StockMovement::query()
                ->select(['id', 'ingredient_id', 'type', 'quantity', 'quantity_before', 'quantity_after', 'created_by', 'created_at'])
                ->with([
                    'ingredient:id,name,unit',
                    'creator:id,name',
                ])
                ->latest()
                ->take(15)
                ->get(),
        ]));
    }

    public function downloadGeneratedSummaryPdf(): Response
    {
        $data = $this->generatedSummaryData();
        $filename = 'inventory-summary-'.$data['generatedAt']->format('Y-m-d-Hi').'.pdf';

        return Pdf::loadView('reports.pdf.generated-summary', $data)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    /**
     * @return array{
     *     generatedAt: Carbon,
     *     totalIngredients: int,
     *     totalCategories: int,
     *     lowStockIngredients: Collection<int, Ingredient>,
     *     expiredIngredients: Collection<int, Ingredient>
     * }
     */
    private function generatedSummaryData(): array
    {
        return [
            'generatedAt' => now(),
            'totalIngredients' => Ingredient::count(),
            'totalCategories' => Category::count(),
            'lowStockIngredients' => Ingredient::query()
                ->select(['id', 'category_id', 'name', 'unit', 'quantity', 'minimum_stock'])
                ->with('category:id,name')
                ->lowStock()
                ->orderBy('name')
                ->limit(50)
                ->get(),
            'expiredIngredients' => Ingredient::query()
                ->select(['id', 'category_id', 'name', 'unit', 'quantity', 'expiry_date'])
                ->with('category:id,name')
                ->expired()
                ->orderBy('expiry_date')
                ->limit(50)
                ->get(),
        ];
    }
}
