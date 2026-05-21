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

        <section class="dashboard-analytics" aria-label="Dashboard analytics">
            <article class="analytics-card analytics-value">
                <div>
                    <p class="eyebrow">ANALYTICS</p>
                    <h2>Inventory Value</h2>
                    <strong>RM {{ number_format($analytics['inventoryValue'], 2) }}</strong>
                    <span>Estimated value from current quantity and cost price.</span>
                </div>
            </article>

            <article class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <p class="eyebrow">STOCK HEALTH</p>
                        <h2>{{ $analytics['stockHealthPercent'] }}%</h2>
                    </div>
                    <span>{{ 100 - $analytics['stockHealthPercent'] }}% need review</span>
                </div>
                <div class="progress-track" aria-hidden="true">
                    <span style="width: {{ $analytics['stockHealthPercent'] }}%"></span>
                </div>
                <p>Healthy ingredients are above their minimum stock level.</p>
            </article>

            <article class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <p class="eyebrow">MOVEMENT MIX</p>
                        <h2>{{ $analytics['stockOutPercent'] }}%</h2>
                    </div>
                    <span>stock out</span>
                </div>
                <div class="split-bar" aria-hidden="true">
                    <span class="stock-in" style="width: {{ $analytics['stockInPercent'] }}%"></span>
                    <span class="stock-out" style="width: {{ $analytics['stockOutPercent'] }}%"></span>
                </div>
                <div class="analytics-pair">
                    <span>In: {{ number_format($analytics['stockIn'], 2) }}</span>
                    <span>Out: {{ number_format($analytics['stockOut'], 2) }}</span>
                </div>
            </article>
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

        <section class="dashboard-insights">
            <article class="dashboard-panel">
                <div class="panel-title-row">
                    <div>
                        <p class="eyebrow">ATTENTION LIST</p>
                        <h2>Lowest Stock Items</h2>
                    </div>
                    <a href="{{ route('alerts.low-stock') }}">View all</a>
                </div>

                <div class="insight-list">
                    @forelse ($analytics['lowStockItems'] as $item)
                        <div class="insight-row">
                            <div>
                                <strong>{{ $item['name'] }}</strong>
                                <span>Minimum {{ number_format($item['minimum'], 2) }} {{ $item['unit'] }}</span>
                            </div>
                            <em>{{ number_format($item['quantity'], 2) }} {{ $item['unit'] }}</em>
                        </div>
                    @empty
                        <p class="empty-copy">No low-stock items right now.</p>
                    @endforelse
                </div>
            </article>

            <article class="dashboard-panel">
                <div class="panel-title-row">
                    <div>
                        <p class="eyebrow">LIVE LEDGER</p>
                        <h2>Recent Movement</h2>
                    </div>
                    <a href="{{ route('stock.index') }}">Open ledger</a>
                </div>

                <div class="insight-list">
                    @forelse ($analytics['recentMovements'] as $movement)
                        <div class="insight-row">
                            <div>
                                <strong>{{ $movement->ingredient?->name ?? 'Deleted ingredient' }}</strong>
                                <span>{{ $movement->creator?->name ?? 'System' }} · {{ $movement->created_at->format('d M Y') }}</span>
                            </div>
                            <em class="{{ $movement->type === \App\Models\StockMovement::TYPE_IN ? 'positive' : 'negative' }}">
                                {{ $movement->type === \App\Models\StockMovement::TYPE_IN ? '+' : '-' }}{{ number_format($movement->quantity, 2) }}
                            </em>
                        </div>
                    @empty
                        <p class="empty-copy">No stock movement has been recorded yet.</p>
                    @endforelse
                </div>
            </article>
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
