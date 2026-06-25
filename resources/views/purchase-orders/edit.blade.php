@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.purchase_order_management') }}</p>
                <h1>{{ __('messages.edit_purchase_order') }}</h1>
                <p>{{ $purchaseOrder->po_number }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-muted">{{ __('messages.back') }}</a>
            </div>
        </div>

        @include('purchase-orders.partials.form', [
            'action' => route('purchase-orders.update', $purchaseOrder),
            'method' => 'PUT',
        ])
    </section>
</main>
@endsection
