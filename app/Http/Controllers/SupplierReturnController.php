<?php

namespace App\Http\Controllers;

use App\Models\SupplierReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierReturnController extends Controller
{
    public function index(): View
    {
        return view('supplier-returns.index', [
            'title' => 'Ting Hao | '.__('messages.supplier_returns'),
            'supplierReturns' => SupplierReturn::query()
                ->with(['supplier:id,name', 'ingredient:id,name,unit', 'purchaseOrder:id,po_number', 'creator:id,name'])
                ->latest()
                ->paginate(12),
        ]);
    }

    public function show(SupplierReturn $supplierReturn): View
    {
        return view('supplier-returns.show', [
            'title' => 'Ting Hao | '.$supplierReturn->return_number,
            'supplierReturn' => $supplierReturn->load([
                'supplier',
                'ingredient',
                'purchaseOrder',
                'purchaseOrderItem',
                'creator',
            ]),
            'statuses' => SupplierReturn::STATUSES,
        ]);
    }

    public function update(Request $request, SupplierReturn $supplierReturn): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(SupplierReturn::STATUSES)],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $supplierReturn->update([
            'status' => $data['status'],
            'reason' => $data['reason'] ?? $supplierReturn->reason,
        ]);

        return back()->with('status', __('messages.supplier_return_updated'));
    }
}
