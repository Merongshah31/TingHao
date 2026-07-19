<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agent_run_id',
    'ingredient_id',
    'quantity_at_risk',
    'unit',
    'cost_price',
    'potential_loss',
    'expiry_date',
    'days_until_expiry',
    'recommendation_title',
    'recommendation_body',
    'status',
    'reviewed_by',
])]
class ExpiryLossRecommendation extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_COMPLETED = 'completed';

    public const OPEN_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_REVIEWED,
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return [
            'quantity_at_risk' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'potential_loss' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }
}
