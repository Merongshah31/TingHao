@extends('layouts.app')

@section('content')
@php
    $timeline = [
        'draft' => __('messages.draft'),
        'email_sent' => __('messages.email_sent'),
        'supplier_confirmed' => __('messages.supplier_confirmed'),
        'received' => __('messages.received'),
        'closed' => __('messages.closed'),
    ];
    $statusOrder = array_keys($timeline);
    $currentIndex = array_search($po->status === 'partially_received' ? 'received' : $po->status, $statusOrder, true);
@endphp

<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.purchase_order_demo') }}</p>
                <h1>{{ $po->po_number }}</h1>
                <p>{{ $po->supplier_name }} · {{ $po->supplier_email ?: __('messages.not_set') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('po-demo.index') }}" class="btn btn-muted">{{ __('messages.back') }}</a>
                @if (auth()->user()->isAdmin() && $po->status === 'draft')
                    <form method="post" action="{{ route('po-demo.send-email', $po) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">{{ __('messages.send_email_demo') }}</button>
                    </form>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <div class="info-panel po-summary-panel">
            <dl>
                <div><dt>{{ __('messages.status') }}</dt><dd><span class="status-pill po-status-{{ $po->status }}">{{ __('messages.'.$po->status) }}</span></dd></div>
                <div><dt>{{ __('messages.order_date') }}</dt><dd>{{ $po->order_date?->format('d M Y') ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.expected_delivery_date') }}</dt><dd>{{ $po->expected_delivery_date?->format('d M Y') ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.subtotal') }}</dt><dd>RM {{ number_format((float) $po->subtotal, 2) }}</dd></div>
            </dl>
        </div>

        <section class="po-demo-timeline">
            @foreach ($timeline as $key => $label)
                @php($index = array_search($key, $statusOrder, true))
                <div @class(['active' => $currentIndex !== false && $index <= $currentIndex])>
                    <span>{{ $index + 1 }}</span>
                    <strong>{{ $label }}</strong>
                </div>
            @endforeach
        </section>

        <div class="table-card movement-preview">
            <div class="section-heading-row">
                <h2>{{ __('messages.items') }}</h2>
                <strong>{{ __('messages.demo_only_inventory_note') }}</strong>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.ingredient') }}</th>
                        <th>{{ __('messages.quantity') }}</th>
                        <th>{{ __('messages.unit_price') }}</th>
                        <th>{{ __('messages.line_total') }}</th>
                        <th>{{ __('messages.received_quantity') }}</th>
                        <th>{{ __('messages.quality_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($po->items as $item)
                        <tr>
                            <td><strong>{{ $item->ingredient_name }}</strong></td>
                            <td>{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</td>
                            <td>RM {{ number_format((float) $item->unit_price, 2) }}</td>
                            <td>RM {{ number_format((float) $item->line_total, 2) }}</td>
                            <td>{{ number_format((float) $item->received_quantity, 2) }} {{ $item->unit }}</td>
                            <td>{{ $item->quality_status ? __('messages.'.$item->quality_status) : __('messages.not_set') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($po->email_sent_at)
            <section class="info-panel po-email-preview">
                <p class="eyebrow">{{ __('messages.supplier_email_preview') }}</p>
                <h2>{{ __('messages.purchase_order_email_subject', ['po' => $po->po_number]) }}</h2>
                <p><strong>{{ __('messages.email_to') }}:</strong> {{ $po->supplier_email ?: __('messages.not_set') }}</p>
                <p><strong>{{ __('messages.sent_at') }}:</strong> {{ $po->email_sent_at->format('d M Y H:i') }}</p>
                <div class="email-preview-body">
                    <p>Dear {{ $po->supplier_name }},</p>
                    <p>Please find below our purchase order:</p>
                    <ul>
                        @foreach ($po->items as $item)
                            <li>{{ $item->ingredient_name }} — {{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</li>
                        @endforeach
                    </ul>
                    <p>Please confirm item availability and expected delivery date.</p>
                    <p>Thank you,<br>Ting Hao</p>
                </div>
            </section>
        @endif

        <section class="po-demo-actions">
            @if (auth()->user()->isAdmin() && $po->status === 'email_sent')
                <form method="post" action="{{ route('po-demo.confirm', $po) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit">{{ __('messages.mark_supplier_confirmed') }}</button>
                </form>
            @endif

            @if (in_array($po->status, ['supplier_confirmed', 'partially_received'], true))
                <form method="post" action="{{ route('po-demo.receive', $po) }}">
                    @csrf
                    <input type="hidden" name="mode" value="partial">
                    <button class="btn btn-muted" type="submit">{{ __('messages.receive_partial_demo') }}</button>
                </form>
                <form method="post" action="{{ route('po-demo.receive', $po) }}">
                    @csrf
                    <input type="hidden" name="mode" value="full">
                    <button class="btn btn-primary" type="submit">{{ $po->status === 'partially_received' ? __('messages.receive_remaining_stock_demo') : __('messages.receive_full_demo') }}</button>
                </form>
            @endif

            @if (auth()->user()->isAdmin() && $po->status === 'received')
                <form method="post" action="{{ route('po-demo.close', $po) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit">{{ __('messages.close_po') }}</button>
                </form>
            @endif

            @if ($po->status === 'closed')
                <div class="success-alert">{{ __('messages.po_workflow_completed') }}</div>
            @endif
        </section>

        <section class="stock-memory-note">
            <span><i data-lucide="info"></i></span>
            <p>{{ __('messages.demo_only_inventory_note') }}</p>
        </section>

        <section class="smart-future-panel">
            <div>
                <p class="eyebrow">{{ __('messages.future_full_version') }}</p>
                <h2>{{ __('messages.po_demo_future_title') }}</h2>
            </div>
            <ul>
                <li>{{ __('messages.po_future_real_email') }}</li>
                <li>{{ __('messages.po_future_pdf') }}</li>
                <li>{{ __('messages.po_future_actual_low_stock') }}</li>
                <li>{{ __('messages.po_future_confirmation') }}</li>
                <li>{{ __('messages.po_future_partial_delivery') }}</li>
                <li>{{ __('messages.po_future_real_stock_in') }}</li>
                <li>{{ __('messages.po_future_grn') }}</li>
                <li>{{ __('messages.po_future_supplier_performance') }}</li>
            </ul>
        </section>
    </section>
</main>
@endsection
