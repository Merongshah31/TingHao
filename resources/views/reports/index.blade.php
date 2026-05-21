@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">REPORTS & ANALYTICS</p>
                <h1>Reports Dashboard</h1>
                <p>Review inventory condition, stock movement, low-stock items, and expiry risk.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">Dashboard</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('reports.generated-summary') }}" class="btn btn-primary">Generate Report</a>
                @endif
            </div>
        </div>

        <div class="report-metrics">
            <article><span>Total Ingredients</span><strong>{{ $totalIngredients }}</strong></article>
            <article><span>Low Stock</span><strong>{{ $lowStockCount }}</strong></article>
            <article><span>Expiring Soon</span><strong>{{ $expiringCount }}</strong></article>
            <article><span>Expired</span><strong>{{ $expiredCount }}</strong></article>
            <article><span>Stock In Records</span><strong>{{ $stockInCount }}</strong></article>
            <article><span>Stock Out Records</span><strong>{{ $stockOutCount }}</strong></article>
        </div>

        <div class="report-grid">
            <a href="{{ route('reports.inventory') }}">
                <strong>Inventory Report</strong>
                <span>Ingredient list, suppliers, quantities, and stock status.</span>
            </a>
            <a href="{{ route('reports.stock') }}">
                <strong>Stock Movement Report</strong>
                <span>Stock-in and stock-out records with date filters.</span>
            </a>
            <a href="{{ route('reports.low-stock') }}">
                <strong>Low Stock Report</strong>
                <span>Items at or below minimum stock level.</span>
            </a>
            <a href="{{ route('reports.expiry') }}">
                <strong>Expiry Report</strong>
                <span>Expiring soon and expired ingredients.</span>
            </a>
        </div>
    </section>
</main>
@endsection
