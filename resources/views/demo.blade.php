@extends('layouts.app')

@section('content')
<main class="admin-page demo-guide-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">Qwen Cloud Hackathon</p>
                <h1>TingHao Agent</h1>
                <p>Track 4 Autopilot Agent for bakery procurement, supplier drafting, expiry-loss prevention, and human-approved business actions.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('login') }}" class="btn btn-primary">Open Login</a>
                <a href="{{ route('health') }}" class="btn btn-muted">Health JSON</a>
                <a href="{{ route('agent.proof') }}" class="btn btn-muted">Proof JSON</a>
            </div>
        </div>

        <section class="demo-hero-grid">
            <article class="info-panel">
                <p class="eyebrow">Demo accounts</p>
                <h2>Judge login</h2>
                <div class="agent-detail-list">
                    <div><span>Admin</span><strong>admin@tinghao.com / password</strong></div>
                    <div><span>Staff</span><strong>staff@tinghao.com / password</strong></div>
                </div>
                <p class="agent-summary">No API keys, database credentials, or real email delivery are exposed on this page.</p>
            </article>

            <article class="info-panel">
                <p class="eyebrow">Phase 1 controls</p>
                <h2>Safe demo configuration</h2>
                <div class="agent-detail-list">
                    <div><span>Observe</span><strong>php artisan tinghao:autopilot-scan</strong></div>
                    <div><span>Automatic draft</span><strong>Disabled by default</strong></div>
                    <div><span>Real Gmail</span><strong>Disabled by default</strong></div>
                </div>
                <p class="agent-summary">Predictions come from FastAPI. Qwen is called only for explicit explanation or supplier email drafting actions.</p>
            </article>
        </section>

        <x-agent.phase-one-capability-map :capabilities="$phaseOneCapabilities" />

        <section class="info-panel">
            <div class="agent-card-heading compact">
                <div>
                    <p class="eyebrow">Three-minute path</p>
                    <h2>Main demo flow</h2>
                </div>
                <a href="{{ route('agent.index') }}" class="btn btn-primary">Open Agent Audit</a>
            </div>
            <ol class="demo-step-list">
                <li><span>1</span><strong>Run proactive observation</strong><em>Execute tinghao:autopilot-scan and open the audited monitoring run.</em></li>
                <li><span>2</span><strong>Review Stock Planner</strong><em>Open a low-stock prediction and show the FastAPI action, positive quantity, and supplier evidence.</em></li>
                <li><span>3</span><strong>Prepare the restock plan</strong><em>Use Plan Restock, or enable the optional high-confidence draft flag, to create one pending approval PO.</em></li>
                <li><span>4</span><strong>Approve as admin</strong><em>Edit supplier or quantity if needed, then approve at the human checkpoint.</em></li>
                <li><span>5</span><strong>Generate the supplier draft</strong><em>Explicitly ask Qwen for professional English wording from the approved PO.</em></li>
                <li><span>6</span><strong>Edit and approve the email</strong><em>Admin controls subject, body, and final approval.</em></li>
                <li><span>7</span><strong>Complete supplier communication</strong><em>Use demo-safe Mark Sent, or explicit Gmail only when the server is configured.</em></li>
                <li><span>8</span><strong>Verify and audit</strong><em>Record supplier confirmation, goods receiving, discrepancies, close the PO, and inspect the Agent Audit trail.</em></li>
            </ol>
        </section>

        <section class="demo-link-grid">
            <a href="{{ route('login') }}"><strong>Login</strong><span>/login</span></a>
            <a href="{{ route('agent.index') }}"><strong>Agent Audit</strong><span>/agent</span></a>
            <a href="{{ route('purchase-orders.index') }}"><strong>Purchase Orders</strong><span>/purchase-orders</span></a>
            <a href="{{ route('agent.expiry-loss') }}"><strong>Expiry Loss</strong><span>/agent/expiry-loss</span></a>
            <a href="{{ route('health') }}"><strong>Health</strong><span>/health</span></a>
            <a href="{{ route('agent.proof') }}"><strong>Proof</strong><span>/agent/proof</span></a>
        </section>

        <section class="agent-result-grid">
            <article class="table-card">
                <div class="agent-card-heading compact">
                    <div><p class="eyebrow">Recent activity</p><h2>Latest agent runs</h2></div>
                </div>
                <table class="data-table">
                    <thead><tr><th>Run</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                        @forelse ($recentAgentRuns as $run)
                            <tr>
                                <td><strong>#{{ $run->id }}</strong><span>{{ str($run->input_text)->limit(70) }}</span></td>
                                <td>{{ ucfirst($run->status) }}</td>
                                <td>{{ $run->created_at->format('d M, H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-state">Run the autopilot scan or a Stock Planner restock mission to create activity.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </article>

            <article class="table-card">
                <div class="agent-card-heading compact">
                    <div><p class="eyebrow">Human approval</p><h2>POs waiting approval</h2></div>
                </div>
                <table class="data-table">
                    <thead><tr><th>PO</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                        @forelse ($pendingPurchaseOrders as $po)
                            <tr>
                                <td><strong>{{ $po->po_number }}</strong><span>#{{ $po->id }}</span></td>
                                <td>{{ str_replace('_', ' ', $po->status) }}</td>
                                <td>{{ $po->created_at->format('d M, H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-state">No purchase orders are waiting right now.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </article>
        </section>
    </section>
</main>
@endsection
