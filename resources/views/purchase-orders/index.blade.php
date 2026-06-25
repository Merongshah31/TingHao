@extends('layouts.app')

@section('content')
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
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('purchase-orders.create-from-low-stock') }}" class="btn btn-muted">{{ __('messages.create_po_from_low_stock') }}</a>
                    <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">{{ __('messages.create_purchase_order') }}</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.po_number') }}</th>
                        <th>{{ __('messages.supplier') }}</th>
                        <th>{{ __('messages.status') }}</th>
                        <th>{{ __('messages.order_date') }}</th>
                        <th>{{ __('messages.expected_delivery_date') }}</th>
                        <th>{{ __('messages.subtotal') }}</th>
                        <th>{{ __('messages.sent_at') }}</th>
                        <th>{{ __('messages.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrders as $purchaseOrder)
                        <tr>
                            <td><strong>{{ $purchaseOrder->po_number }}</strong></td>
                            <td>{{ $purchaseOrder->supplier?->name ?? __('messages.not_set') }}</td>
                            <td><span class="status-pill po-status-{{ $purchaseOrder->status }}">{{ __('messages.'.$purchaseOrder->status) }}</span></td>
                            <td>{{ $purchaseOrder->order_date?->format('d M Y') ?? __('messages.not_set') }}</td>
                            <td>{{ $purchaseOrder->expected_delivery_date?->format('d M Y') ?? __('messages.not_set') }}</td>
                            <td>RM {{ number_format((float) $purchaseOrder->subtotal, 2) }}</td>
                            <td>{{ $purchaseOrder->sent_at?->format('d M Y H:i') ?? __('messages.not_sent') }}</td>
                            <td class="table-actions stacked-actions">
                                <a class="action-chip" href="{{ route('purchase-orders.show', $purchaseOrder) }}">{{ __('messages.view') }}</a>
                                @if (auth()->user()->isAdmin())
                                    <a class="action-chip" href="{{ route('purchase-orders.edit', $purchaseOrder) }}">{{ __('messages.edit') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">{{ __('messages.no_purchase_orders') }}</td>
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
