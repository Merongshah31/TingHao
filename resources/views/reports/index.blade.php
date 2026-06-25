@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.reports') }}</p>
                <h1>{{ __('messages.reports_dashboard') }}</h1>
                <p>{{ __('messages.reports_intro') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">{{ __('messages.dashboard') }}</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('reports.generated-summary') }}" class="btn btn-primary">{{ __('messages.generate_report') }}</a>
                @endif
            </div>
        </div>

        <div class="report-metrics">
            <article><span>{{ __('messages.total_ingredients') }}</span><strong>{{ $totalIngredients }}</strong></article>
            <article><span>{{ __('messages.low_stock') }}</span><strong>{{ $lowStockCount }}</strong></article>
            <article><span>{{ __('messages.expiring_soon') }}</span><strong>{{ $expiringCount }}</strong></article>
            <article><span>{{ __('messages.expired') }}</span><strong>{{ $expiredCount }}</strong></article>
            <article><span>{{ __('messages.stock_in') }}</span><strong>{{ $stockInCount }}</strong></article>
            <article><span>{{ __('messages.stock_out') }}</span><strong>{{ $stockOutCount }}</strong></article>
        </div>

        <div class="report-grid">
            <a href="{{ route('reports.inventory') }}">
                <strong>{{ __('messages.inventory_report') }}</strong>
                <span>{{ __('messages.inventory_intro') }}</span>
            </a>
            <a href="{{ route('reports.stock') }}">
                <strong>{{ __('messages.stock_movement_report') }}</strong>
                <span>{{ __('messages.stock_intro') }}</span>
            </a>
            <a href="{{ route('reports.low-stock') }}">
                <strong>{{ __('messages.low_stock_report') }}</strong>
                <span>{{ __('messages.low_stock_intro') }}</span>
            </a>
            <a href="{{ route('reports.expiry') }}">
                <strong>{{ __('messages.expiry_report') }}</strong>
                <span>{{ __('messages.expiry_intro') }}</span>
            </a>
        </div>
    </section>
</main>
@endsection
