<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'purchase_order_item_id',
    'supplier_id',
    'ingredient_id',
    'return_number',
    'damaged_quantity',
    'returned_quantity',
    'reason',
    'status',
    'created_by',
])]
class SupplierReturn extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT_TO_SUPPLIER = 'sent_to_supplier';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_REJECTED_BY_SUPPLIER = 'rejected_by_supplier';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SENT_TO_SUPPLIER,
        self::STATUS_RESOLVED,
        self::STATUS_REJECTED_BY_SUPPLIER,
    ];

    public const OPEN_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SENT_TO_SUPPLIER,
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'damaged_quantity' => 'decimal:2',
            'returned_quantity' => 'decimal:2',
        ];
    }
}
