@extends('layouts.app')

@section('content')
<div class="saas-dashboard" data-dashboard-shell>
    <div class="saas-sidebar-backdrop" data-sidebar-backdrop></div>
    <x-dashboard.sidebar :metrics="$metrics" />

    <div class="saas-workspace">
        <x-dashboard.header :role="$dashboardRole" :metrics="$metrics" />

        <main class="saas-content">
            <section class="saas-welcome">
                <div>
                    <p class="saas-kicker"><span></span> {{ now()->format('l, d F Y') }}</p>
                    @php($greeting = now()->hour < 12 ? __('messages.good_morning') : (now()->hour < 18 ? __('messages.good_afternoon') : __('messages.good_evening')))
                    
                    <p>{{ $dashboardIntro }}</p>
                </div>
                <div class="saas-welcome-actions">
                    <a href="{{ route('reports.index') }}" class="saas-button secondary"><i data-lucide="chart-no-axes-combined"></i> {{ __('messages.view_reports') }}</a>
                    <a href="{{ route('inventory.create') }}" class="saas-button primary"><i data-lucide="plus"></i> {{ __('messages.add_item') }}</a>
                </div>
            </section>

            <section class="saas-stats" aria-label="{{ __('messages.inventory') }}">
                @foreach ($metrics as $metric)
                    <x-dashboard.stat-card :metric="$metric" />
                @endforeach
            </section>

            <section class="saas-section-heading">
                <div><span>{{ __('messages.performance') }}</span><h2>{{ __('messages.inventory_analytics') }}</h2></div>
                <a href="{{ route('reports.index') }}">{{ __('messages.open_full_analytics') }} <i data-lucide="arrow-right"></i></a>
            </section>

            <section class="saas-analytics" aria-label="Dashboard analytics">
                <article class="saas-card saas-value-card">
                    <div class="saas-card-heading">
                        <div><span>{{ __('messages.total_inventory_value') }}</span><h3>RM {{ number_format($analytics['inventoryValue'], 2) }}</h3></div>
                        <span class="saas-change positive"><i data-lucide="trending-up"></i> {{ __('messages.live') }}</span>
                    </div>
                    <p>{{ __('messages.inventory_value') }}</p>
                    <div class="saas-value-chart" aria-label="Inventory value trend visualization">
                        @foreach ([42, 49, 45, 58, 61, 55, 68, 64, 76, 81, 78, 92] as $height)
                            <i style="--bar-height: {{ $height }}%"></i>
                        @endforeach
                    </div>
                    <div class="saas-chart-axis"><span>Jan</span><span>Mar</span><span>May</span><span>Jul</span><span>Sep</span><span>Nov</span></div>
                </article>

                <article class="saas-card saas-health-card">
                    <div class="saas-card-heading">
                        <div><span>{{ __('messages.stock_status') }}</span><h3>{{ __('messages.stock_health') }}</h3></div>
                        <a href="{{ route('alerts.low-stock') }}" aria-label="View stock alerts"><i data-lucide="more-horizontal"></i></a>
                    </div>
                    <div class="saas-health-body">
                        <div class="saas-donut" style="--value: {{ $analytics['stockHealthPercent'] }}">
                            <div><strong>{{ $analytics['stockHealthPercent'] }}%</strong><span>{{ __('messages.healthy') }}</span></div>
                        </div>
                        <div class="saas-health-legend">
                            <span><i class="healthy"></i><b>{{ __('messages.healthy') }}</b><em>{{ $analytics['stockHealthPercent'] }}%</em></span>
                            <span><i class="attention"></i><b>{{ __('messages.needs_attention') }}</b><em>{{ 100 - $analytics['stockHealthPercent'] }}%</em></span>
                        </div>
                    </div>
                    <a class="saas-card-link" href="{{ route('alerts.low-stock') }}">{{ __('messages.review_stock_status') }} <i data-lucide="arrow-right"></i></a>
                </article>

                <article class="saas-card saas-flow-card">
                    <div class="saas-card-heading">
                        <div><span>{{ __('messages.movements') }}</span><h3>{{ __('messages.stock_flow') }}</h3></div>
                        <span class="saas-period">Last 7 days <i data-lucide="chevron-down"></i></span>
                    </div>
                    <div class="saas-flow-summary">
                        <span><i class="in"></i><b>{{ number_format($analytics['stockIn'], 0) }}</b> {{ __('messages.stock_in') }}</span>
                        <span><i class="out"></i><b>{{ number_format($analytics['stockOut'], 0) }}</b> {{ __('messages.stock_out') }}</span>
                    </div>
                    <div class="saas-flow-chart" aria-label="Seven-day stock movement visualization">
                        @foreach ([.52, .72, .44, .81, .62, .9, .74] as $factor)
                            <div>
                                <i class="in" style="--bar-height: {{ max(16, round($analytics['stockInPercent'] * $factor)) }}%"></i>
                                <i class="out" style="--bar-height: {{ max(12, round($analytics['stockOutPercent'] * (1.15 - $factor / 2))) }}%"></i>
                            </div>
                        @endforeach
                    </div>
                    <div class="saas-flow-days"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
                </article>
            </section>

            <section class="saas-section-heading compact">
                <div><span>{{ __('messages.workspace') }}</span><h2>{{ __('messages.management_center') }}</h2></div>
            </section>

            <section class="saas-management">
                <x-dashboard.action-card icon="boxes" :eyebrow="__('messages.daily_operations')" :title="__('messages.inventory_control')" :description="__('messages.inventory_intro')">
                    <a href="{{ route('inventory.index') }}">{{ __('messages.open_inventory') }} <i data-lucide="arrow-right"></i></a>
                    <a href="{{ route('inventory.create') }}">{{ __('messages.add_item') }}</a>
                </x-dashboard.action-card>

                <x-dashboard.action-card icon="bell-ring" :eyebrow="__('messages.needs_attention')" :title="__('messages.alerts')" :description="__('messages.low_stock_intro')">
                    <a href="{{ route('alerts.low-stock') }}">{{ __('messages.low_stock') }} <i data-lucide="arrow-right"></i></a>
                    <a href="{{ route('expiry.index') }}">{{ __('messages.expiry_dates') }}</a>
                </x-dashboard.action-card>

                <x-dashboard.action-card icon="folder-kanban" :eyebrow="__('messages.business_data')" :title="__('messages.records')" :description="__('messages.reports_intro')">
                    <a href="{{ route('suppliers.index') }}">{{ __('messages.suppliers') }} <i data-lucide="arrow-right"></i></a>
                    <a href="{{ route('reports.index') }}">{{ __('messages.reports') }}</a>
                </x-dashboard.action-card>

                <x-dashboard.action-card icon="clipboard-list" :eyebrow="__('messages.supplier_management')" :title="__('messages.purchase_orders')" :description="__('messages.purchase_orders_dashboard')">
                    <a href="{{ route('purchase-orders.index') }}">{{ __('messages.purchase_orders') }} <i data-lucide="arrow-right"></i></a>
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('purchase-orders.create') }}">{{ __('messages.create_purchase_order') }}</a>
                    @else
                        <a href="{{ route('suppliers.index') }}">{{ __('messages.suppliers') }}</a>
                    @endif
                </x-dashboard.action-card>

                <x-dashboard.action-card icon="brain-circuit" :eyebrow="__('messages.stock_planning_calendar')" :title="__('messages.smart_stock_memory_planner')" :description="__('messages.calendar_stock_demo')">
                    <a href="{{ route('stock-memory.demo') }}">{{ __('messages.open_planner') }} <i data-lucide="arrow-right"></i></a>
                    <a href="{{ route('inventory.index') }}">{{ __('messages.view_inventory') }}</a>
                </x-dashboard.action-card>

                @if (auth()->user()->isAdmin())
                    <x-dashboard.action-card icon="shield-check" :eyebrow="__('messages.administration')" :title="__('messages.system')" :description="__('messages.settings_intro')">
                        <a href="{{ route('system.settings') }}">{{ __('messages.settings') }} <i data-lucide="arrow-right"></i></a>
                        <a href="{{ route('system.backups') }}">{{ __('messages.backup_system_data') }}</a>
                    </x-dashboard.action-card>
                @endif
            </section>

            <section class="saas-lists">
                <article class="saas-card saas-list-card">
                    <div class="saas-card-heading">
                        <div><span>{{ __('messages.needs_attention') }}</span><h3>{{ __('messages.low_stock_items') }}</h3></div>
                        <a href="{{ route('alerts.low-stock') }}">{{ __('messages.view_all') }} <i data-lucide="arrow-up-right"></i></a>
                    </div>
                    <div class="saas-stock-list">
                        @forelse ($analytics['lowStockItems'] as $item)
                            <div class="saas-stock-row">
                                <span class="saas-item-icon"><i data-lucide="package"></i></span>
                                <div class="saas-item-main">
                                    <strong>{{ $item['name'] }}</strong>
                                    <span>{{ number_format($item['quantity'], 2) }} {{ $item['unit'] }} {{ __('messages.remaining') }}</span>
                                    <div><i style="width: {{ $item['percent'] }}%"></i></div>
                                </div>
                                <span class="saas-status-pill">{{ number_format($item['shortage'], 2) }} {{ __('messages.short') }}</span>
                            </div>
                        @empty
                            <div class="saas-empty"><i data-lucide="circle-check-big"></i><strong>{{ __('messages.healthy') }}</strong><span>{{ __('messages.no_low_stock_ingredients') }}</span></div>
                        @endforelse
                    </div>
                </article>

                <article class="saas-card saas-list-card">
                    <div class="saas-card-heading">
                        <div><span>{{ __('messages.live_ledger') }}</span><h3>{{ __('messages.recent_movement') }}</h3></div>
                        <a href="{{ route('stock.index') }}">{{ __('messages.open_ledger') }} <i data-lucide="arrow-up-right"></i></a>
                    </div>
                    <div class="saas-movement-list">
                        @forelse ($analytics['recentMovements'] as $movement)
                            @php($isStockIn = $movement->type === \App\Models\StockMovement::TYPE_IN)
                            <div class="saas-movement-row">
                                <span class="saas-movement-icon {{ $isStockIn ? 'in' : 'out' }}"><i data-lucide="{{ $isStockIn ? 'arrow-down-left' : 'arrow-up-right' }}"></i></span>
                                <div>
                                    <strong>{{ $movement->ingredient?->name ?? __('messages.deleted_ingredient') }}</strong>
                                    <span>{{ $movement->creator?->name ?? __('messages.system') }} &middot; {{ $movement->created_at->format('d M, H:i') }}</span>
                                </div>
                                <em class="{{ $isStockIn ? 'in' : 'out' }}">{{ $isStockIn ? '+' : '-' }}{{ number_format($movement->quantity, 2) }}</em>
                            </div>
                        @empty
                            <div class="saas-empty"><i data-lucide="activity"></i><strong>{{ __('messages.no_movement_yet') }}</strong><span>{{ __('messages.stock_ledger_entries') }}</span></div>
                        @endforelse
                    </div>
                </article>
            </section>

            <section class="saas-quick-actions">
                <div><span>{{ __('messages.quick_actions') }}</span><h2>{{ __('messages.what_would_you_like_to_do') }}</h2></div>
                <div>
                    <a href="{{ route('inventory.create') }}"><i data-lucide="package-plus"></i><span><strong>{{ __('messages.add_new_item') }}</strong><small>{{ __('messages.create_inventory_record') }}</small></span></a>
                    <a href="{{ route('stock.index') }}"><i data-lucide="history"></i><span><strong>{{ __('messages.stock_movement_history') }}</strong><small>{{ __('messages.review_recent_movements') }}</small></span></a>
                    <a href="{{ route('reports.index') }}"><i data-lucide="file-chart-column"></i><span><strong>{{ __('messages.generate_report') }}</strong><small>{{ __('messages.reports_dashboard') }}</small></span></a>
                </div>
            </section>
        </main>
    </div>
</div>
@endsection
