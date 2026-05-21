@extends('layouts.app')

@section('content')
<main class="dashboard-page">
    <section class="dashboard-shell">
        <header class="dashboard-hero">
            <div>
                <p class="eyebrow">TING HAO SYSTEM</p>
                <h1>{{ $dashboardRole }} Dashboard</h1>
                <p class="dashboard-user">Welcome, {{ auth()->user()->name }}</p>
                <p>{{ $dashboardIntro }}</p>
            </div>

            <form action="{{ route('logout') }}" method="post">
                @csrf
                <button type="submit" class="btn btn-primary">Logout</button>
            </form>
        </header>

        <section class="dashboard-metrics" aria-label="Dashboard summary">
            @foreach ($metrics as $metric)
                <article>
                    <span>{{ $metric['label'] }}</span>
                    <strong>{{ $metric['value'] }}</strong>
                    <small>{{ $metric['hint'] }}</small>
                </article>
            @endforeach
        </section>

        <section class="dashboard-grid">
            <article class="dashboard-panel primary-panel">
                <div>
                    <p class="eyebrow">DAILY WORK</p>
                    <h2>Inventory Control</h2>
                    <p>Open the ingredient ledger, add new items, or record stock movement.</p>
                </div>
                <div class="dashboard-actions">
                    <a href="{{ route('inventory.index') }}" class="btn btn-primary">Open Inventory</a>
                    <a href="{{ route('inventory.create') }}" class="btn btn-muted">Add Ingredient</a>
                    <a href="{{ route('stock.index') }}" class="btn btn-muted">Stock History</a>
                </div>
            </article>

            <article class="dashboard-panel">
                <p class="eyebrow">ATTENTION</p>
                <h2>Alerts</h2>
                <div class="dashboard-action-list">
                    <a href="{{ route('alerts.low-stock') }}">Low Stock</a>
                    <a href="{{ route('expiry.index') }}">Expiry Dates</a>
                </div>
            </article>

            <article class="dashboard-panel">
                <p class="eyebrow">BUSINESS DATA</p>
                <h2>Records</h2>
                <div class="dashboard-action-list">
                    <a href="{{ route('suppliers.index') }}">Suppliers</a>
                    <a href="{{ route('reports.index') }}">Reports</a>
                </div>
            </article>

            @if (auth()->user()->isAdmin())
                <article class="dashboard-panel">
                    <p class="eyebrow">ADMIN</p>
                    <h2>System</h2>
                    <div class="dashboard-action-list">
                        <a href="{{ route('system.settings') }}">Settings</a>
                        <a href="{{ route('system.backups') }}">Backups</a>
                    </div>
                </article>
            @endif
        </section>

        <section class="dashboard-permissions">
            <p class="eyebrow">ACCESS SCOPE</p>
            <div class="dashboard-list">
                @foreach ($dashboardItems as $item)
                    <span>{{ $item }}</span>
                @endforeach
            </div>
        </section>
    </section>
</main>
@endsection
