<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\RestockRequest;
use App\Services\Agent\TingHaoAgentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class LowStockController extends Controller
{
    public function index(): View
    {
        return view('alerts.low-stock', [
            'title' => 'Ting Hao | Low Stock Alerts',
            'ingredients' => Ingredient::query()
                ->select(['id', 'category_id', 'name', 'sku', 'unit', 'quantity', 'minimum_stock'])
                ->with([
                    'category:id,name',
                    'currentRestockRequest',
                    'activeRestockRequest',
                ])
                ->lowStock()
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function requestRestock(Request $request, Ingredient $ingredient): RedirectResponse
    {
        abort_unless($ingredient->isLowStock(), 422, 'Only low-stock ingredients can be requested for restock.');

        $activeRequestExists = $ingredient->restockRequests()
            ->whereIn('status', RestockRequest::ACTIVE_STATUSES)
            ->exists();

        if ($activeRequestExists) {
            return back()->with('status', __('messages.restock_request_exists'));
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $ingredient->restockRequests()->create([
            'status' => RestockRequest::STATUS_REQUESTED,
            'notes' => $data['notes'] ?? __('messages.default_restock_request_note', ['ingredient' => $ingredient->name]),
            'requested_by' => $request->user()->id,
        ]);

        return back()->with('status', __('messages.restock_request_submitted'));
    }

    public function planRestockWithAgent(Request $request, Ingredient $ingredient, TingHaoAgentService $agentService): RedirectResponse
    {
        abort_unless($ingredient->isLowStock(), 422, 'Only low-stock ingredients can be planned for restock.');

        $supplier = $ingredient->supplier;
        $shortage = max(0, (float) $ingredient->minimum_stock - (float) $ingredient->quantity);
        $targetQuantity = max((float) $ingredient->minimum_stock, ((float) $ingredient->minimum_stock * 2) - (float) $ingredient->quantity);
        $prompt = trim(sprintf(
            'Plan restock for %s. Current stock is %.2f %s, minimum stock is %.2f %s, shortage is %.2f %s. Recommend %.2f %s%s and create a purchase order draft for admin approval.',
            $ingredient->name,
            (float) $ingredient->quantity,
            $ingredient->unit,
            (float) $ingredient->minimum_stock,
            $ingredient->unit,
            $shortage,
            $ingredient->unit,
            $targetQuantity,
            $ingredient->unit,
            $supplier ? ' from '.$supplier->name : ''
        ));

        $agentRun = $agentService->run($request->user(), $prompt);
        Cache::forget(DashboardController::CACHE_KEY);

        $purchaseOrder = $agentRun->purchaseOrders->first();

        if ($purchaseOrder) {
            return redirect()
                ->route('purchase-orders.show', $purchaseOrder)
                ->with('status', __('messages.agent_restock_plan_created'));
        }

        return redirect()
            ->route('agent.runs.show', $agentRun)
            ->with('status', __('messages.agent_restock_plan_needs_review'));
    }

    public function updateRestock(Request $request, RestockRequest $restockRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:requested,ordered,completed,rejected'],
        ]);

        $updates = ['status' => $data['status']];

        if ($data['status'] === RestockRequest::STATUS_COMPLETED) {
            $updates['completed_by'] = $request->user()->id;
            $updates['completed_at'] = now();
        } else {
            $updates['completed_by'] = null;
            $updates['completed_at'] = null;
        }

        $restockRequest->update($updates);

        return back()->with('status', 'Restock request updated.');
    }
}
