@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.purchase_order_management') }}</p>
                <h1>{{ __('messages.create_purchase_order') }}</h1>
                <p>{{ __('messages.purchase_order_create_intro') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-muted">{{ __('messages.back') }}</a>
            </div>
        </div>

        @include('purchase-orders.partials.form', [
            'action' => route('purchase-orders.store'),
            'method' => 'POST',
            'suggestionsUrl' => route('purchase-orders.suggestions'),
        ])
    </section>
</main>
@endsection
