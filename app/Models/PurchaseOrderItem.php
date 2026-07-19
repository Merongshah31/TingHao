<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_order_id',
    'ingredient_id',
    'description',
    'quantity',
    'unit',
    'unit_price',
    'line_total',
    'received_quantity',
    'accepted_quantity',
    'damaged_quantity',
    'returned_quantity',
    'shortage_quantity',
    'quality_status',
    'receiving_notes',
])]
class PurchaseOrderItem extends Model
{
    use HasFactory;

    public const QUALITY_ACCEPTED = 'accepted';

    public const QUALITY_PARTIALLY_ACCEPTED = 'partially_accepted';

    public const QUALITY_DAMAGED = 'damaged';

    public const QUALITY_REJECTED = 'rejected';

    public const QUALITY_SHORTAGE = 'shortage';

    public const QUALITY_RETURNED = 'returned';

    public const QUALITY_STATUSES = [
        self::QUALITY_ACCEPTED,
        self::QUALITY_PARTIALLY_ACCEPTED,
        self::QUALITY_DAMAGED,
        self::QUALITY_REJECTED,
        self::QUALITY_SHORTAGE,
        self::QUALITY_RETURNED,
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function stockAllocations(): HasMany
    {
        return $this->hasMany(StockAllocation::class);
    }

    public function supplierReturns(): HasMany
    {
        return $this->hasMany(SupplierReturn::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'received_quantity' => 'decimal:2',
            'accepted_quantity' => 'decimal:2',
            'damaged_quantity' => 'decimal:2',
            'returned_quantity' => 'decimal:2',
            'shortage_quantity' => 'decimal:2',
        ];
    }
}
