@extends('layouts.app')

@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
@endphp
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.purchase_order_management') }}</p>
                <h1>{{ __('messages.purchase_orders') }}</h1>
                <p>{{ __('messages.purchase_orders_intro') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">{{ __('messages.dashboard') }}</a>
                @if ($isAdmin)
                    <a href="{{ route('purchase-orders.create-from-low-stock') }}" class="btn btn-muted">{{ __('messages.create_po_from_low_stock') }}</a>
                    <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">{{ __('messages.create_purchase_order') }}</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <div class="table-card po-index-table-card">
            <table class="data-table po-index-table">
                <colgroup>
                    <col class="po-col-number">
                    <col class="po-col-supplier">
                    <col class="po-col-status">
                    <col class="po-col-source">
                    <col class="po-col-approval">
                    <col class="po-col-requested">
                    <col class="po-col-order-date">
                    <col class="po-col-delivery-date">
                    <col class="po-col-subtotal">
                    <col class="po-col-sent-at">
                    <col class="po-col-action">
                </colgroup>
                <thead>
                    <tr>
                        <th>{{ __('messages.po_number') }}</th>
                        <th>{{ __('messages.supplier') }}</th>
                        <th>{{ __('messages.status') }}</th>
                        <th>Source</th>
                        <th>Approval</th>
                        <th class="po-priority-secondary">Requested by</th>
                        <th>{{ __('messages.order_date') }}</th>
                        <th class="po-priority-secondary">{{ __('messages.expected_delivery_date') }}</th>
                        <th>{{ __('messages.subtotal') }}</th>
                        <th class="po-priority-secondary">{{ __('messages.sent_at') }}</th>
                        <th>{{ __('messages.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrders as $purchaseOrder)
                        @php
                            $nextStep = match ($purchaseOrder->status) {
                                \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL => 'Waiting admin approval',
                                \App\Models\PurchaseOrder::STATUS_APPROVED => 'Prepare supplier email draft',
                                \App\Models\PurchaseOrder::STATUS_SENT => 'Confirm before receiving',
                                \App\Models\PurchaseOrder::STATUS_CONFIRMED => 'Receive Goods',
                                \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'Continue Receiving',
                                \App\Models\PurchaseOrder::STATUS_RECEIVED => $isAdmin ? 'Ready to close' : 'Received',
                                \App\Models\PurchaseOrder::STATUS_CLOSED => 'Completed',
                                \App\Models\PurchaseOrder::STATUS_REJECTED,
                                \App\Models\PurchaseOrder::STATUS_CANCELLED => 'No further action',
                                default => 'Prepare supplier communication',
                            };
                            $nextStepTone = match ($purchaseOrder->status) {
                                \App\Models\PurchaseOrder::STATUS_CONFIRMED,
                                \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'ready',
                                \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL,
                                \App\Models\PurchaseOrder::STATUS_APPROVED,
                                \App\Models\PurchaseOrder::STATUS_SENT,
                                \App\Models\PurchaseOrder::STATUS_RECEIVED => 'waiting',
                                default => 'neutral',
                            };
                            $canEditFromIndex = $isAdmin && in_array($purchaseOrder->status, [
                                \App\Models\PurchaseOrder::STATUS_DRAFT,
                                \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL,
                                \App\Models\PurchaseOrder::STATUS_APPROVED,
                                \App\Models\PurchaseOrder::STATUS_SENT,
                            ], true);
                        @endphp
                        <tr data-po-status="{{ $purchaseOrder->status }}">
                            <td data-label="{{ __('messages.po_number') }}"><strong>{{ $purchaseOrder->po_number }}</strong></td>
                            <td data-label="{{ __('messages.supplier') }}">{{ $purchaseOrder->supplier?->name ?? __('messages.not_set') }}</td>
                            <td data-label="{{ __('messages.status') }}"><span class="status-pill po-status-{{ $purchaseOrder->status }}">{{ str_replace('_', ' ', __('messages.'.$purchaseOrder->status)) }}</span></td>
                            <td data-label="Source">
                                @if ($purchaseOrder->agentRun?->input_type === 'stock_prediction_restock')
                                    <span class="status-pill info po-source-badge">Created from Stock Prediction</span>
                                @elseif ($purchaseOrder->agentRun)
                                    <span class="status-pill neutral po-source-badge">TingHao Agent</span>
                                @else
                                    <span class="status-pill neutral po-source-badge">Manual</span>
                                @endif
                            </td>
                            <td data-label="Approval">{{ $purchaseOrder->approvalRequest?->status ? ucfirst($purchaseOrder->approvalRequest->status) : 'Not required' }}</td>
                            <td class="po-priority-secondary" data-label="Requested by">{{ $purchaseOrder->requestedBy?->name ?? $purchaseOrder->creator?->name ?? __('messages.system') }}</td>
                            <td data-label="{{ __('messages.order_date') }}">{{ $purchaseOrder->order_date?->format('d M Y') ?? __('messages.not_set') }}</td>
                            <td class="po-priority-secondary" data-label="{{ __('messages.expected_delivery_date') }}">{{ $purchaseOrder->expected_delivery_date?->format('d M Y') ?? __('messages.not_set') }}</td>
                            <td data-label="{{ __('messages.subtotal') }}">RM {{ number_format((float) $purchaseOrder->subtotal, 2) }}</td>
                            <td class="po-priority-secondary" data-label="{{ __('messages.sent_at') }}">{{ $purchaseOrder->sent_at?->format('d M Y H:i') ?? __('messages.not_sent') }}</td>
                            <td class="po-action-cell" data-label="{{ __('messages.action') }}">
                                <div class="po-index-actions">
                                    <a class="action-chip" href="{{ route('purchase-orders.show', $purchaseOrder) }}">{{ __('messages.view') }}</a>
                                    @if ($purchaseOrder->canReceiveStock())
                                        <a class="action-chip po-action-primary" href="{{ route('purchase-orders.receive-form', $purchaseOrder) }}">{{ $nextStep }}</a>
                                    @else
                                        <span class="po-next-step {{ $nextStepTone }}">{{ $nextStep }}</span>
                                    @endif
                                    @if ($isAdmin)
                                        @if ($purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL)
                                            <a class="action-chip" href="{{ route('purchase-orders.show', $purchaseOrder) }}">Review Approval</a>
                                        @elseif ($purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_DRAFT)
                                            <form action="{{ route('purchase-orders.send-email', $purchaseOrder) }}" method="post">
                                                @csrf
                                                <button type="submit" class="action-chip button-chip">{{ __('messages.send_email_to_supplier') }}</button>
                                            </form>
                                        @endif
                                        @if ($purchaseOrder->canBeConfirmed())
                                            <form action="{{ route('purchase-orders.confirm', $purchaseOrder) }}" method="post">
                                                @csrf
                                                <button type="submit" class="action-chip button-chip">{{ __('messages.mark_supplier_confirmed') }}</button>
                                            </form>
                                        @endif
                                        @if ($canEditFromIndex)
                                            <a class="action-chip" href="{{ route('purchase-orders.edit', $purchaseOrder) }}">{{ __('messages.edit') }}</a>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="empty-state">{{ __('messages.no_purchase_orders') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $purchaseOrders->links() }}
        </div>
    </section>
</main>
@endsection
