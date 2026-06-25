@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.generated_report') }}</p>
                <h1>{{ __('messages.inventory_summary') }}</h1>
                <p>{{ __('messages.generated_on', ['date' => $generatedAt->format('d M Y H:i')]) }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('reports.index') }}" class="btn btn-muted">{{ __('messages.reports') }}</a>
                <a href="{{ route('reports.generated-summary.pdf') }}" class="btn btn-primary">{{ __('messages.download_pdf') }}</a>
            </div>
        </div>

        <div class="report-metrics">
            <article><span>{{ __('messages.total_ingredients') }}</span><strong>{{ $totalIngredients }}</strong></article>
            <article><span>{{ __('messages.total_categories') }}</span><strong>{{ $totalCategories }}</strong></article>
            <article><span>{{ __('messages.low_stock') }}</span><strong>{{ $lowStockIngredients->count() }}</strong></article>
            <article><span>{{ __('messages.expired') }}</span><strong>{{ $expiredIngredients->count() }}</strong></article>
        </div>

        <div class="report-section">
            <h2>{{ __('messages.low_stock_items') }}</h2>
            <ul>
                @forelse ($lowStockIngredients as $ingredient)
                    <li>{{ $ingredient->name }} &mdash; {{ $ingredient->quantity }} {{ $ingredient->unit }} {{ __('messages.remaining') }}</li>
                @empty
                    <li>{{ __('messages.no_low_stock_items_found') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="report-section">
            <h2>{{ __('messages.expired_items') }}</h2>
            <ul>
                @forelse ($expiredIngredients as $ingredient)
                    <li>{{ $ingredient->name }} &mdash; {{ __('messages.expired_on', ['date' => $ingredient->expiry_date?->format('d M Y')]) }}</li>
                @empty
                    <li>{{ __('messages.no_expired_items_found') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="report-section">
            <h2>{{ __('messages.recent_stock_movement') }}</h2>
            <ul>
                @forelse ($recentMovements as $movement)
                    <li>{{ $movement->typeLabel() }}: {{ $movement->ingredient->name }} {{ $movement->quantity }} {{ $movement->ingredient->unit }} by {{ $movement->creator?->name ?? __('messages.unknown') }}</li>
                @empty
                    <li>{{ __('messages.no_recent_stock_movement') }}</li>
                @endforelse
            </ul>
        </div>
    </section>
</main>
@endsection
