<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\RestockRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LowStockController extends Controller
{
    public function index(): View
    {
        return view('alerts.low-stock', [
            'title' => 'Ting Hao | Low Stock Alerts',
            'ingredients' => Ingredient::query()
                ->with(['category', 'restockRequests' => fn ($query) => $query->latest()])
                ->lowStock()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function requestRestock(Request $request, Ingredient $ingredient): RedirectResponse
    {
        abort_unless($ingredient->isLowStock(), 422, 'Only low-stock ingredients can be requested for restock.');

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $ingredient->restockRequests()->create([
            'status' => RestockRequest::STATUS_REQUESTED,
            'notes' => $data['notes'] ?? null,
            'requested_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Restock request created.');
    }

    public function updateRestock(Request $request, RestockRequest $restockRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:requested,ordered,completed'],
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
