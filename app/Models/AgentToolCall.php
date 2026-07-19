<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'agent_run_id',
    'tool_name',
    'input_payload',
    'output_payload',
    'status',
])]
class AgentToolCall extends Model
{
    use HasFactory;

    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    public function reasoningSteps(): HasMany
    {
        return $this->hasMany(AgentReasoningStep::class, 'related_tool_call_id');
    }

    protected function casts(): array
    {
        return [
            'input_payload' => 'array',
            'output_payload' => 'array',
        ];
    }
}
