@extends('layouts.app')

@section('content')
<main class="admin-page agent-console-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">Track 4 Autopilot Agent</p>
                <h1>Agent Audit Console</h1>
                <p>Technical proof for agent missions, Qwen usage, reasoning activity, tool calls, and human approval checkpoints.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('demo') }}" class="btn btn-muted">Demo Guide</a>
                <a href="{{ route('agent.proof') }}" class="btn btn-muted">Proof JSON</a>
                <a href="{{ route('dashboard') }}" class="btn btn-muted">Dashboard</a>
                <a href="{{ route('inventory.index') }}" class="btn btn-muted">Inventory</a>
                <a href="{{ route('agent.expiry-loss') }}" class="btn btn-primary">Expiry Loss Prevention</a>
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <section class="agent-status-strip" aria-label="Agent status">
            <article>
                <span>Qwen mode</span>
                <strong>{{ $qwenMockMode ? 'Mock demo mode' : 'Live Qwen mode' }}</strong>
            </article>
            <article>
                <span>Model</span>
                <strong>{{ $qwenModel }}</strong>
            </article>
            <article>
                <span>Server-side</span>
                <strong>{{ $qwenConfigured ? 'Configured' : 'Not configured' }}</strong>
            </article>
            <article>
                <span>Audit trail</span>
                <strong>Runs and tool calls stored</strong>
            </article>
            <article>
                <span>Human loop</span>
                <strong>Admin approval required</strong>
            </article>
        </section>

        <section class="autopilot-status-grid" aria-label="Current autopilot status">
            <article class="autopilot-card">
                <span>Pending PO Approvals</span>
                <strong>{{ $autopilotStats['pending_po_approvals'] }}</strong>
                <a href="{{ route('purchase-orders.index') }}">Review purchase orders</a>
            </article>
            <article class="autopilot-card">
                <span>Email Drafts Waiting Approval</span>
                <strong>{{ $autopilotStats['email_drafts_waiting'] }}</strong>
                <a href="{{ route('dashboard') }}#autopilot-actions">Review dashboard actions</a>
            </article>
            <article class="autopilot-card">
                <span>Expiry Risk RM</span>
                <strong>RM {{ number_format($autopilotStats['expiry_risk_rm'], 2) }}</strong>
                <a href="{{ route('agent.expiry-loss') }}">Open expiry risk</a>
            </article>
            <article class="autopilot-card">
                <span>Recent Agent Missions</span>
                <strong>{{ $autopilotStats['recent_missions'] }}</strong>
                <a href="#recent-agent-runs">View activity</a>
            </article>
        </section>

        <section id="agent-audit-visualizer" class="workflow-visualizer agent-audit-visualizer" aria-labelledby="workflow-visualizer-title">
            <div class="agent-card-heading">
                <div>
                    <p class="eyebrow">Selected Live Mission</p>
                    <h2 id="workflow-visualizer-title">Agent Audit Visualizer</h2>
                    <p class="agent-summary">Follow the selected mission from trigger to human-governed outcome. Select a milestone for its relevant evidence.</p>
                </div>
                <form method="get" action="{{ route('agent.index') }}" class="workflow-run-picker">
                    <label for="workflow-run">Live run</label>
                    <select id="workflow-run" name="run">
                        @if (! $workflowRun)
                            <option value="">No live runs yet</option>
                        @endif
                        @if ($workflowRun && ! $runs->getCollection()->contains('id', $workflowRun->id))
                            <option value="{{ $workflowRun->id }}" selected>
                                #{{ $workflowRun->id }} - {{ str($workflowRun->input_text)->limit(48) }}
                            </option>
                        @endif
                        @foreach ($runs as $run)
                            <option value="{{ $run->id }}" @selected($workflowRun?->id === $run->id)>
                                #{{ $run->id }} - {{ str($run->input_text)->limit(48) }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-muted">Load</button>
                </form>
            </div>

            @if ($agentAudit['has_run'])
                <div class="audit-section-heading">
                    <p class="eyebrow">Run Summary</p>
                    <h3>Selected mission</h3>
                </div>
                <dl class="audit-run-summary" aria-label="Selected agent run summary">
                    @foreach ([
                        'Run ID' => 'run_id',
                        'Mission type' => 'mission_type',
                        'Agent Mission Status' => 'agent_status',
                        'Procurement Workflow Status' => 'procurement_status',
                        'Started at' => 'started_at',
                        'Owner' => 'owner',
                        'Qwen mode' => 'qwen_mode',
                        'Human approval' => 'approval_state',
                    ] as $label => $key)
                        <div><dt>{{ $label }}</dt><dd>{{ $agentAudit['summary'][$key] }}</dd></div>
                    @endforeach
                </dl>
            @endif

            <div class="audit-section-heading">
                <p class="eyebrow">Live Audit Milestones</p>
                <h3>Business decision path</h3>
            </div>
            <ol class="audit-milestone-strip" aria-label="Selected mission audit milestones">
                @foreach ($agentAudit['milestones'] as $milestone)
                    <li>
                        <button
                            type="button"
                            @class(['audit-milestone', 'state-'.$milestone['state'], 'selected' => $loop->index === $agentAudit['selected_milestone']])
                            data-audit-milestone="{{ $loop->index }}"
                            aria-controls="selected-audit-milestone"
                            aria-expanded="{{ $loop->index === $agentAudit['selected_milestone'] ? 'true' : 'false' }}"
                        >
                            <span class="audit-milestone-number">{{ $loop->iteration }}</span>
                            <strong>{{ $milestone['title'] }}</strong>
                            <span class="audit-actor-badge actor-{{ str($milestone['actor'])->slug() }}">{{ $milestone['actor'] }}</span>
                            <em>{{ str_replace('_', ' ', $milestone['state']) }}</em>
                        </button>
                    </li>
                @endforeach
            </ol>

            @php($selectedMilestone = $agentAudit['milestones'][$agentAudit['selected_milestone']] ?? $agentAudit['milestones'][0])
            <article id="selected-audit-milestone" class="audit-selected-milestone" aria-live="polite">
                <div class="audit-selected-heading">
                    <div>
                        <p class="eyebrow">Selected Step Details</p>
                        <h3 data-audit-detail-title>{{ $selectedMilestone['title'] }}</h3>
                    </div>
                    <div class="audit-selected-badges">
                        <span class="audit-actor-badge actor-{{ str($selectedMilestone['actor'])->slug() }}" data-audit-detail-actor>{{ $selectedMilestone['actor'] }}</span>
                        <span class="workflow-status-pill status-{{ $selectedMilestone['state'] }}" data-audit-detail-state>{{ str_replace('_', ' ', $selectedMilestone['state']) }}</span>
                    </div>
                </div>
                <time data-audit-detail-time>{{ $selectedMilestone['timestamp'] ?? '' }}</time>
                <p data-audit-detail-result>{{ $selectedMilestone['result'] }}</p>
                <dl data-audit-detail-fields>
                    @foreach ($selectedMilestone['details'] as $label => $value)
                        <div><dt>{{ str($label)->replace('_', ' ')->title() }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </article>

            @if ($agentAudit['has_run'])
                <details class="audit-technical-details">
                    <summary>Technical Audit Details</summary>
                    <p>For judges and developers only. No API keys or raw chain-of-thought are shown.</p>
                    <ul>
                        @forelse ($workflowRun->toolCalls as $toolCall)
                            <li><strong>#{{ $toolCall->id }} {{ $toolCall->tool_name }}</strong> <span>{{ str($toolCall->status)->replace('_', ' ')->title() }}</span></li>
                        @empty
                            <li>No tool calls were recorded.</li>
                        @endforelse
                    </ul>
                    <a href="{{ route('agent.runs.show', $workflowRun) }}">Open full technical audit</a>
                </details>
            @endif
        </section>

        <details class="agent-capability-disclosure">
            <summary>How TingHao Autopilot Works</summary>
            <p>The static Phase 1 architecture is supporting context. The selected live mission above is the primary audit evidence.</p>
            <x-agent.phase-one-capability-map :capabilities="$phaseOneCapabilities" />
        </details>

        <section class="info-panel expiry-agent-callout">
            <div class="agent-card-heading compact">
                <div>
                    <p class="eyebrow">Expiry Loss Prevention</p>
                    <h2>Measure RM at risk this week</h2>
                </div>
                @if (auth()->user()->isAdmin())
                    <form method="post" action="{{ route('agent.expiry-loss.scan') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Run Expiry Loss Scan</button>
                    </form>
                @endif
            </div>
            <p class="agent-summary">Scan ingredients expiring within 7 days, calculate potential RM loss, and generate practical Qwen recommendations before stock becomes waste.</p>
            <p><a href="{{ route('agent.expiry-loss') }}">Open latest recommendations</a></p>
        </section>

        <section id="recent-agent-runs" class="table-card agent-runs-card">
            <div class="agent-card-heading compact">
                <div>
                    <p class="eyebrow">Recent agent runs</p>
                    <h2>{{ auth()->user()->isAdmin() ? 'All console activity' : 'Your console activity' }}</h2>
                </div>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Run</th>
                        <th>Intent</th>
                        <th>Urgency</th>
                        <th>User</th>
                        <th>Qwen Mode</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr>
                            <td>
                                <strong>{{ str($run->input_text)->limit(80) }}</strong>
                                <span>{{ $run->created_at->format('d M Y, H:i') }}</span>
                            </td>
                            <td>{{ str_replace('_', ' ', $run->parsed_intent['intent'] ?? 'pending') }}</td>
                            <td><span class="status-pill {{ ($run->parsed_intent['urgency'] ?? 'low') === 'high' ? 'danger' : 'ok' }}">{{ $run->parsed_intent['urgency'] ?? 'low' }}</span></td>
                            <td>{{ $run->user?->name ?? 'System' }}</td>
                            <td>{{ $run->qwen_mocked ? 'Mock' : 'Qwen' }}</td>
                            <td class="table-actions">
                                <a href="{{ route('agent.index', ['run' => $run->id]) }}#agent-audit-visualizer">Inspect run</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">No agent runs yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div class="pagination-wrap">
            {{ $runs->links() }}
        </div>
    </section>
</main>

<script>
    const auditMilestones = @json($agentAudit['milestones']);
    const auditDetail = document.querySelector('#selected-audit-milestone');

    document.querySelectorAll('[data-audit-milestone]').forEach((button) => {
        button.addEventListener('click', () => {
            const milestone = auditMilestones[Number(button.dataset.auditMilestone)];
            if (!milestone || !auditDetail) return;

            document.querySelectorAll('[data-audit-milestone]').forEach((item) => {
                const selected = item === button;
                item.classList.toggle('selected', selected);
                item.setAttribute('aria-expanded', selected ? 'true' : 'false');
            });

            auditDetail.querySelector('[data-audit-detail-title]').textContent = milestone.title;
            auditDetail.querySelector('[data-audit-detail-result]').textContent = milestone.result;
            auditDetail.querySelector('[data-audit-detail-time]').textContent = milestone.timestamp || '';

            const actor = auditDetail.querySelector('[data-audit-detail-actor]');
            actor.textContent = milestone.actor;
            actor.className = `audit-actor-badge actor-${milestone.actor.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')}`;

            const state = auditDetail.querySelector('[data-audit-detail-state]');
            state.textContent = milestone.state.replaceAll('_', ' ');
            state.className = `workflow-status-pill status-${milestone.state}`;

            const fields = auditDetail.querySelector('[data-audit-detail-fields]');
            fields.replaceChildren();
            Object.entries(milestone.details || {}).forEach(([label, value]) => {
                if (!value) return;
                const row = document.createElement('div');
                const term = document.createElement('dt');
                const description = document.createElement('dd');
                term.textContent = label.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
                description.textContent = value;
                row.append(term, description);
                fields.append(row);
            });
        });
    });
</script>

@endsection
