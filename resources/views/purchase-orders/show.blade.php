@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.purchase_order') }}</p>
                <h1>{{ $purchaseOrder->po_number }}</h1>
                <p>{{ $purchaseOrder->supplier?->name ?? __('messages.not_set') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-muted">{{ __('messages.back') }}</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="btn btn-muted">{{ __('messages.edit') }}</a>
                    <form action="{{ route('purchase-orders.send-email', $purchaseOrder) }}" method="post">
                        @csrf
                        <button type="submit" class="btn btn-primary">{{ __('messages.send_email_to_supplier') }}</button>
                    </form>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="error-alert">{{ $errors->first() }}</div>
        @endif

        <div class="info-panel po-summary-panel">
            <dl>
                <div><dt>{{ __('messages.status') }}</dt><dd><span class="status-pill po-status-{{ $purchaseOrder->status }}">{{ __('messages.'.$purchaseOrder->status) }}</span></dd></div>
                <div><dt>{{ __('messages.order_date') }}</dt><dd>{{ $purchaseOrder->order_date?->format('d M Y') ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.expected_delivery_date') }}</dt><dd>{{ $purchaseOrder->expected_delivery_date?->format('d M Y') ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.email_to') }}</dt><dd>{{ $purchaseOrder->email_to ?: $purchaseOrder->supplier?->email ?: __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.sent_at') }}</dt><dd>{{ $purchaseOrder->sent_at?->format('d M Y H:i') ?? __('messages.not_sent') }}</dd></div>
                <div><dt>{{ __('messages.created_by') }}</dt><dd>{{ $purchaseOrder->creator?->name ?? __('messages.system') }}</dd></div>
            </dl>
        </div>

        <div class="info-panel">
            <dl>
                <div><dt>{{ __('messages.supplier') }}</dt><dd>{{ $purchaseOrder->supplier?->name ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.contact_person') }}</dt><dd>{{ $purchaseOrder->supplier?->contact_person ?: __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.email') }}</dt><dd>{{ $purchaseOrder->supplier?->email ?: __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.phone') }}</dt><dd>{{ $purchaseOrder->supplier?->phone ?: __('messages.not_set') }}</dd></div>
            </dl>
        </div>

        <div class="table-card movement-preview">
            <div class="section-heading-row">
                <h2>{{ __('messages.items') }}</h2>
                <strong>RM {{ number_format((float) $purchaseOrder->subtotal, 2) }}</strong>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.ingredient') }}</th>
                        <th>{{ __('messages.quantity') }}</th>
                        <th>{{ __('messages.unit_price') }}</th>
                        <th>{{ __('messages.line_total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseOrder->items as $item)
                        <tr>
                            <td><strong>{{ $item->ingredient?->name ?? $item->description }}</strong><span>{{ $item->description }}</span></td>
                            <td>{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</td>
                            <td>RM {{ number_format((float) $item->unit_price, 2) }}</td>
                            <td>RM {{ number_format((float) $item->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="info-panel">
            <dl>
                <div><dt>{{ __('messages.notes') }}</dt><dd>{{ $purchaseOrder->notes ?: __('messages.no_notes_added') }}</dd></div>
            </dl>
        </div>
    </section>
</main>
@endsection
