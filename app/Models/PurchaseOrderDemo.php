<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'po_number',
    'supplier_name',
    'supplier_email',
    'status',
    'order_date',
    'expected_delivery_date',
    'subtotal',
    'email_sent_at',
    'confirmed_at',
    'received_at',
    'closed_at',
    'notes',
    'created_by',
])]
class PurchaseOrderDemo extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_EMAIL_SENT = 'email_sent';

    public const STATUS_SUPPLIER_CONFIRMED = 'supplier_confirmed';

    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_EMAIL_SENT,
        self::STATUS_SUPPLIER_CONFIRMED,
        self::STATUS_PARTIALLY_RECEIVED,
        self::STATUS_RECEIVED,
        self::STATUS_CLOSED,
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderDemoItem::class);
    }

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'subtotal' => 'decimal:2',
            'email_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'received_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
