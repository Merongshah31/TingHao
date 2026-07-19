@extends('layouts.app')

@php
    use App\Support\QuantityFormatter;
    use App\Support\StockPlannerDisplay;

    $cleanUnit = QuantityFormatter::cleanUnit($ingredient->unit);
    $displayIngredientName = StockPlannerDisplay::ingredientName($ingredient->name);
    $expiryDaysRemaining = $predictionInput['expiry_days_remaining'] ?? null;
    $isExpired = is_numeric($expiryDaysRemaining) && (int) $expiryDaysRemaining < 0;
    $pendingPoQuantity = (float) ($predictionInput['pending_po_quantity'] ?? 0);
    $hasPendingPurchaseOrder = $pendingPoQuantity > 0;
    $pendingPurchaseOrder = $restockAvailability['pending_purchase_order'] ?? null;
    $supplierComparison = $restockAvailability['supplier_comparison'] ?? null;
@endphp

@section('content')
<main class="admin-page stock-planner-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">Stock Prediction</p>
                <h1>{{ $displayIngredientName }}</h1>
                <p>Prediction from summarized stock movement, current inventory, expiry, pending purchase order quantity, and supplier lead-time inputs.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('stock-planner.index') }}" class="btn btn-muted">Stock Planner</a>
                <a href="{{ route('inventory.show', $ingredient) }}" class="btn btn-muted">{{ __('messages.view_inventory') }}</a>
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="error-alert">{{ session('error') }}</div>
        @endif

        <section class="stock-prediction-detail-grid">
            <article class="stock-prediction-card stock-prediction-primary">
                <div class="stock-prediction-card-head">
                    <div>
                        <p class="eyebrow">Recommended Action</p>
                        <h2>{{ $prediction['action_label'] ?? 'Unavailable' }}</h2>
                    </div>
                    <span class="status-pill {{ $prediction['action_tone'] ?? 'neutral' }}">
                        {{ $prediction['risk_label'] ?? 'Unknown Risk' }}
                    </span>
                </div>

                @if (! ($prediction['available'] ?? false))
                    <p class="stock-prediction-message">{{ $prediction['message'] ?? 'Prediction service unavailable' }}</p>
                @else
                    <p class="stock-prediction-message">
                        Ting Hao should {{ strtolower($prediction['action_label'] ?? 'monitor') }} for {{ $displayIngredientName }} based on current stock, recent usage, expiry timing, and pending purchase orders.
                    </p>
                @endif

                @if ($isExpired)
                    <div class="stock-safety-alert danger">
                        <strong>Expired stock requires review</strong>
                        <span>This item is past expiry. Review or remove expired stock before restocking.</span>
                    </div>
                @endif

                @if ($hasPendingPurchaseOrder)
                    <div class="stock-safety-alert warning">
                        <strong>Pending purchase order</strong>
                        <span>A pending purchase order already exists for this item.</span>
                        <a href="{{ $pendingPurchaseOrder ? route('purchase-orders.show', $pendingPurchaseOrder) : route('purchase-orders.index') }}">
                            {{ $pendingPurchaseOrder ? 'View Pending Purchase Order' : 'View Purchase Orders' }}
                        </a>
                    </div>
                @endif

                <div class="stock-prediction-metrics">
                    <div>
                        <span>Estimated Stockout</span>
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
                            <span>Suggested Quantity</span>
                            <strong>{{ QuantityFormatter::format($prediction['suggested_quantity'], $cleanUnit) }}</strong>
                        </div>
                    @else
                        <div>
                            <span>Purchase Advice</span>
                            <strong>{{ $prediction['purchase_guidance'] ?? 'No purchase suggested.' }}</strong>
                        </div>
                    @endif
                    <div>
                        <span>Confidence</span>
                        <strong>{{ $prediction['confidence_percent'] ?? 0 }}%</strong>
                    </div>
                    <div>
                        <span>Predicted At</span>
                        <strong>{{ $prediction['predicted_at'] ?? 'Not available' }}</strong>
                    </div>
                </div>

                <div class="reason-badge-list">
                    @forelse (($prediction['reason_labels'] ?? []) as $reason)
                        <span>{{ $reason }}</span>
                    @empty
                        <span>No reason codes</span>
                    @endforelse
                </div>

                <div class="stock-prediction-actions">
                    <form method="post" action="{{ route('stock-planner.refresh-prediction', $ingredient) }}">
                        @csrf
                        <button type="submit" class="btn btn-muted">Refresh Prediction</button>
                    </form>

                    @if ($isExpired)
                        <a href="{{ route('expiry.index') }}" class="btn btn-primary">Review Expired Stock</a>
                    @elseif ($hasPendingPurchaseOrder)
                        <a href="{{ $pendingPurchaseOrder ? route('purchase-orders.show', $pendingPurchaseOrder) : route('purchase-orders.index') }}" class="btn btn-primary">
                            {{ $pendingPurchaseOrder ? 'View Pending Purchase Order' : 'View Purchase Orders' }}
                        </a>
                    @elseif ($restockAvailability['allowed'])
                        <form method="post" action="{{ route('stock-planner.plan-restock', $ingredient) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">Plan Restock with TingHao Agent</button>
                        </form>
                    @elseif (in_array($prediction['recommended_action'] ?? null, ['add_stock_now', 'add_stock_soon'], true))
                        <span class="stock-advice-static">{{ $restockAvailability['message'] }}</span>
                    @elseif (($prediction['recommended_action'] ?? null) === 'use_before_expiry')
                        <a href="{{ route('expiry.index') }}" class="btn btn-primary">Open Expiry Page</a>
                    @elseif (($prediction['recommended_action'] ?? null) === 'do_not_buy')
                        <span class="stock-advice-static">Buying is not recommended for this item right now.</span>
                    @elseif (($prediction['recommended_action'] ?? null) === 'buy_less')
                        <span class="stock-advice-static">Review usage before purchasing.</span>
                    @elseif (($prediction['recommended_action'] ?? null) === 'monitor')
                        <span class="stock-advice-static">Continue monitoring stock movement.</span>
                    @endif
                </div>
            </article>

            <article class="stock-prediction-card">
                <div class="stock-prediction-card-head">
                    <div>
                        <p class="eyebrow">Compact Input Sent</p>
                        <h2>Laravel Summary</h2>
                    </div>
                </div>
                <dl class="stock-prediction-input-list">
                    <div><dt>Current Quantity</dt><dd>{{ QuantityFormatter::format($predictionInput['current_quantity'], $cleanUnit) }}</dd></div>
                    <div><dt>Minimum Stock</dt><dd>{{ QuantityFormatter::format($predictionInput['minimum_stock'], $cleanUnit) }}</dd></div>
                    <div><dt>Stock Out 7 Days</dt><dd>{{ QuantityFormatter::format($predictionInput['stock_out_last_7_days'], $cleanUnit) }}</dd></div>
                    <div><dt>Stock Out 14 Days</dt><dd>{{ QuantityFormatter::format($predictionInput['stock_out_last_14_days'], $cleanUnit) }}</dd></div>
                    <div><dt>Stock Out 30 Days</dt><dd>{{ QuantityFormatter::format($predictionInput['stock_out_last_30_days'], $cleanUnit) }}</dd></div>
                    <div><dt>Pending PO Quantity</dt><dd>{{ QuantityFormatter::format($predictionInput['pending_po_quantity'], $cleanUnit) }}</dd></div>
                    <div><dt>Expiry Days Remaining</dt><dd>{{ $predictionInput['expiry_days_remaining'] ?? 'Not set' }}</dd></div>
                    <div><dt>Supplier Lead Time</dt><dd>{{ $predictionInput['supplier_lead_time_days'] }} day(s)</dd></div>
                    <div><dt>Weekend Near</dt><dd>{{ $predictionInput['weekend_near'] ? 'Yes' : 'No' }}</dd></div>
                    <div><dt>Festival Near</dt><dd>{{ $predictionInput['festival_near'] ? 'Yes' : 'No' }}</dd></div>
                </dl>
            </article>
        </section>

        @if (! empty($supplierComparison['suppliers']))
            <section class="info-panel supplier-comparison-panel">
                <div class="section-heading-row">
                    <div>
                        <p class="eyebrow">Supplier Comparison</p>
                        <h2>Evidence for the restock recommendation</h2>
                        <p>Ranking uses only available TingHao purchase and receiving records. Missing evidence is shown as insufficient history.</p>
                    </div>
                    <span class="status-pill info">{{ count($supplierComparison['suppliers']) }} eligible supplier(s)</span>
                </div>
                <div class="responsive-table">
                    <table class="data-table supplier-comparison-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Supplier</th>
                                <th>Latest Price</th>
                                <th>Average Price</th>
                                <th>Lead Time</th>
                                <th>Receiving Quality</th>
                                <th>Contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($supplierComparison['suppliers'] as $supplierOption)
                                <tr>
                                    <td><strong>#{{ $supplierOption['rank'] }}</strong></td>
                                    <td>
                                        <strong>{{ $supplierOption['name'] }}</strong>
                                        <span>{{ $supplierOption['history_label'] }}</span>
                                    </td>
                                    <td>{{ $supplierOption['latest_item_price'] !== null ? 'RM '.number_format($supplierOption['latest_item_price'], 2) : 'Insufficient history' }}</td>
                                    <td>{{ $supplierOption['average_historical_price'] !== null ? 'RM '.number_format($supplierOption['average_historical_price'], 2) : 'Insufficient history' }}</td>
                                    <td>{{ $supplierOption['estimated_lead_time_days'] !== null ? $supplierOption['estimated_lead_time_days'].' day(s)' : 'Insufficient history' }}</td>
                                    <td>
                                        @if ($supplierOption['completed_item_records'] > 0)
                                            {{ number_format((float) $supplierOption['damaged_quantity'], 2) }} damaged,
                                            {{ number_format((float) $supplierOption['returned_quantity'], 2) }} returned,
                                            {{ number_format((float) $supplierOption['shortage_quantity'], 2) }} short
                                        @else
                                            Insufficient history
                                        @endif
                                    </td>
                                    <td>{{ $supplierOption['contact_available'] ? 'Available' : 'Missing' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="field-help">The first-ranked supplier is used for the draft. Admin can choose another supplier on the purchase order edit page before approval.</p>
            </section>
        @endif

        <section class="qwen-explanation-panel">
            <div class="stock-prediction-card-head">
                <div>
                    <p class="eyebrow">Qwen Explanation</p>
                    <h2>Business-Friendly Reasoning</h2>
                </div>
                @if (($qwenExplanation['available'] ?? false) && ($qwenExplanation['cache_hit'] ?? false))
                    <span class="status-pill ok">AI explanation generated from latest prediction.</span>
                @elseif (($qwenExplanation['available'] ?? false))
                    <span class="status-pill info">Generated now</span>
                @else
                    <span class="status-pill warning">Temporarily unavailable</span>
                @endif
            </div>

            @if (($qwenExplanation['available'] ?? false))
                <div class="qwen-explanation-grid">
                    <article>
                        <span>Title</span>
                        <strong>{{ $qwenExplanation['title'] }}</strong>
                    </article>
                    <article>
                        <span>Confidence</span>
                        <strong>{{ $qwenExplanation['confidence_label'] }}</strong>
                    </article>
                    <article class="wide">
                        <span>Summary</span>
                        <p>{{ $qwenExplanation['summary'] }}</p>
                    </article>
                    <article class="wide">
                        <span>Business Reason</span>
                        <p>{{ $qwenExplanation['business_reason'] }}</p>
                    </article>
                    @if ($qwenExplanation['warning'])
                        <article class="wide warning">
                            <span>Warning</span>
                            <p>{{ $qwenExplanation['warning'] }}</p>
                        </article>
                    @endif
                    <article class="wide">
                        <span>Recommended Next Step</span>
                        <p>{{ $qwenExplanation['recommended_next_step'] }}</p>
                    </article>
                </div>
            @else
                <p class="stock-prediction-message">{{ $qwenExplanation['message'] ?? 'Prediction is available, but AI explanation is temporarily unavailable.' }}</p>
            @endif

            <div class="stock-prediction-actions">
                <form method="post" action="{{ route('stock-planner.explain', $ingredient) }}">
                    @csrf
                    <button type="submit" class="btn btn-muted">
                        {{ ($qwenExplanation['available'] ?? false) ? 'Regenerate English Explanation' : 'Generate English Explanation' }}
                    </button>
                </form>
                @if (($prediction['recommended_action'] ?? null) === 'use_before_expiry')
                    <a href="{{ route('expiry.index') }}" class="btn btn-primary">View Expiry Save Plan</a>
                @endif
            </div>
        </section>

        <details class="advanced-details-panel">
            <summary>Technical Audit Details</summary>
            <p class="technical-audit-note">For judges/developers only. No API keys or raw chain-of-thought are shown.</p>
            <dl class="stock-prediction-input-list">
                <div><dt>Qwen Model</dt><dd>{{ data_get($qwenExplanation, 'qwen_metadata.model', 'qwen-plus') }}</dd></div>
                <div><dt>Mock Mode</dt><dd>{{ data_get($qwenExplanation, 'qwen_metadata.mock_mode') ? 'true' : 'false' }}</dd></div>
                <div><dt>Latency</dt><dd>{{ data_get($qwenExplanation, 'qwen_metadata.latency_ms') !== null ? data_get($qwenExplanation, 'qwen_metadata.latency_ms').' ms' : 'Not available' }}</dd></div>
                <div><dt>Total Tokens</dt><dd>{{ data_get($qwenExplanation, 'qwen_metadata.total_tokens') ?? 'Not available' }}</dd></div>
                <div><dt>Max Tokens</dt><dd>{{ data_get($qwenExplanation, 'qwen_metadata.max_tokens') ?? 'Not available' }}</dd></div>
                <div><dt>Cache</dt><dd>{{ ($qwenExplanation['cache_hit'] ?? false) ? 'hit' : 'miss' }}</dd></div>
            </dl>
            <pre>{{ json_encode([
                'fastapi_prediction' => $prediction['raw_response'] ?? $prediction,
                'qwen_audit_metadata' => $qwenExplanation['qwen_metadata'] ?? [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
    </section>
</main>
@endsection
