<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'supplier_id',
    'agent_run_id',
    'subject',
    'body',
    'status',
    'approved_by',
    'approved_at',
    'sent_at',
    'provider',
    'provider_message_id',
    'delivery_status',
    'delivery_provider',
    'delivery_metadata',
    'last_delivery_attempt_at',
    'sent_by',
    'send_error_category',
    'qwen_model',
    'qwen_metadata',
])]
class SupplierEmailDraft extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SENT = 'sent';

    public const DELIVERY_DELIVERED = 'delivered';

    public const DELIVERY_ACCEPTED = 'accepted';

    public const DELIVERY_FAILED = 'failed';

    public const DELIVERY_DEMO_MARKED_SENT = 'demo_marked_sent';

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivery_metadata' => 'array',
            'last_delivery_attempt_at' => 'datetime',
            'qwen_metadata' => 'array',
        ];
    }
}
