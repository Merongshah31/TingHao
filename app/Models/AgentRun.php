<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'input_text',
    'input_type',
    'status',
    'parsed_intent',
    'final_summary',
    'qwen_mocked',
])]
class AgentRun extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const STATUS_NEEDS_APPROVAL = 'needs_approval';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AgentToolCall::class);
    }

    public function reasoningSteps(): HasMany
    {
        return $this->hasMany(AgentReasoningStep::class)->orderBy('step_order');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    public function supplierEmailDrafts(): HasMany
    {
        return $this->hasMany(SupplierEmailDraft::class);
    }

    public function expiryLossRecommendations(): HasMany
    {
        return $this->hasMany(ExpiryLossRecommendation::class);
    }

    protected function casts(): array
    {
        return [
            'parsed_intent' => 'array',
            'qwen_mocked' => 'boolean',
        ];
    }
}
