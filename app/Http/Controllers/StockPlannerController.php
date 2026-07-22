<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Services\Agent\PredictionRestockPlanningService;
use App\Services\Agent\StockPredictionReasoningService;
use App\Services\Procurement\GptProcurementReviewService;
use App\Services\Stock\StockPredictionApiClient;
use App\Services\Stock\StockPredictionInputBuilder;
use App\Support\StockPlannerDisplay;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class StockPlannerController extends Controller
{
    public function __construct(
        private readonly StockPredictionApiClient $predictionClient,
        private readonly StockPredictionInputBuilder $inputBuilder,
        private readonly StockPredictionReasoningService $reasoningService,
        private readonly PredictionRestockPlanningService $planningService,
        private readonly GptProcurementReviewService $gptReviewService,
    ) {
    }

    public function index(Request $request): View
    {
        $activeView = $request->query('view') === 'calendar' ? 'calendar' : 'cards';
        $ingredients = Ingredient::query()
            ->select(['id', 'category_id', 'name', 'unit', 'quantity', 'minimum_stock', 'expiry_date', 'supplier_id'])
            ->with('supplier:id,name')
            ->orderByRaw('CASE WHEN quantity <= minimum_stock THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->paginate(12);

        $predictions = collect();
        $calendarSignals = [];

        if ($activeView === 'calendar') {
            $calendarSignals = $this->calendarSignals(
                Ingredient::query()
                    ->select(['id', 'category_id', 'name', 'unit', 'quantity', 'minimum_stock', 'expiry_date', 'supplier_id'])
                    ->with('supplier:id,name')
                    ->orderByRaw('CASE WHEN quantity <= minimum_stock THEN 0 ELSE 1 END')
                    ->orderBy('expiry_date')
                    ->orderBy('name')
                    ->take(50)
                    ->get()
            );
        } else {
            $predictions = $ingredients
                ->getCollection()
                ->mapWithKeys(fn (Ingredient $ingredient): array => [
                    $ingredient->id => $this->predictionFor($ingredient),
                ]);
        }

        $selectedDate = CarbonImmutable::parse($request->query('date', now()->toDateString()))->toDateString();

        return view('stock-planner.index', [
            'title' => 'Ting Hao | Stock Planner',
            'activeView' => $activeView,
            'ingredients' => $ingredients,
            'predictions' => $predictions,
            'calendarMonth' => CarbonImmutable::now()->startOfMonth(),
            'calendarDays' => $this->calendarDays(CarbonImmutable::now()->startOfMonth(), $calendarSignals, $selectedDate),
            'calendarSignals' => $calendarSignals,
            'selectedDate' => $selectedDate,
            'selectedSignals' => $calendarSignals[$selectedDate] ?? [],
        ]);
    }

    public function show(Ingredient $ingredient): View
    {
        $ingredient->load('supplier:id,name');
        $prediction = $this->predictionFor($ingredient);
        $predictionInput = $this->inputBuilder->build($ingredient);
        $prediction = $this->predictionClient->applyBusinessRules($prediction, $predictionInput);
        $restockAvailability = $this->planningService->availability($ingredient, $prediction, $predictionInput);

        return view('stock-planner.show', [
            'title' => 'Ting Hao | Stock Prediction',
            'ingredient' => $ingredient,
            'prediction' => $prediction,
            'predictionInput' => $predictionInput,
            'restockAvailability' => $restockAvailability,
            'qwenExplanation' => $this->reasoningService->cachedExplanation($ingredient, $prediction, $predictionInput),
        ]);
    }

    public function explain(Ingredient $ingredient): RedirectResponse
    {
        $prediction = $this->predictionFor($ingredient);

        if (! ($prediction['available'] ?? false)) {
            return redirect()
                ->route('stock-planner.prediction', $ingredient)
                ->with('error', $prediction['message'] ?? 'Prediction service unavailable');
        }

        $explanation = $this->reasoningService->explain($ingredient, $prediction, $this->inputBuilder->build($ingredient), force: true);

        if (! ($explanation['available'] ?? false)) {
            return redirect()
                ->route('stock-planner.prediction', $ingredient)
                ->with('error', $explanation['message'] ?? 'Prediction is available, but AI explanation is temporarily unavailable.');
        }

        return redirect()
            ->route('stock-planner.prediction', $ingredient)
            ->with('status', ($explanation['cache_replaced'] ?? false) ? 'Qwen explanation regenerated in English.' : 'Qwen explanation generated in English.');
    }

    public function gptReview(Ingredient $ingredient): RedirectResponse
    {
        try {
            $prediction = $this->predictionFor($ingredient);
            $predictionInput = $this->inputBuilder->build($ingredient);
            $restockAvailability = $this->planningService->availability($ingredient, $prediction, $predictionInput);
            $pendingOrders = $ingredient->purchaseOrderItems()
                ->with('purchaseOrder:id,supplier_id,status')
                ->whereHas('purchaseOrder', fn ($query) => $query->whereIn('status', [
                    \App\Models\PurchaseOrder::STATUS_DRAFT,
                    \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL,
                    \App\Models\PurchaseOrder::STATUS_APPROVED,
                    \App\Models\PurchaseOrder::STATUS_SENT,
                    \App\Models\PurchaseOrder::STATUS_CONFIRMED,
                ]))
                ->get()
                ->map(fn ($item): array => [
                    'id' => $item->purchase_order_id,
                    'supplier_id' => $item->purchaseOrder?->supplier_id,
                    'status' => $item->purchaseOrder?->status,
                    'quantity' => (float) $item->quantity,
                ])->all();

            $review = $this->gptReviewService->review(
                $ingredient,
                (float) $ingredient->quantity,
                (float) $ingredient->minimum_stock,
                (float) ($predictionInput['stock_out_last_30_days'] ?? 0) / 30,
                $pendingOrders,
                $restockAvailability['supplier_comparison'] ?? null,
                $prediction,
            );

            $message = $review['recommended_supplier_id'] === null
                ? 'GPT-5.6 Review could not produce a safe procurement recommendation. Human approval is still required.'
                : 'GPT-5.6 Review completed. No purchase order was created.';

            return redirect()->route('stock-planner.prediction', $ingredient)
                ->with('gpt_review', $review)
                ->with($review['recommended_supplier_id'] === null ? 'error' : 'status', $message);
        } catch (\Throwable) {
            return redirect()->route('stock-planner.prediction', $ingredient)
                ->with('error', 'GPT-5.6 Review is temporarily unavailable. No procurement action was taken.');
        }
    }

    public function refresh(Ingredient $ingredient): RedirectResponse
    {
        $prediction = $this->predictionFor($ingredient, force: true);

        if (! ($prediction['available'] ?? false)) {
            return redirect()
                ->route('stock-planner.prediction', $ingredient)
                ->with('error', $prediction['message'] ?? 'Prediction service unavailable');
        }

        return redirect()
            ->route('stock-planner.prediction', $ingredient)
            ->with('status', 'Prediction refreshed.');
    }

    public function planRestock(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $ingredient->load('supplier');
        $prediction = $this->predictionFor($ingredient);

        if (! ($prediction['available'] ?? false)) {
            return redirect()
                ->route('stock-planner.prediction', $ingredient)
                ->with('error', $prediction['message'] ?? 'Prediction service unavailable');
        }

        $predictionInput = $this->inputBuilder->build($ingredient);
        $qwenExplanation = $this->reasoningService->cachedExplanation($ingredient, $prediction, $predictionInput);
        $result = $this->planningService->plan($request->user(), $ingredient, $prediction, $predictionInput, $qwenExplanation);

        Cache::forget(DashboardController::CACHE_KEY);

        if ($result['purchase_order']) {
            return redirect()
                ->route('purchase-orders.show', $result['purchase_order'])
                ->with('status', $result['message']);
        }

        if ($result['agent_run']) {
            return redirect()
                ->route('agent.runs.show', $result['agent_run'])
                ->with('status', $result['message']);
        }

        return redirect()
            ->route('stock-planner.prediction', $ingredient)
            ->with('error', $result['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function predictionFor(Ingredient $ingredient, bool $force = false): array
    {
        $minutes = max(1, (int) config('stock_prediction.cache_minutes', 30));
        $cacheKey = $this->inputBuilder->cacheKey($ingredient);
        $businessFacts = $this->inputBuilder->businessFacts($ingredient);

        if (! $force && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                $this->logPredictionCache('hit', $ingredient, $cacheKey);

                return $this->predictionClient->applyBusinessRules($cached, $businessFacts);
            }
        }

        $this->logPredictionCache($force ? 'refresh' : 'miss', $ingredient, $cacheKey);

        $prediction = $this->predictionClient->predict($this->inputBuilder->build($ingredient));

        Cache::put($cacheKey, $prediction, now()->addMinutes($minutes));

        return $prediction;
    }

    private function logPredictionCache(string $status, Ingredient $ingredient, string $cacheKey): void
    {
        if (! app()->environment('local')) {
            return;
        }

        Log::info('Stock prediction cache '.$status, [
            'ingredient_id' => $ingredient->id,
            'ingredient' => $ingredient->name,
            'cache_key' => $cacheKey,
            'cache_minutes' => max(1, (int) config('stock_prediction.cache_minutes', 30)),
        ]);
    }

    /**
     * @param  Collection<int, Ingredient>  $ingredients
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function calendarSignals(Collection $ingredients): array
    {
        $signals = [];
        $today = CarbonImmutable::now()->startOfDay();

        foreach ($ingredients as $ingredient) {
            $prediction = $this->predictionFor($ingredient);

            if (! ($prediction['available'] ?? false)) {
                continue;
            }

            $input = $this->inputBuilder->build($ingredient);
            $prediction = $this->predictionClient->applyBusinessRules($prediction, $input);
            $action = $prediction['recommended_action'] ?? 'monitor';
            $displayAction = $prediction['display_action'] ?? $action;
            $signalDate = $this->signalDate($action, $prediction, $input, $today);
            $restockAvailability = $this->planningService->availability($ingredient, $prediction, $input);

            if ($signalDate === null) {
                continue;
            }

            $dateKey = $signalDate->toDateString();
            $signals[$dateKey][] = [
                'ingredient_id' => $ingredient->id,
                'ingredient_name' => StockPlannerDisplay::ingredientName($ingredient->name),
                'supplier_name' => StockPlannerDisplay::supplierName($ingredient->supplier?->name),
                'unit' => $ingredient->unit,
                'current_quantity' => (float) $ingredient->quantity,
                'minimum_stock' => (float) $ingredient->minimum_stock,
                'recommended_action' => $action,
                'display_action' => $displayAction,
                'action_label' => $prediction['action_label'] ?? $this->calendarActionLabel($displayAction),
                'action_tone' => $prediction['action_tone'] ?? 'neutral',
                'estimated_days_until_stockout' => $prediction['estimated_days_until_stockout'] ?? null,
                'suggested_quantity' => $prediction['suggested_quantity'] ?? null,
                'purchase_guidance' => $prediction['purchase_guidance'] ?? 'No purchase suggested.',
                'can_plan_restock' => $restockAvailability['allowed'],
                'restock_guidance' => $restockAvailability['message'],
                'pending_purchase_order_url' => $restockAvailability['pending_purchase_order']
                    ? route('purchase-orders.show', $restockAvailability['pending_purchase_order'])
                    : null,
                'risk_label' => $prediction['risk_label'] ?? 'Unknown',
                'confidence_percent' => $prediction['confidence_percent'] ?? null,
                'reason_labels' => $prediction['reason_labels'] ?? [],
                'detail_url' => route('stock-planner.prediction', $ingredient),
                'refresh_url' => route('stock-planner.refresh-prediction', $ingredient),
                'explain_url' => route('stock-planner.explain', $ingredient),
                'agent_plan_url' => route('stock-planner.plan-restock', $ingredient),
                'expiry_url' => route('expiry.index'),
                'inventory_url' => route('inventory.show', $ingredient),
            ];
        }

        foreach ($signals as $date => $items) {
            usort($items, fn (array $a, array $b): int => $this->actionPriority($a['display_action']) <=> $this->actionPriority($b['display_action']));
            $signals[$date] = $items;
        }

        ksort($signals);

        return $signals;
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $input
     */
    private function signalDate(string $action, array $prediction, array $input, CarbonImmutable $today): ?CarbonImmutable
    {
        return match ($action) {
            'add_stock_now', 'do_not_buy', 'buy_less' => $today,
            'add_stock_soon' => $today->addDays(max(0, (int) ($prediction['estimated_days_until_stockout'] ?? 0) - (int) ($input['supplier_lead_time_days'] ?? 2))),
            'use_before_expiry' => $today->addDays(max(0, (int) ($input['expiry_days_remaining'] ?? 0) - 2)),
            default => null,
        };
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $signals
     * @return array<int, array<string, mixed>>
     */
    private function calendarDays(CarbonImmutable $month, array $signals, string $selectedDate): array
    {
        $start = $month->startOfWeek();
        $end = $month->endOfMonth()->endOfWeek();
        $days = [];

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $dateKey = $date->toDateString();
            $dateSignals = $signals[$dateKey] ?? [];

            $days[] = [
                'date' => $date,
                'key' => $dateKey,
                'day' => $date->day,
                'in_month' => $date->month === $month->month,
                'is_today' => $date->isSameDay(now()),
                'is_selected' => $dateKey === $selectedDate,
                'signals' => array_slice($dateSignals, 0, 2),
                'more_count' => max(0, count($dateSignals) - 2),
            ];
        }

        return $days;
    }

    private function calendarActionLabel(string $action): string
    {
        return match ($action) {
            'review_expired_stock' => 'Review Expired Stock',
            'add_stock_now' => 'Add Stock Now',
            'add_stock_soon' => 'Add Stock Soon',
            'buy_less' => 'Buy Less',
            'do_not_buy' => 'Do Not Buy',
            'use_before_expiry' => 'Use Before Expiry',
            default => 'Monitor',
        };
    }

    private function actionPriority(string $action): int
    {
        return match ($action) {
            'add_stock_now' => 0,
            'add_stock_soon' => 1,
            'review_expired_stock' => 2,
            'use_before_expiry' => 3,
            'do_not_buy' => 4,
            'buy_less' => 5,
            default => 9,
        };
    }
}
