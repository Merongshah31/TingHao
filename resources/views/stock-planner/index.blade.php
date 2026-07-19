@extends('layouts.app')

@php
    use App\Support\QuantityFormatter;
    use App\Support\StockPlannerDisplay;

    $topSignal = $selectedSignals[0] ?? null;
@endphp

@section('content')
<main class="admin-page stock-planner-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">Stock Planning Signals</p>
                <h1>Stock Planner</h1>
                <p>Predict when to add stock, buy less, avoid buying, or use stock before expiry.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">{{ __('messages.dashboard') }}</a>
                <a href="{{ route('alerts.low-stock') }}" class="btn btn-primary">{{ __('messages.low_stock') }}</a>
            </div>
        </div>

        <div class="planner-view-switcher" aria-label="Stock Planner view switcher">
            <a @class(['active' => $activeView === 'cards']) href="{{ route('stock-planner.index', ['view' => 'cards']) }}">Prediction View</a>
            <a @class(['active' => $activeView === 'calendar']) href="{{ route('stock-planner.index', ['view' => 'calendar', 'date' => $selectedDate]) }}">Calendar View</a>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="error-alert">{{ session('error') }}</div>
        @endif

        @if ($activeView === 'calendar')
            <section class="stock-calendar-layout real-stock-calendar">
                <article class="stock-calendar-card">
                    <div class="stock-calendar-head">
                        <div>
                            <p class="eyebrow">Calendar View</p>
                            <h2>{{ $calendarMonth->format('F Y') }}</h2>
                        </div>
                    </div>

                    <div class="stock-calendar-scroll" role="region" aria-label="{{ $calendarMonth->format('F Y') }} stock signals" tabindex="0">
                        <div class="stock-calendar-weekdays">
                            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                                <span>{{ $weekday }}</span>
                            @endforeach
                        </div>

                        <div class="stock-calendar-grid">
                            @foreach ($calendarDays as $day)
                                <a @class([
                                    'stock-calendar-day',
                                    'muted' => ! $day['in_month'],
                                    'today' => $day['is_today'],
                                    'is-selected' => $day['is_selected'],
                                    'has-advice' => count($day['signals']) > 0,
                                ])
                                    href="{{ route('stock-planner.index', ['view' => 'calendar', 'date' => $day['key']]) }}"
                                >
                                    <div class="day-number">{{ $day['day'] }}</div>
                                    @foreach ($day['signals'] as $signal)
                                        <span class="calendar-badge badge-{{ $signal['action_tone'] }}">{{ $signal['action_label'] }}</span>
                                    @endforeach
                                    @if ($day['more_count'] > 0)
                                        <strong>+{{ $day['more_count'] }} in advice</strong>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </article>

                <aside class="stock-advice-panel">
                    <p class="eyebrow">Date Advice</p>
                    <h2>{{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d M Y') }}</h2>

                    @if ($topSignal)
                        <span class="calendar-badge badge-{{ $topSignal['action_tone'] }}">{{ $topSignal['action_label'] }}</span>
                        <h3>{{ $topSignal['ingredient_name'] }}</h3>
                        <p>{{ $topSignal['supplier_name'] ?? __('messages.no_supplier') }}</p>

                        <dl>
                            <div>
                                <dt>Current Stock</dt>
                                <dd>{{ QuantityFormatter::format($topSignal['current_quantity'], $topSignal['unit']) }}</dd>
                            </div>
                            <div>
                                <dt>Minimum Stock</dt>
                                <dd>{{ QuantityFormatter::format($topSignal['minimum_stock'], $topSignal['unit']) }}</dd>
                            </div>
                            <div>
                                <dt>Stockout Estimate</dt>
                                <dd>{{ $topSignal['estimated_days_until_stockout'] !== null ? $topSignal['estimated_days_until_stockout'].' day(s)' : 'Unknown' }}</dd>
                            </div>
                            @if ($topSignal['suggested_quantity'] !== null)
                                <div>
                                    <dt>Suggested Quantity</dt>
                                    <dd>{{ QuantityFormatter::format($topSignal['suggested_quantity'], $topSignal['unit']) }}</dd>
                                </div>
                            @else
                                <div>
                                    <dt>Purchase Advice</dt>
                                    <dd>{{ $topSignal['purchase_guidance'] }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt>Risk Level</dt>
                                <dd>{{ $topSignal['risk_label'] }}</dd>
                            </div>
                        </dl>

                        <div class="reason-badge-list">
                            @forelse ($topSignal['reason_labels'] as $reason)
                                <span>{{ $reason }}</span>
                            @empty
                                <span>No reason codes</span>
                            @endforelse
                        </div>

                        <div class="stock-prediction-actions">
                            @if ($topSignal['can_plan_restock'])
                                <form method="post" action="{{ $topSignal['agent_plan_url'] }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary">Plan Restock with TingHao Agent</button>
                                </form>
                            @elseif (in_array($topSignal['recommended_action'], ['add_stock_now', 'add_stock_soon'], true))
                                <span class="stock-advice-static">{{ $topSignal['restock_guidance'] }}</span>
                                @if ($topSignal['pending_purchase_order_url'])
                                    <a class="btn btn-muted" href="{{ $topSignal['pending_purchase_order_url'] }}">View Pending Purchase Order</a>
                                @else
                                    <form method="post" action="{{ $topSignal['refresh_url'] }}">
                                        @csrf
                                        <button type="submit" class="btn btn-muted">Refresh Prediction</button>
                                    </form>
                                @endif
                            @elseif ($topSignal['display_action'] === 'review_expired_stock')
                                <a class="btn btn-primary" href="{{ $topSignal['expiry_url'] }}">Review Expired Stock</a>
                            @elseif ($topSignal['recommended_action'] === 'do_not_buy')
                                <a class="btn btn-muted" href="{{ $topSignal['detail_url'] }}">View Do Not Buy Advice</a>
                            @elseif ($topSignal['recommended_action'] === 'buy_less')
                                <a class="btn btn-muted" href="{{ route('stock.index') }}">Review Stock Usage</a>
                            @elseif ($topSignal['recommended_action'] === 'use_before_expiry')
                                <a class="btn btn-primary" href="{{ $topSignal['expiry_url'] }}">View Expiry Save Plan</a>
                            @else
                                <a class="btn btn-muted" href="{{ $topSignal['inventory_url'] }}">View Inventory</a>
                            @endif

                            <form method="post" action="{{ $topSignal['explain_url'] }}">
                                @csrf
                                <button type="submit" class="btn btn-muted">Explain with Qwen</button>
                            </form>
                            <a class="btn btn-muted" href="{{ $topSignal['detail_url'] }}">View Details</a>
                        </div>

                        @if (count($selectedSignals) > 1)
                            <div class="planner-extra-signals">
                                <strong>Other signals on this date</strong>
                                @foreach (array_slice($selectedSignals, 1, 4) as $signal)
                                    <a href="{{ $signal['detail_url'] }}">{{ $signal['ingredient_name'] }} - {{ $signal['action_label'] }}</a>
                                @endforeach
                                @if (count($selectedSignals) > 5)
                                    <span>+{{ count($selectedSignals) - 5 }} more signals available in Prediction View</span>
                                @endif
                            </div>
                        @endif
                    @else
                        <span class="calendar-badge badge-neutral">No urgent signal</span>
                        <h3>No stock action for this date</h3>
                        <p>Monitor labels are kept out of the calendar to avoid clutter. Open Prediction View for all ingredients.</p>
                        <a class="btn btn-primary" href="{{ route('stock-planner.index', ['view' => 'cards']) }}">Open Prediction View</a>
                    @endif
                </aside>
            </section>
        @else
            <section class="stock-prediction-grid">
                @forelse ($ingredients as $ingredient)
                    @php($prediction = $predictions->get($ingredient->id))
                    @php($cleanUnit = QuantityFormatter::cleanUnit($ingredient->unit))
                    @php($displayIngredientName = StockPlannerDisplay::ingredientName($ingredient->name))
                    @php($displaySupplierName = StockPlannerDisplay::supplierName($ingredient->supplier?->name))
                    <article class="stock-prediction-card">
                        <div class="stock-prediction-card-head">
                            <div>
                                <h2>{{ $displayIngredientName }}</h2>
                                <p class="stock-card-supplier">{{ $displaySupplierName ?? __('messages.no_supplier') }}</p>
                            </div>
                            <span class="status-pill {{ $prediction['action_tone'] ?? 'neutral' }}">
                                {{ $prediction['action_label'] ?? 'Unavailable' }}
                            </span>
                        </div>

                        <div class="stock-prediction-metrics">
                            <div>
                                <span>Current</span>
                                <strong>{{ QuantityFormatter::format($ingredient->quantity, $cleanUnit) }}</strong>
                            </div>
                            <div>
                                <span>Minimum</span>
                                <strong>{{ QuantityFormatter::format($ingredient->minimum_stock, $cleanUnit) }}</strong>
                            </div>
                            <div>
                                <span>Stockout</span>
                                <strong>
                                    @if (($prediction['estimated_days_until_stockout'] ?? null) !== null)
                                        {{ $prediction['estimated_days_until_stockout'] }} day(s)
                                    @else
                                        Unknown
                                    @endif
                                </strong>
                            </div>
                            @if (($prediction['suggested_quantity'] ?? null) !== null)
                                <div>
                                    <span>Suggested</span>
                                    <strong>{{ QuantityFormatter::format($prediction['suggested_quantity'], $cleanUnit) }}</strong>
                                </div>
                            @else
                                <div>
                                    <span>Purchase Advice</span>
                                    <strong>{{ $prediction['purchase_guidance'] ?? 'No purchase suggested.' }}</strong>
                                </div>
                            @endif
                        </div>

                        @if (! ($prediction['available'] ?? false))
                            <p class="stock-prediction-message">{{ $prediction['message'] ?? 'Prediction service unavailable' }}</p>
                        @else
                            <div class="stock-prediction-meta">
                                <span>Risk: {{ $prediction['risk_label'] ?? 'Unknown' }}</span>
                                <span>Confidence: {{ $prediction['confidence_percent'] ?? 0 }}%</span>
                            </div>
                            <div class="reason-badge-list">
                                @forelse (($prediction['reason_labels'] ?? []) as $reason)
                                    <span>{{ $reason }}</span>
                                @empty
                                    <span>No reason codes</span>
                                @endforelse
                            </div>
                        @endif

                        <div class="stock-prediction-actions">
                            <form method="post" action="{{ route('stock-planner.refresh-prediction', $ingredient) }}">
                                @csrf
                                <button type="submit" class="btn btn-muted">Refresh Prediction</button>
                            </form>
                            <a href="{{ route('stock-planner.prediction', $ingredient) }}" class="btn btn-primary">View Details</a>
                        </div>
                    </article>
                @empty
                    <article class="stock-prediction-card">
                        <h2>No ingredients found</h2>
                        <p class="stock-prediction-message">Create inventory ingredients before running stock predictions.</p>
                    </article>
                @endforelse
            </section>

            <div class="pagination-wrap">
                {{ $ingredients->appends(['view' => 'cards'])->links() }}
            </div>
        @endif
    </section>
</main>
@endsection
