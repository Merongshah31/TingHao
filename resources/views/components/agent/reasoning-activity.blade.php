@props(['steps'])

@php
    $groups = [
        'Observe' => [
            'types' => [\App\Models\AgentReasoningStep::TYPE_OBSERVE],
            'fallback' => 'No observation has been recorded yet.',
        ],
        'Analyze' => [
            'types' => [\App\Models\AgentReasoningStep::TYPE_UNDERSTAND, \App\Models\AgentReasoningStep::TYPE_RISK_CHECK],
            'fallback' => 'No analysis summary has been recorded yet.',
        ],
        'Plan' => [
            'types' => [\App\Models\AgentReasoningStep::TYPE_PLAN],
            'fallback' => 'No plan step has been recorded yet.',
        ],
        'Tool Actions' => [
            'types' => [\App\Models\AgentReasoningStep::TYPE_TOOL_ACTION, \App\Models\AgentReasoningStep::TYPE_TOOL_RESULT],
            'fallback' => 'No tool action has been recorded yet.',
        ],
        'Decision' => [
            'types' => [\App\Models\AgentReasoningStep::TYPE_DECISION],
            'fallback' => 'No decision step has been recorded yet.',
        ],
        'Human Checkpoint' => [
            'types' => [\App\Models\AgentReasoningStep::TYPE_HUMAN_CHECKPOINT],
            'fallback' => 'No human checkpoint has been recorded yet.',
        ],
        'Execution / Outcome' => [
            'types' => [\App\Models\AgentReasoningStep::TYPE_FINAL_SUMMARY],
            'fallback' => 'No final outcome summary has been recorded yet.',
        ],
    ];
@endphp

<section class="info-panel reasoning-activity-panel">
    <p class="eyebrow">Reasoning Activity</p>
    <h2>Safe structured reasoning</h2>
    <p class="agent-summary">Observe -> Analyze -> Plan -> Tool Action -> Decision -> Human Checkpoint -> Execute. Structured summaries only; TingHao Agent does not expose or store raw model chain-of-thought.</p>

    <div class="reasoning-section-list">
        @foreach ($groups as $groupTitle => $group)
            @php($groupSteps = $steps->whereIn('step_type', $group['types']))
            <article class="reasoning-section">
                <div class="reasoning-section-head">
                    <span>{{ $loop->iteration }}</span>
                    <div>
                        <strong>{{ $groupTitle }}</strong>
                        <em>{{ $groupSteps->count() }} step(s)</em>
                    </div>
                </div>

                @forelse ($groupSteps as $step)
                    <div class="reasoning-card">
                        <div class="reasoning-row">
                            <strong>{{ $step->title }}</strong>
                            <em class="reasoning-type reasoning-type-{{ $step->step_type }}">{{ str_replace('_', ' ', $step->step_type) }}</em>
                        </div>
                        <p>{{ $step->summary }}</p>
                        <div class="reasoning-meta">
                            @if ($step->confidence !== null)
                                <small>Confidence {{ number_format((float) $step->confidence * 100, 0) }}%</small>
                            @endif
                            @if ($step->risk_level)
                                <small class="risk-badge risk-{{ $step->risk_level }}">Risk {{ ucfirst($step->risk_level) }}</small>
                            @endif
                            @if ($step->requires_human_approval)
                                <small class="risk-badge risk-blocked">Human checkpoint</small>
                            @endif
                            @if ($step->relatedToolCall)
                                <small>Tool: {{ $step->relatedToolCall->tool_name }}</small>
                            @endif
                        </div>
                        @if ($step->evidence)
                            <details>
                                <summary>Developer details</summary>
                                <pre>{{ json_encode($step->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif
                    </div>
                @empty
                    <p class="reasoning-empty">{{ $group['fallback'] }}</p>
                @endforelse
            </article>
        @endforeach
    </div>
</section>
