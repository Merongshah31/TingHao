@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">GENERATED REPORT</p>
                <h1>Inventory Summary</h1>
                <p>Generated on {{ now()->format('d M Y H:i') }}.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('reports.index') }}" class="btn btn-muted">Reports</a>
            </div>
        </div>

        <div class="report-metrics">
            <article><span>Total Ingredients</span><strong>{{ $totalIngredients }}</strong></article>
            <article><span>Total Categories</span><strong>{{ $totalCategories }}</strong></article>
            <article><span>Low Stock</span><strong>{{ $lowStockIngredients->count() }}</strong></article>
            <article><span>Expired</span><strong>{{ $expiredIngredients->count() }}</strong></article>
        </div>

        <div class="report-section">
            <h2>Low Stock Items</h2>
            <ul>
                @forelse ($lowStockIngredients as $ingredient)
                    <li>{{ $ingredient->name }} — {{ $ingredient->quantity }} {{ $ingredient->unit }} remaining</li>
                @empty
                    <li>No low-stock items.</li>
                @endforelse
            </ul>
        </div>

        <div class="report-section">
            <h2>Expired Items</h2>
            <ul>
                @forelse ($expiredIngredients as $ingredient)
                    <li>{{ $ingredient->name }} — expired {{ $ingredient->expiry_date?->format('d M Y') }}</li>
                @empty
                    <li>No expired items.</li>
                @endforelse
            </ul>
        </div>

        <div class="report-section">
            <h2>Recent Stock Movement</h2>
            <ul>
                @forelse ($recentMovements as $movement)
                    <li>{{ $movement->typeLabel() }}: {{ $movement->ingredient->name }} {{ $movement->quantity }} {{ $movement->ingredient->unit }} by {{ $movement->creator?->name ?? 'Unknown' }}</li>
                @empty
                    <li>No recent stock movement.</li>
                @endforelse
            </ul>
        </div>
    </section>
</main>
@endsection
