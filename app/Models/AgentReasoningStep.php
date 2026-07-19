<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agent_run_id',
    'step_order',
    'step_type',
    'title',
    'summary',
    'evidence',
    'confidence',
    'risk_level',
    'requires_human_approval',
    'related_tool_call_id',
])]
class AgentReasoningStep extends Model
{
    use HasFactory;

    public const TYPE_OBSERVE = 'observe';

    public const TYPE_UNDERSTAND = 'understand';

    public const TYPE_PLAN = 'plan';

    public const TYPE_TOOL_ACTION = 'tool_action';

    public const TYPE_TOOL_RESULT = 'tool_result';

    public const TYPE_DECISION = 'decision';

    public const TYPE_RISK_CHECK = 'risk_check';

    public const TYPE_HUMAN_CHECKPOINT = 'human_checkpoint';

    public const TYPE_FINAL_SUMMARY = 'final_summary';

    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    public const RISK_BLOCKED = 'blocked';

    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    public function relatedToolCall(): BelongsTo
    {
        return $this->belongsTo(AgentToolCall::class, 'related_tool_call_id');
    }

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'confidence' => 'decimal:2',
            'requires_human_approval' => 'boolean',
        ];
    }
}
