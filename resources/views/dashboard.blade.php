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
            <article class="analytics-card analytics-value analytics-visual-card">
                <div class="value-visual">
                    <div>
                        <span>Total</span>
                        <strong>RM {{ number_format($analytics['inventoryValue'], 2) }}</strong>
                    </div>
                    <div class="value-bars" aria-hidden="true">
                        <i style="height: 42%"></i>
                        <i style="height: 62%"></i>
                        <i style="height: 78%"></i>
                        <i style="height: 54%"></i>
                        <i style="height: 88%"></i>
                    </div>
                </div>
                <div class="analytics-copy">
                    <p class="eyebrow">ANALYTICS</p>
                    <h2>Inventory Value</h2>
                    <span>Estimated value from current quantity and cost price.</span>
                </div>
            </article>

            <article class="analytics-card analytics-visual-card">
                <div class="donut-wrap">
                    <div class="donut-chart" style="--value: {{ $analytics['stockHealthPercent'] }};">
                        <strong>{{ $analytics['stockHealthPercent'] }}%</strong>
                        <span>healthy</span>
                    </div>
                    <div class="analytics-copy">
                        <p class="eyebrow">STOCK HEALTH</p>
                        <h2>{{ 100 - $analytics['stockHealthPercent'] }}% need review</h2>
                        <span>Healthy ingredients are above their minimum stock level.</span>
                    </div>
                </div>
            </article>

            <article class="analytics-card movement-visual">
                <div>
                    <p class="eyebrow">MOVEMENT MIX</p>
                    <h2>Stock Flow</h2>
                </div>
                <div class="flow-chart" aria-hidden="true">
                    <div>
                        <span class="flow-in" style="height: {{ max(12, $analytics['stockInPercent']) }}%"></span>
                    </div>
                    <div>
                        <span class="flow-out" style="height: {{ max(12, $analytics['stockOutPercent']) }}%"></span>
                    </div>
                </div>
                <div class="analytics-pair">
                    <span><i class="legend-dot in"></i> In {{ number_format($analytics['stockIn'], 2) }}</span>
                    <span><i class="legend-dot out"></i> Out {{ number_format($analytics['stockOut'], 2) }}</span>
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
                        <div class="insight-row visual-row">
                            <div>
                                <strong>{{ $item['name'] }}</strong>
                                <span>Short {{ number_format($item['shortage'], 2) }} {{ $item['unit'] }} from minimum</span>
                                <div class="stock-mini-bar" aria-hidden="true">
                                    <i style="width: {{ $item['percent'] }}%"></i>
                                </div>
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
                        <div class="insight-row movement-row">
                            <span class="movement-type {{ $movement->type === \App\Models\StockMovement::TYPE_IN ? 'in' : 'out' }}">
                                {{ $movement->type === \App\Models\StockMovement::TYPE_IN ? 'IN' : 'OUT' }}
                            </span>
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
