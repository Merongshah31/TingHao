@extends('layouts.app')

@section('content')
@php
    $ingredients = $parsed['ingredients'] ?? [];
    $matchedInventory = $parsed['matched_inventory'] ?? [];
    $matchedSuppliers = $parsed['matched_suppliers'] ?? [];
    $restockPlan = $parsed['restock_plan'] ?? [];
    $purchaseOrder = $agentRun->purchaseOrders->first();
    $emailDraft = $purchaseOrder?->latestSupplierEmailDraft;
    $expiryLossRecommendations = $agentRun->expiryLossRecommendations;
    $primaryIngredient = collect($restockPlan)->first()['ingredient_name'] ?? ($ingredients[0]['name'] ?? ($matchedInventory[0]['name'] ?? null));
    $primarySupplier = $purchaseOrder?->supplier?->name ?? ($matchedSuppliers[0]['name'] ?? ($parsed['supplier_name'] ?? null));
    $firstInventory = collect($matchedInventory)->first();
    $firstPlan = collect($restockPlan)->first();
    $missionTitle = $expiryLossRecommendations->isNotEmpty()
        ? 'Expiry Loss Prevention Mission'
        : (filled($primaryIngredient)
            ? str($primaryIngredient)->title().' Restock Mission'.(filled($primarySupplier) ? ' from '.$primarySupplier : '')
            : 'Autopilot Procurement Mission');
    $riskLevel = $expiryLossRecommendations->isNotEmpty()
        ? 'medium'
        : (($parsed['urgency'] ?? 'low') === 'high' || (bool) data_get($firstInventory, 'low_stock') ? 'high' : 'low');
    $nextActionTitle = 'Workflow completed';
    $nextActionDescription = 'All available autopilot steps for this mission are complete.';
    if ($purchaseOrder?->status === \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL) {
        $nextActionTitle = 'Approve Purchase Order';
        $nextActionDescription = 'Approve '.$purchaseOrder->po_number.' for '.(filled($firstPlan) ? number_format((float) ($firstPlan['recommended_quantity'] ?? 0), 2).' '.($firstPlan['unit'] ?? '') : 'the planned order').' from '.($primarySupplier ?? 'the selected supplier').'.';
    } elseif ($purchaseOrder?->status === \App\Models\PurchaseOrder::STATUS_APPROVED && ! $emailDraft) {
        $nextActionTitle = 'Generate Supplier Email Draft';
        $nextActionDescription = 'Create a Qwen-backed supplier email draft for '.$purchaseOrder->po_number.'.';
    } elseif ($emailDraft?->status === \App\Models\SupplierEmailDraft::STATUS_DRAFT) {
        $nextActionTitle = 'Approve Supplier Email Draft';
        $nextActionDescription = 'Review and approve the AI-generated email before it can be marked sent.';
    } elseif ($emailDraft?->status === \App\Models\SupplierEmailDraft::STATUS_APPROVED) {
        $nextActionTitle = 'Mark Email as Sent';
        $nextActionDescription = 'Record a demo-safe sent state. No real supplier email is delivered.';
    } elseif (! $purchaseOrder && $agentRun->status !== \App\Models\AgentRun::STATUS_COMPLETED) {
        $nextActionTitle = 'Waiting for agent result';
        $nextActionDescription = 'The mission has not produced a linked purchase order yet.';
    }
    $missionSummary = $agentRun->final_summary ?: 'TingHao Agent analyzed the staff message, checked available business context, and stored this mission for review.';
    if ($purchaseOrder) {
        $missionSummary .= ' Purchase order '.$purchaseOrder->po_number.' is linked and currently '.str_replace('_', ' ', $purchaseOrder->status).'.';
    }
    if ($purchaseOrder?->status === \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL) {
        $missionSummary .= ' Admin approval is required before supplier communication can proceed.';
    }
    $steps = [
        ['label' => 'Message Parsed', 'done' => filled($parsed['intent'] ?? null), 'current' => blank($parsed['intent'] ?? null)],
        ['label' => 'Inventory Checked', 'done' => count($matchedInventory) > 0, 'current' => filled($parsed['intent'] ?? null) && count($matchedInventory) === 0],
        ['label' => 'Supplier Ranked', 'done' => count($matchedSuppliers) > 0, 'current' => count($matchedInventory) > 0 && count($matchedSuppliers) === 0],
        ['label' => 'PO Drafted', 'done' => (bool) $purchaseOrder, 'current' => ! $purchaseOrder && count($matchedSuppliers) > 0],
        ['label' => 'Admin Approval', 'done' => $purchaseOrder && $purchaseOrder->status !== \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL, 'current' => $purchaseOrder?->status === \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL],
        ['label' => 'Email Drafted', 'done' => (bool) $emailDraft, 'current' => $purchaseOrder?->status === \App\Models\PurchaseOrder::STATUS_APPROVED && ! $emailDraft],
        ['label' => 'Marked Sent', 'done' => $emailDraft?->status === \App\Models\SupplierEmailDraft::STATUS_SENT, 'current' => in_array($emailDraft?->status, [\App\Models\SupplierEmailDraft::STATUS_DRAFT, \App\Models\SupplierEmailDraft::STATUS_APPROVED], true)],
        ['label' => 'Audit Logged', 'done' => $agentRun->toolCalls->isNotEmpty() || $agentRun->reasoningSteps->isNotEmpty(), 'current' => false],
    ];
    $decisionLoopIterations = data_get($parsed, 'decision_loop.iterations', []);
@endphp

<main class="admin-page agent-console-page">
    <section class="page-shell">
        <div class="page-heading mission-header">
            <div>
                <p class="eyebrow">Autopilot Command Center</p>
                <h1>Autopilot Procurement Mission</h1>
                <p>{{ $missionTitle }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('demo') }}" class="btn btn-muted">Demo Guide</a>
                <a href="{{ route('agent.proof') }}" class="btn btn-muted">Proof JSON</a>
                <a href="{{ route('agent.index') }}" class="btn btn-muted">Back to console</a>
                <a href="{{ route('dashboard') }}" class="btn btn-muted">Dashboard</a>
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <section class="mission-grid">
            <article class="autopilot-card mission-summary-card">
                <p class="eyebrow">Mission Summary</p>
                <h2>{{ $missionTitle }}</h2>
                <p>{{ $missionSummary }}</p>
                <blockquote class="agent-message">{{ $agentRun->input_text }}</blockquote>
            </article>

            <article class="next-action-card">
                <p class="eyebrow">Next Best Action</p>
                <h2>{{ $nextActionTitle }}</h2>
                <p>{{ $nextActionDescription }}</p>
                <div class="next-action-buttons">
                    @if ($purchaseOrder)
                        <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-muted">View PO</a>
                    @endif
                    @if (auth()->user()->isAdmin())
                        @if ($purchaseOrder?->status === \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL)
                            <form method="post" action="{{ route('purchase-orders.approve', $purchaseOrder) }}">@csrf<button class="btn btn-primary" type="submit">Approve PO</button></form>
                            <form method="post" action="{{ route('purchase-orders.reject', $purchaseOrder) }}">@csrf<button class="btn btn-danger" type="submit">Reject PO</button></form>
                        @elseif ($purchaseOrder?->status === \App\Models\PurchaseOrder::STATUS_APPROVED && ! $emailDraft)
                            <form method="post" action="{{ route('purchase-orders.generate-email-draft', $purchaseOrder) }}">@csrf<button class="btn btn-primary" type="submit">Generate Supplier Email Draft</button></form>
                        @elseif ($emailDraft?->status === \App\Models\SupplierEmailDraft::STATUS_DRAFT)
                            <form method="post" action="{{ route('supplier-email-drafts.approve', $emailDraft) }}">@csrf<button class="btn btn-primary" type="submit">Approve Email Draft</button></form>
                        @elseif ($emailDraft?->status === \App\Models\SupplierEmailDraft::STATUS_APPROVED)
                            <form method="post" action="{{ route('supplier-email-drafts.mark-sent', $emailDraft) }}">@csrf<button class="btn btn-primary" type="submit">Mark Email as Sent</button></form>
                        @endif
                    @elseif ($purchaseOrder?->status === \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL)
                        <span class="status-pill warning">Waiting for admin approval</span>
                    @endif
                </div>
            </article>
        </section>

        <section class="mission-meta-grid">
            <div><span>Run ID</span><strong>#{{ $agentRun->id }}</strong></div>
            <div><span>Status</span><strong>{{ ucfirst(str_replace('_', ' ', $agentRun->status)) }}</strong></div>
            <div><span>Risk level</span><strong class="risk-badge risk-{{ $riskLevel }}">{{ ucfirst($riskLevel) }}</strong></div>
            <div><span>Created</span><strong>{{ $agentRun->created_at->format('d M Y, H:i') }}</strong></div>
            <div><span>Requested by</span><strong>{{ $agentRun->user?->name ?? 'System' }}</strong></div>
            <div><span>Next action</span><strong>{{ $nextActionTitle }}</strong></div>
        </section>

        <section class="workflow-stepper">
            @foreach ($steps as $index => $step)
                <div @class(['completed' => $step['done'], 'current' => $step['current'], 'pending' => ! $step['done'] && ! $step['current']])>
                    <span>{{ $index + 1 }}</span>
                    <strong>{{ $step['label'] }}</strong>
                </div>
            @endforeach
        </section>

        <section class="mission-grid">
            <article class="impact-card">
                <p class="eyebrow">Business Impact</p>
                <h2>Operational value</h2>
                <dl>
                    <div><dt>Stockout risk</dt><dd>{{ ucfirst($riskLevel) }}</dd></div>
                    <div><dt>Recommended reorder</dt><dd>{{ filled($firstPlan) ? number_format((float) ($firstPlan['recommended_quantity'] ?? 0), 2).' '.($firstPlan['unit'] ?? '') : 'Not available' }}</dd></div>
                    <div><dt>Current quantity</dt><dd>{{ filled($firstInventory) ? number_format((float) ($firstInventory['current_quantity'] ?? 0), 2).' '.($firstInventory['unit'] ?? '') : 'Not available' }}</dd></div>
                    <div><dt>Minimum stock</dt><dd>{{ filled($firstInventory) ? number_format((float) ($firstInventory['minimum_stock'] ?? 0), 2).' '.($firstInventory['unit'] ?? '') : 'Not available' }}</dd></div>
                    <div><dt>Supplier selected</dt><dd>{{ $primarySupplier ?? 'No supplier selected' }}</dd></div>
                    <div><dt>Manual steps automated</dt><dd>{{ min(8, max(1, $agentRun->toolCalls->count())) }}</dd></div>
                    <div><dt>Human approvals required</dt><dd>{{ $purchaseOrder ? '2' : '0' }}</dd></div>
                    <div><dt>Potential expiry loss</dt><dd>{{ $expiryLossRecommendations->isNotEmpty() ? 'RM '.number_format((float) $expiryLossRecommendations->sum('potential_loss'), 2) : 'Not available' }}</dd></div>
                    <div><dt>Tool calls completed</dt><dd>{{ $agentRun->toolCalls->where('status', 'completed')->count() }}</dd></div>
                </dl>
            </article>

            <article class="safety-card">
                <p class="eyebrow">Autopilot Safety Guardrails</p>
                <h2>Production-ready controls</h2>
                <ul>
                    <li>Agent cannot approve purchase orders automatically.</li>
                    <li>Agent cannot send supplier email automatically.</li>
                    <li>Admin approval is required for critical actions.</li>
                    <li>Tool calls are logged for audit review.</li>
                    <li>Reasoning Activity is stored as safe summaries only.</li>
                    <li>Qwen API keys remain server-side only.</li>
                </ul>
            </article>
        </section>

        @if (count($decisionLoopIterations) > 0)
            <section class="table-card responsive-table-card">
                <div class="agent-card-heading compact">
                    <div>
                        <p class="eyebrow">Bounded Qwen Decision Loop</p>
                        <h2>Observation to safe action</h2>
                    </div>
                    <span class="status-pill warning">Maximum {{ data_get($parsed, 'decision_loop.maximum_iterations', 4) }} iterations</span>
                </div>
                <p class="agent-summary">Qwen selects one allowed action per iteration. Laravel validates and executes it, and critical work stops for human approval.</p>
                <div class="responsive-table" tabindex="0" aria-label="Bounded Qwen decision loop audit">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Step</th>
                                <th>Observation</th>
                                <th>Selected action</th>
                                <th>Tool result</th>
                                <th>Safe reason summary</th>
                                <th>Stop reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($decisionLoopIterations as $iteration)
                                <tr>
                                    <td>
                                        <strong>#{{ $iteration['iteration'] ?? $loop->iteration }}</strong>
                                        <span>{{ ($iteration['decision_source'] ?? 'unknown') === 'qwen' ? 'Qwen selected' : 'Safe fallback' }}</span>
                                    </td>
                                    <td>{{ $iteration['observation'] ?? 'Not recorded' }}</td>
                                    <td><code>{{ $iteration['selected_action'] ?? 'stop' }}</code></td>
                                    <td>{{ $iteration['tool_result'] ?? 'Not recorded' }}</td>
                                    <td>
                                        {{ $iteration['reason_summary'] ?? 'Not recorded' }}
                                        @if (is_numeric($iteration['confidence'] ?? null))
                                            <span>Confidence {{ number_format((float) $iteration['confidence'] * 100, 0) }}%</span>
                                        @endif
                                    </td>
                                    <td>{{ str_replace('_', ' ', $iteration['stop_reason'] ?? 'Continue') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="agent-summary">Final stop: {{ str_replace('_', ' ', data_get($parsed, 'decision_loop.stop_reason', 'not recorded')) }}. Raw chain-of-thought is neither requested nor stored.</p>
            </section>
        @endif

        <x-agent.reasoning-activity :steps="$agentRun->reasoningSteps" />

        @if ($expiryLossRecommendations->isNotEmpty())
            <section class="table-card">
                <div class="agent-card-heading compact">
                    <div><p class="eyebrow">Expiry loss prevention</p><h2>RM impact recommendations</h2></div>
                </div>
                <table class="data-table">
                    <thead><tr><th>Ingredient</th><th>Quantity</th><th>Potential loss</th><th>Expiry</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($expiryLossRecommendations as $recommendation)
                            <tr>
                                <td><strong>{{ $recommendation->ingredient?->name ?? 'Deleted ingredient' }}</strong></td>
                                <td>{{ number_format((float) $recommendation->quantity_at_risk, 2) }} {{ $recommendation->unit }}</td>
                                <td>{{ $recommendation->potential_loss !== null ? 'RM '.number_format((float) $recommendation->potential_loss, 2) : 'Cost unavailable' }}</td>
                                <td>{{ $recommendation->expiry_date?->format('d M Y') }} · {{ $recommendation->days_until_expiry }} day(s)</td>
                                <td><span class="status-pill expiry-loss-status-{{ $recommendation->status }}">{{ ucfirst($recommendation->status) }}</span></td>
                                <td class="table-actions"><a href="{{ route('expiry-loss-recommendations.show', $recommendation) }}">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        <section class="agent-result-grid">
            <article class="table-card">
                <div class="agent-card-heading compact">
                    <div><p class="eyebrow">Extracted ingredients</p><h2>Procurement hints</h2></div>
                </div>
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Quantity</th><th>Unit</th></tr></thead>
                    <tbody>
                        @forelse ($ingredients as $ingredient)
                            <tr>
                                <td><strong>{{ $ingredient['name'] ?? 'Unknown' }}</strong></td>
                                <td>{{ $ingredient['quantity'] ?? 'Not specified' }}</td>
                                <td>{{ $ingredient['unit'] ?? 'Not specified' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-state">No ingredient hints detected.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </article>

            <article class="table-card">
                <div class="agent-card-heading compact">
                    <div><p class="eyebrow">Matched inventory records</p><h2>Database context</h2></div>
                </div>
                <table class="data-table">
                    <thead><tr><th>Ingredient</th><th>Current</th><th>Minimum</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($matchedInventory as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item['name'] ?? 'Unknown' }}</strong>
                                    <span>{{ $item['supplier_name'] ?? 'No supplier linked' }}</span>
                                </td>
                                <td>{{ number_format((float) ($item['current_quantity'] ?? 0), 2) }} {{ $item['unit'] ?? '' }}</td>
                                <td>{{ number_format((float) ($item['minimum_stock'] ?? 0), 2) }} {{ $item['unit'] ?? '' }}</td>
                                <td><span class="status-pill {{ ($item['low_stock'] ?? false) ? 'danger' : 'ok' }}">{{ ($item['low_stock'] ?? false) ? 'Low stock' : 'Available' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">No matching inventory records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </article>
        </section>

        <section class="table-card">
            <div class="agent-card-heading compact">
                <div><p class="eyebrow">Restock planned</p><h2>Recommended quantities</h2></div>
            </div>
            <table class="data-table">
                <thead><tr><th>Ingredient</th><th>Current</th><th>Minimum</th><th>Recommended</th><th>Reasoning</th></tr></thead>
                <tbody>
                    @forelse ($restockPlan as $plan)
                        <tr>
                            <td><strong>{{ $plan['ingredient_name'] ?? 'Unknown' }}</strong></td>
                            <td>{{ number_format((float) ($plan['current_quantity'] ?? 0), 2) }} {{ $plan['unit'] ?? '' }}</td>
                            <td>{{ number_format((float) ($plan['minimum_stock'] ?? 0), 2) }} {{ $plan['unit'] ?? '' }}</td>
                            <td>{{ number_format((float) ($plan['recommended_quantity'] ?? 0), 2) }} {{ $plan['unit'] ?? '' }}</td>
                            <td>{{ $plan['reasoning'] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">No restock plan was created.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="agent-result-grid">
            <article class="table-card">
                <div class="agent-card-heading compact">
                    <div><p class="eyebrow">Matched supplier records</p><h2>Supplier context</h2></div>
                </div>
                <table class="data-table">
                    <thead><tr><th>Supplier</th><th>Email</th><th>Phone</th></tr></thead>
                    <tbody>
                        @forelse ($matchedSuppliers as $supplier)
                            <tr>
                                <td>
                                    <strong>{{ $supplier['name'] ?? 'Unknown' }}</strong>
                                    <span>{{ $supplier['notes'] ?? '' }}</span>
                                </td>
                                <td>{{ $supplier['email'] ?? 'Not set' }}</td>
                                <td>{{ $supplier['phone'] ?? 'Not set' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-state">No matching suppliers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </article>

            <article class="info-panel">
                <p class="eyebrow">Final summary</p>
                <h2>Recommendation Summary</h2>
                <p class="agent-summary">{{ $agentRun->final_summary }}</p>
            </article>
        </section>

        <section class="info-panel">
            <p class="eyebrow">Live agent activity</p>
            <h2>Tool call timeline</h2>
            <ol class="agent-flow-list agent-phase-two-flow">
                <li><span>1</span><strong>Message Parsed</strong><em>Qwen or mock parser extracted intent.</em></li>
                <li><span>2</span><strong>Inventory Checked</strong><em>Existing ingredients were matched.</em></li>
                <li><span>3</span><strong>Restock Planned</strong><em>Recommended reorder quantities calculated.</em></li>
                <li><span>4</span><strong>Supplier Ranked</strong><em>Supplier score and explanation generated.</em></li>
                <li><span>5</span><strong>PO Drafted</strong><em>Purchase order draft created if possible.</em></li>
                <li><span>6</span><strong>Admin Approval</strong><em>Human-in-the-loop checkpoint.</em></li>
                <li><span>7</span><strong>Email Drafted</strong><em>Available after PO approval.</em></li>
                <li><span>8</span><strong>Audit Logged</strong><em>Tool calls and reasoning are persisted.</em></li>
            </ol>
            <div class="agent-timeline">
                @foreach ($agentRun->toolCalls as $toolCall)
                    <article>
                        <span>{{ $loop->iteration }}</span>
                        <div>
                            <strong>{{ $toolCall->tool_name }}</strong>
                            <em>{{ $toolCall->created_at->format('H:i:s') }} · {{ ucfirst($toolCall->status) }}</em>
                            <details>
                                <summary>Payload</summary>
                                <pre>{{ json_encode(['input' => $toolCall->input_payload, 'output' => $toolCall->output_payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </section>
</main>
@endsection
