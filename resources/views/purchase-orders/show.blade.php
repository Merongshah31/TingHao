@extends('layouts.app')

@section('content')
@php
    $latestEmailDraft = $purchaseOrder->latestSupplierEmailDraft;
    $isAdmin = auth()->user()->isAdmin();
    $requiresApproval = (bool) $purchaseOrder->agentRun || (bool) $purchaseOrder->approvalRequest;
    $isRejected = $purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_REJECTED;
    $isClosed = $purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_CLOSED;
    $isCancelled = $purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_CANCELLED;
    $approvedStatuses = [
        \App\Models\PurchaseOrder::STATUS_APPROVED,
        \App\Models\PurchaseOrder::STATUS_SENT,
        \App\Models\PurchaseOrder::STATUS_CONFIRMED,
        \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        \App\Models\PurchaseOrder::STATUS_RECEIVED,
        \App\Models\PurchaseOrder::STATUS_CLOSED,
    ];
    $approvalDisplay = ! $requiresApproval
        ? 'Not required'
        : match (true) {
            $isRejected => 'Rejected by admin',
            in_array($purchaseOrder->status, $approvedStatuses, true) => 'Approved by admin',
            default => 'Pending admin approval',
        };
    $approvalTone = match (true) {
        $isRejected => 'danger',
        $approvalDisplay === 'Approved by admin' => 'ok',
        $approvalDisplay === 'Pending admin approval' => 'warning',
        default => 'neutral',
    };

    if ($requiresApproval) {
        if ($isRejected) {
            $workflowSteps = [
                ['label' => 'PO Drafted', 'state' => 'completed'],
                ['label' => 'Rejected by Admin', 'state' => 'rejected'],
            ];
        } else {
            $isApproved = in_array($purchaseOrder->status, $approvedStatuses, true);
            $isEmailMarkedSent = $latestEmailDraft?->status === \App\Models\SupplierEmailDraft::STATUS_SENT
                || in_array($purchaseOrder->status, [
                    \App\Models\PurchaseOrder::STATUS_SENT,
                    \App\Models\PurchaseOrder::STATUS_CONFIRMED,
                    \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    \App\Models\PurchaseOrder::STATUS_RECEIVED,
                    \App\Models\PurchaseOrder::STATUS_CLOSED,
                ], true);
            $isEmailApproved = in_array($latestEmailDraft?->status, [
                \App\Models\SupplierEmailDraft::STATUS_APPROVED,
                \App\Models\SupplierEmailDraft::STATUS_SENT,
            ], true) || $isEmailMarkedSent;
            $isEmailDrafted = (bool) $latestEmailDraft || $isEmailApproved;

            $workflowSteps = [
                ['label' => 'PO Drafted', 'state' => 'completed'],
                ['label' => 'Admin Approved', 'state' => $isApproved ? 'completed' : 'current'],
                ['label' => 'Email Drafted', 'state' => $isEmailDrafted ? 'completed' : ($isApproved ? 'current' : 'future')],
                ['label' => 'Email Approved', 'state' => $isEmailApproved ? 'completed' : ($isEmailDrafted ? 'current' : 'future')],
                ['label' => 'Marked Sent', 'state' => $isEmailMarkedSent ? 'completed' : ($isEmailApproved ? 'current' : 'future')],
            ];
        }

        $workflowLabel = 'Agent Purchase Order Workflow';
    } else {
        $manualEmailMarkedSent = (bool) $purchaseOrder->sent_at
            || $latestEmailDraft?->status === \App\Models\SupplierEmailDraft::STATUS_SENT;
        $manualEmailLabel = ! $purchaseOrder->sent_at && $latestEmailDraft?->status === \App\Models\SupplierEmailDraft::STATUS_SENT
            ? 'Marked Sent'
            : 'Email Sent';
        $manualSupplierConfirmed = (bool) $purchaseOrder->confirmed_at
            || in_array($purchaseOrder->status, [
                \App\Models\PurchaseOrder::STATUS_CONFIRMED,
                \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                \App\Models\PurchaseOrder::STATUS_RECEIVED,
                \App\Models\PurchaseOrder::STATUS_CLOSED,
            ], true);
        $manualReceived = in_array($purchaseOrder->status, [
            \App\Models\PurchaseOrder::STATUS_RECEIVED,
            \App\Models\PurchaseOrder::STATUS_CLOSED,
        ], true);
        $manualClosed = $purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_CLOSED;
        $manualCurrentStep = match ($purchaseOrder->status) {
            \App\Models\PurchaseOrder::STATUS_CLOSED => null,
            \App\Models\PurchaseOrder::STATUS_RECEIVED => 4,
            \App\Models\PurchaseOrder::STATUS_CONFIRMED,
            \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 3,
            \App\Models\PurchaseOrder::STATUS_SENT => $manualEmailMarkedSent ? 2 : 1,
            \App\Models\PurchaseOrder::STATUS_DRAFT => $manualEmailMarkedSent ? 2 : 1,
            default => 0,
        };

        $workflowSteps = collect([
            ['label' => 'Draft', 'completed' => true],
            ['label' => $manualEmailLabel, 'completed' => $manualEmailMarkedSent],
            ['label' => 'Supplier Confirmed', 'completed' => $manualSupplierConfirmed],
            ['label' => 'Received', 'completed' => $manualReceived],
            ['label' => 'Closed', 'completed' => $manualClosed],
        ])->map(fn (array $step, int $index): array => [
            'label' => $step['label'],
            'state' => $step['completed'] ? 'completed' : ($index === $manualCurrentStep ? 'current' : 'future'),
        ])->all();

        if ($isRejected || $isCancelled) {
            $workflowSteps = [
                ['label' => 'Draft', 'state' => 'completed'],
                ['label' => $isRejected ? 'Rejected' : 'Cancelled', 'state' => 'rejected'],
            ];
        }

        $workflowLabel = 'Manual Purchase Order Workflow';
    }

    $nextStep = match ($purchaseOrder->status) {
        \App\Models\PurchaseOrder::STATUS_DRAFT => 'Send or prepare supplier email',
        \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL => 'Wait for admin approval',
        \App\Models\PurchaseOrder::STATUS_APPROVED => $latestEmailDraft ? 'Review supplier email draft' : 'Generate supplier email draft',
        \App\Models\PurchaseOrder::STATUS_SENT => 'Wait for supplier confirmation',
        \App\Models\PurchaseOrder::STATUS_CONFIRMED => 'Receive goods',
        \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'Continue receiving goods',
        \App\Models\PurchaseOrder::STATUS_RECEIVED => 'Close purchase order',
        \App\Models\PurchaseOrder::STATUS_CLOSED => 'Completed',
        \App\Models\PurchaseOrder::STATUS_REJECTED,
        \App\Models\PurchaseOrder::STATUS_CANCELLED => 'No further action',
        default => 'Review purchase order',
    };
    $canEditPurchaseOrder = $isAdmin && in_array($purchaseOrder->status, [
        \App\Models\PurchaseOrder::STATUS_DRAFT,
        \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL,
        \App\Models\PurchaseOrder::STATUS_APPROVED,
        \App\Models\PurchaseOrder::STATUS_SENT,
    ], true);
    $totalOrdered = $purchaseOrder->items->sum(fn ($item) => (float) $item->quantity);
    $totalReceived = $purchaseOrder->items->sum(fn ($item) => (float) $item->received_quantity);
    $totalAccepted = $purchaseOrder->items->sum(fn ($item) => (float) ($item->accepted_quantity ?? 0));
    $totalDamaged = $purchaseOrder->items->sum(fn ($item) => (float) ($item->damaged_quantity ?? 0));
    $totalReturned = $purchaseOrder->items->sum(fn ($item) => (float) ($item->returned_quantity ?? 0));
    $totalShortage = $purchaseOrder->items->sum(fn ($item) => (float) ($item->shortage_quantity ?? 0));
    $allocationSummary = $purchaseOrder->stockAllocations
        ->groupBy(fn ($allocation) => $allocation->stockLocation?->name ?? __('messages.not_set'))
        ->map(fn ($allocations) => $allocations->sum(fn ($allocation) => (float) $allocation->quantity));
    $canReceiveStock = $purchaseOrder->canReceiveStock();
    $agentItemSummary = $purchaseOrder->items
        ->map(fn ($item) => ($item->ingredient?->name ?? $item->description ?? __('messages.ingredient')).' - '.number_format((float) $item->quantity, 2).' '.$item->unit)
        ->implode(', ');
    $agentBusinessSummary = $purchaseOrder->agentRun?->final_summary
        ?: $purchaseOrder->agent_reasoning
        ?: 'TingHao Agent prepared this purchase order from an autopilot mission and linked it for admin review.';
    $isStockPredictionPo = $purchaseOrder->agentRun?->input_type === 'stock_prediction_restock';
    $predictionContext = $isStockPredictionPo ? data_get($purchaseOrder->agentRun?->parsed_intent, 'stock_prediction', []) : [];
    $qwenContext = $isStockPredictionPo ? data_get($purchaseOrder->agentRun?->parsed_intent, 'qwen_explanation') : null;
    $supplierComparison = $purchaseOrder->agentRun ? data_get($purchaseOrder->agentRun->parsed_intent, 'supplier_comparison', []) : [];
    $realEmailEnabled = (bool) ($emailDelivery['enabled'] ?? false);
    $realEmailConfigured = (bool) ($emailDelivery['configured'] ?? false);
@endphp

<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.purchase_order') }}</p>
                <h1>{{ $purchaseOrder->po_number }}</h1>
                <p>{{ $purchaseOrder->supplier?->name ?? __('messages.not_set') }}</p>
                @if ($isStockPredictionPo)
                    <span class="status-pill info">Created from Stock Prediction</span>
                @endif
            </div>
            <div class="page-actions">
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-muted">{{ __('messages.back') }}</a>
                @if ($canEditPurchaseOrder)
                    <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="btn btn-muted">{{ __('messages.edit') }}</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="error-alert">{{ $errors->first() }}</div>
        @endif

        <div class="info-panel po-summary-panel">
            <dl>
                <div><dt>{{ __('messages.status') }}</dt><dd><span class="status-pill po-status-{{ $purchaseOrder->status }}">{{ str_replace('_', ' ', __('messages.'.$purchaseOrder->status)) }}</span></dd></div>
                <div><dt>Approval</dt><dd><span class="status-pill {{ $approvalTone }}">{{ $approvalDisplay }}</span></dd></div>
                <div><dt>{{ __('messages.order_date') }}</dt><dd>{{ $purchaseOrder->order_date?->format('d M Y') ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.expected_delivery_date') }}</dt><dd>{{ $purchaseOrder->expected_delivery_date?->format('d M Y') ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.email_to') }}</dt><dd>{{ $purchaseOrder->email_to ?: $purchaseOrder->supplier?->email ?: __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.sent_at') }}</dt><dd>{{ $purchaseOrder->sent_at?->format('d M Y H:i') ?? __('messages.not_sent') }}</dd></div>
                <div><dt>{{ __('messages.confirmed_at') }}</dt><dd>{{ $purchaseOrder->confirmed_at?->format('d M Y H:i') ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.received_at') }}</dt><dd>{{ $purchaseOrder->received_at?->format('d M Y H:i') ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.closed_at') }}</dt><dd>{{ $purchaseOrder->closed_at?->format('d M Y H:i') ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.created_by') }}</dt><dd>{{ $purchaseOrder->creator?->name ?? __('messages.system') }}</dd></div>
                <div><dt>Requested by</dt><dd>{{ $purchaseOrder->requestedBy?->name ?? $purchaseOrder->creator?->name ?? __('messages.system') }}</dd></div>
                <div><dt>Approved by</dt><dd>{{ $requiresApproval ? ($purchaseOrder->approvedBy?->name ?? __('messages.not_set')) : __('messages.not_applicable') }}</dd></div>
            </dl>
        </div>

        <section class="po-workflow-section" aria-label="{{ $workflowLabel }}">
            <div class="section-heading-row no-padding">
                <div>
                    <p class="eyebrow">Workflow</p>
                    <h2>{{ $workflowLabel }}</h2>
                </div>
            </div>
            <div class="po-demo-timeline po-status-timeline">
                @foreach ($workflowSteps as $index => $step)
                    <div class="{{ $step['state'] }}">
                        <span>{{ $index + 1 }}</span>
                        <strong>{{ $step['label'] }}</strong>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="info-panel po-next-step-panel">
            <div>
                <p class="eyebrow">Next step</p>
                <h2>{{ $nextStep }}</h2>
                @if (in_array($purchaseOrder->status, [
                    \App\Models\PurchaseOrder::STATUS_DRAFT,
                    \App\Models\PurchaseOrder::STATUS_APPROVED,
                    \App\Models\PurchaseOrder::STATUS_SENT,
                ], true) || $latestEmailDraft)
                    <p class="po-email-safety-note">No real email is sent automatically. Admin controls the final action.</p>
                @endif
                @if (! $isAdmin && $purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL)
                    <p class="agent-summary">An admin must approve or reject this purchase order before supplier communication continues.</p>
                @endif
            </div>

            <div class="po-demo-actions po-next-step-actions">
                @if ($isAdmin && $purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_DRAFT && ! $requiresApproval)
                    <form action="{{ route('purchase-orders.send-email', $purchaseOrder) }}" method="post">
                        @csrf
                        <button type="submit" class="btn btn-primary">{{ __('messages.send_email_to_supplier') }}</button>
                    </form>
                @endif

                @if ($isAdmin && $purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL)
                    <form method="post" action="{{ route('purchase-orders.approve', $purchaseOrder) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">Approve PO Draft</button>
                    </form>
                    <form method="post" action="{{ route('purchase-orders.reject', $purchaseOrder) }}" class="po-reject-form">
                        @csrf
                        <input type="text" name="review_notes" placeholder="Optional rejection note">
                        <button class="btn btn-danger" type="submit">Reject PO Draft</button>
                    </form>
                @endif

                @if ($isAdmin && $purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_APPROVED)
                    @if ($latestEmailDraft)
                        <a href="{{ route('supplier-email-drafts.show', $latestEmailDraft) }}" class="btn btn-primary">Review Supplier Email Draft</a>
                    @else
                        <form action="{{ route('purchase-orders.generate-email-draft', $purchaseOrder) }}" method="post">
                            @csrf
                            <button type="submit" class="btn btn-primary">Generate Supplier Email Draft</button>
                        </form>
                    @endif
                @endif

                @if ($isAdmin && $purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_SENT)
                    <form method="post" action="{{ route('purchase-orders.confirm', $purchaseOrder) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">{{ __('messages.mark_supplier_confirmed') }}</button>
                    </form>
                @endif

                @if ($canReceiveStock)
                    <a class="btn btn-primary" href="{{ route('purchase-orders.receive-form', $purchaseOrder) }}">
                        {{ $purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED ? 'Continue Receiving' : 'Receive Goods' }}
                    </a>
                @endif

                @if ($isAdmin && $purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_RECEIVED)
                    <form method="post" action="{{ route('purchase-orders.close', $purchaseOrder) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">{{ __('messages.close_po') }}</button>
                    </form>
                @endif
            </div>
        </section>

        @if ($purchaseOrder->agentRun)
            <section class="mission-grid">
                <article class="autopilot-card simple-decision-card">
                    <p class="eyebrow">Agent Recommendation</p>
                    <h2>{{ $purchaseOrder->po_number }} recommendation</h2>
                    <p class="agent-summary">{{ $agentBusinessSummary }}</p>
                    <div class="agent-detail-list">
                        <div><span>Suggested item and quantity</span><strong>{{ $agentItemSummary ?: __('messages.not_set') }}</strong></div>
                        <div><span>Supplier selected</span><strong>{{ $purchaseOrder->supplier?->name ?? 'No supplier selected' }}</strong></div>
                        <div><span>Human approval state</span><strong>{{ $approvalDisplay }}</strong></div>
                        <div><span>Linked email draft</span><strong>{{ $latestEmailDraft ? ucfirst($latestEmailDraft->status) : 'No draft yet' }}</strong></div>
                    </div>
                    @if ($purchaseOrder->agent_reasoning)
                        <p class="agent-summary"><strong>Why this order is suggested:</strong> {{ str($purchaseOrder->agent_reasoning)->limit(240) }}</p>
                    @endif
                    <p><a href="{{ route('agent.runs.show', $purchaseOrder->agentRun) }}">Back to Agent Mission #{{ $purchaseOrder->agentRun->id }}</a></p>
                </article>

                <article class="safety-card simple-decision-card">
                    <p class="eyebrow">Approval Safety</p>
                    <h2>Agent cannot approve this PO</h2>
                    <ul>
                        <li>Admin approval is required before supplier communication.</li>
                        <li>Supplier email draft generation only appears after approval.</li>
                        <li>All linked reasoning and tool calls remain auditable.</li>
                    </ul>
                </article>
            </section>
        @endif

        @if ($isStockPredictionPo)
            <section class="info-panel po-summary-panel">
                <div class="section-heading-row">
                    <div>
                        <p class="eyebrow">Stock Prediction Approval</p>
                        <h2>Prediction Source: FastAPI Stock Prediction</h2>
                    </div>
                    <span class="status-pill {{ $approvalTone }}">{{ $approvalDisplay }}</span>
                </div>
                <dl>
                    <div><dt>AI Explanation Source</dt><dd>{{ ($qwenContext['available'] ?? false) ? 'Qwen Explanation' : 'Not generated yet' }}</dd></div>
                    <div><dt>{{ __('messages.ingredient') }}</dt><dd>{{ $purchaseOrder->items->first()?->ingredient?->name ?? data_get($predictionContext, 'ingredient', __('messages.not_set')) }}</dd></div>
                    <div><dt>{{ __('messages.supplier') }}</dt><dd>{{ $purchaseOrder->supplier?->name ?? __('messages.not_set') }}</dd></div>
                    <div><dt>Current Quantity</dt><dd>{{ data_get($predictionContext, 'current_quantity', __('messages.not_set')) }}</dd></div>
                    <div><dt>Minimum Stock</dt><dd>{{ data_get($predictionContext, 'minimum_stock', __('messages.not_set')) }}</dd></div>
                    <div><dt>Predicted Action</dt><dd>{{ str_replace('_', ' ', (string) data_get($predictionContext, 'recommended_action', 'unknown')) }}</dd></div>
                    <div><dt>Estimated Stockout</dt><dd>{{ data_get($predictionContext, 'estimated_days_until_stockout') !== null ? data_get($predictionContext, 'estimated_days_until_stockout').' day(s)' : __('messages.not_set') }}</dd></div>
                    <div><dt>Suggested Quantity</dt><dd>{{ data_get($predictionContext, 'suggested_quantity', __('messages.not_set')) }}</dd></div>
                    <div><dt>Risk Level</dt><dd>{{ data_get($predictionContext, 'risk_label') ?? data_get($predictionContext, 'risk_level', __('messages.not_set')) }}</dd></div>
                    <div><dt>Confidence</dt><dd>{{ data_get($predictionContext, 'confidence_percent') !== null ? data_get($predictionContext, 'confidence_percent').'%' : data_get($predictionContext, 'confidence', __('messages.not_set')) }}</dd></div>
                    <div><dt>Workflow</dt><dd>{{ $approvalDisplay }}</dd></div>
                </dl>
                <div class="reason-badge-list">
                    @forelse ((array) data_get($predictionContext, 'reason_labels', []) as $reason)
                        <span>{{ $reason }}</span>
                    @empty
                        @forelse ((array) data_get($predictionContext, 'reason_codes', []) as $reason)
                            <span>{{ str_replace('_', ' ', $reason) }}</span>
                        @empty
                            <span>No reason codes</span>
                        @endforelse
                    @endforelse
                </div>
                @if ($qwenContext['available'] ?? false)
                    <p class="agent-summary"><strong>Qwen explanation:</strong> {{ $qwenContext['summary'] ?? $qwenContext['business_reason'] ?? 'Cached explanation is available.' }}</p>
                @endif
            </section>
        @endif

        @if (! empty($supplierComparison['suppliers']))
            <section class="info-panel supplier-comparison-panel">
                <div class="section-heading-row">
                    <div>
                        <p class="eyebrow">Supplier Decision Evidence</p>
                        <h2>Compared before this draft was created</h2>
                    </div>
                    @if ($canEditPurchaseOrder)
                        <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="btn btn-muted">Choose Another Supplier</a>
                    @endif
                </div>
                <div class="responsive-table">
                    <table class="data-table supplier-comparison-table">
                        <thead><tr><th>Rank</th><th>Supplier</th><th>Latest Price</th><th>Lead Time</th><th>History</th></tr></thead>
                        <tbody>
                            @foreach ($supplierComparison['suppliers'] as $supplierOption)
                                <tr>
                                    <td>#{{ $supplierOption['rank'] }}</td>
                                    <td><strong>{{ $supplierOption['name'] }}</strong></td>
                                    <td>{{ $supplierOption['latest_item_price'] !== null ? 'RM '.number_format($supplierOption['latest_item_price'], 2) : 'Insufficient history' }}</td>
                                    <td>{{ $supplierOption['estimated_lead_time_days'] !== null ? $supplierOption['estimated_lead_time_days'].' day(s)' : 'Insufficient history' }}</td>
                                    <td>{{ $supplierOption['history_label'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($purchaseOrder->agentRun)
            <details class="info-panel advanced-details-panel">
                <summary>{{ __('messages.advanced_details') }}</summary>
                <div class="advanced-details-body">
                    <p class="eyebrow">TingHao Agent</p>
                    <h2>Autonomous Restock Reasoning</h2>
                    <p class="agent-summary">{{ $purchaseOrder->agent_reasoning ?: 'No agent reasoning stored.' }}</p>
                    <p><a href="{{ route('agent.runs.show', $purchaseOrder->agentRun) }}">View linked agent run #{{ $purchaseOrder->agentRun->id }}</a></p>
                    <x-agent.reasoning-activity :steps="$purchaseOrder->agentRun->reasoningSteps" />
                </div>
            </details>
        @endif

        <div class="info-panel">
            <dl>
                <div><dt>{{ __('messages.supplier') }}</dt><dd>{{ $purchaseOrder->supplier?->name ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.contact_person') }}</dt><dd>{{ $purchaseOrder->supplier?->contact_person ?: __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.email') }}</dt><dd>{{ $purchaseOrder->supplier?->email ?: __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.phone') }}</dt><dd>{{ $purchaseOrder->supplier?->phone ?: __('messages.not_set') }}</dd></div>
            </dl>
        </div>

        @if ($totalShortage > 0 || $totalDamaged > 0)
            <section class="info-panel">
                <p class="eyebrow">{{ __('messages.receiving_discrepancy') }}</p>
                <h2>{{ __('messages.goods_receiving') }}</h2>
                @foreach ($purchaseOrder->items as $item)
                    @if ((float) ($item->shortage_quantity ?? 0) > 0)
                        <p><span class="status-pill po-status-rejected">{{ __('messages.shortage') }}</span> {{ __('messages.shortage_detected') }}: {{ number_format((float) $item->shortage_quantity, 2) }} {{ $item->unit }} {{ __('messages.unit_missing_for_delivery') }}</p>
                    @endif
                    @if ((float) ($item->damaged_quantity ?? 0) > 0)
                        <p><span class="status-pill po-status-pending_approval">{{ __('messages.supplier_return_required') }}</span> {{ __('messages.damaged_stock') }}: {{ number_format((float) $item->damaged_quantity, 2) }} {{ $item->unit }} {{ __('messages.return_to_supplier') }}</p>
                    @endif
                @endforeach
            </section>
        @endif

        <div class="table-card movement-preview">
            <div class="section-heading-row">
                <h2>{{ __('messages.items') }}</h2>
                <strong>RM {{ number_format((float) $purchaseOrder->subtotal, 2) }}</strong>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.ingredient') }}</th>
                        <th>{{ __('messages.quantity') }}</th>
                        <th>{{ __('messages.unit_price') }}</th>
                        <th>{{ __('messages.line_total') }}</th>
                        <th>{{ __('messages.received_quantity') }}</th>
                        <th>{{ __('messages.accepted_quantity') }}</th>
                        <th>{{ __('messages.damaged_quantity') }}</th>
                        <th>{{ __('messages.shortage_quantity') }}</th>
                        <th>{{ __('messages.quality_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseOrder->items as $item)
                        <tr>
                            <td><strong>{{ $item->ingredient?->name ?? $item->description }}</strong><span>{{ $item->description }}</span></td>
                            <td>{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</td>
                            <td>RM {{ number_format((float) $item->unit_price, 2) }}</td>
                            <td>RM {{ number_format((float) $item->line_total, 2) }}</td>
                            <td>{{ number_format((float) $item->received_quantity, 2) }} {{ $item->unit }}</td>
                            <td>{{ number_format((float) ($item->accepted_quantity ?? 0), 2) }} {{ $item->unit }}</td>
                            <td>{{ number_format((float) ($item->damaged_quantity ?? 0), 2) }} {{ $item->unit }}</td>
                            <td>{{ number_format((float) ($item->shortage_quantity ?? 0), 2) }} {{ $item->unit }}</td>
                            <td>{{ $item->quality_status ? __('messages.'.$item->quality_status) : __('messages.not_set') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <section class="info-panel po-summary-panel">
            <div class="section-heading-row">
                <div>
                    <p class="eyebrow">{{ __('messages.goods_receiving') }}</p>
                    <h2>{{ __('messages.summary') }}</h2>
                </div>
                <a href="{{ route('supplier-returns.index') }}">{{ __('messages.supplier_returns') }}</a>
            </div>
            <dl>
                <div><dt>{{ __('messages.total_ordered') }}</dt><dd>{{ number_format($totalOrdered, 2) }}</dd></div>
                <div><dt>{{ __('messages.total_received') }}</dt><dd>{{ number_format($totalReceived, 2) }}</dd></div>
                <div><dt>{{ __('messages.total_accepted_into_stock') }}</dt><dd>{{ number_format($totalAccepted, 2) }}</dd></div>
                <div><dt>{{ __('messages.total_damaged') }}</dt><dd>{{ number_format($totalDamaged, 2) }}</dd></div>
                <div><dt>{{ __('messages.total_returned') }}</dt><dd>{{ number_format($totalReturned, 2) }}</dd></div>
                <div><dt>{{ __('messages.total_shortage') }}</dt><dd>{{ number_format($totalShortage, 2) }}</dd></div>
                <div><dt>{{ __('messages.stock_allocation') }}</dt><dd>
                    @forelse ($allocationSummary as $location => $quantity)
                        {{ $location }}: {{ number_format((float) $quantity, 2) }}@if (! $loop->last), @endif
                    @empty
                        {{ __('messages.not_set') }}
                    @endforelse
                </dd></div>
            </dl>
        </section>

        @if ($purchaseOrder->sent_at)
            <section class="info-panel po-email-preview">
                <p class="eyebrow">{{ __('messages.supplier_email_preview') }}</p>
                <h2>{{ __('messages.purchase_order_email_subject', ['po' => $purchaseOrder->po_number]) }}</h2>
                <p><strong>{{ __('messages.email_to') }}:</strong> {{ $purchaseOrder->email_to ?: __('messages.not_set') }}</p>
                <p><strong>{{ __('messages.sent_at') }}:</strong> {{ $purchaseOrder->sent_at->format('d M Y H:i') }}</p>
            </section>
        @endif

        @if (($requiresApproval && ! $isRejected && ! $isCancelled) || $latestEmailDraft)
            <section class="info-panel po-email-draft-panel">
                <p class="eyebrow">Supplier Email Draft</p>
                @if ($latestEmailDraft)
                    <h2>{{ $latestEmailDraft->subject }}</h2>
                    <p class="po-email-safety-note">No real email is sent automatically. Admin controls the final action.</p>
                    <dl>
                        <div><dt>Status</dt><dd><span class="status-pill email-status-{{ $latestEmailDraft->status }}">{{ ucfirst($latestEmailDraft->status) }}</span></dd></div>
                        <div><dt>Sent at</dt><dd>{{ $latestEmailDraft->sent_at?->format('d M Y H:i') ?? 'Not marked sent' }}</dd></div>
                        <div><dt>Draft</dt><dd><a href="{{ route('supplier-email-drafts.show', $latestEmailDraft) }}">Review Supplier Email Draft</a></dd></div>
                    </dl>
                    @if ($isAdmin)
                        <div class="po-demo-actions">
                            @if ($purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_APPROVED && $latestEmailDraft->status === \App\Models\SupplierEmailDraft::STATUS_DRAFT)
                                <form method="post" action="{{ route('supplier-email-drafts.approve', $latestEmailDraft) }}">
                                    @csrf
                                    <button class="btn btn-primary" type="submit">Approve Email Draft</button>
                                </form>
                            @endif
                            @if ($purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_APPROVED && $latestEmailDraft->status === \App\Models\SupplierEmailDraft::STATUS_APPROVED && ! $realEmailEnabled)
                                <form method="post" action="{{ route('supplier-email-drafts.mark-sent', $latestEmailDraft) }}">
                                    @csrf
                                    <button class="btn btn-primary" type="submit">Mark Email as Sent</button>
                                </form>
                            @endif
                            @if ($purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_APPROVED && $latestEmailDraft->status === \App\Models\SupplierEmailDraft::STATUS_APPROVED && $realEmailConfigured)
                                <form method="post" action="{{ route('supplier-email-drafts.send-via-gmail', $latestEmailDraft) }}">
                                    @csrf
                                    <button class="btn btn-primary" type="submit">Send via Gmail</button>
                                </form>
                            @endif
                        </div>
                    @endif
                @else
                    <h2>No email draft yet</h2>
                    <p class="agent-summary">A supplier email draft becomes available after admin approval.</p>
                    <p class="po-email-safety-note">No real email is sent automatically. Admin controls the final action.</p>
                @endif
            </section>
        @endif

        <div class="info-panel">
            <dl>
                <div><dt>{{ __('messages.notes') }}</dt><dd>{{ $purchaseOrder->notes ?: __('messages.no_notes_added') }}</dd></div>
                @if ($purchaseOrder->approvalRequest?->review_notes)
                    <div><dt>Review notes</dt><dd>{{ $purchaseOrder->approvalRequest->review_notes }}</dd></div>
                @endif
            </dl>
        </div>
    </section>
</main>
@endsection
