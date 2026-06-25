@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.purchase_order_demo') }}</p>
                <h1>{{ __('messages.purchase_order_demo_workflow') }}</h1>
                <p>{{ __('messages.purchase_order_demo_intro') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">{{ __('messages.dashboard') }}</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('po-demo.create') }}" class="btn btn-primary">{{ __('messages.create_demo_po') }}</a>
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
                        <th>{{ __('messages.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrders as $po)
                        <tr>
                            <td><strong>{{ $po->po_number }}</strong></td>
                            <td>{{ $po->supplier_name }}</td>
                            <td><span class="status-pill po-status-{{ $po->status }}">{{ __('messages.'.$po->status) }}</span></td>
                            <td>{{ $po->order_date?->format('d M Y') ?? __('messages.not_set') }}</td>
                            <td>{{ $po->expected_delivery_date?->format('d M Y') ?? __('messages.not_set') }}</td>
                            <td>RM {{ number_format((float) $po->subtotal, 2) }}</td>
                            <td class="table-actions">
                                <a class="action-chip" href="{{ route('po-demo.show', $po) }}">{{ __('messages.view') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">{{ __('messages.no_purchase_orders') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <section class="stock-memory-note">
            <span><i data-lucide="sparkles"></i></span>
            <p>{{ __('messages.po_demo_future_note') }}</p>
        </section>

        <div class="pagination-wrap">
            {{ $purchaseOrders->links() }}
        </div>
    </section>
</main>
@endsection
