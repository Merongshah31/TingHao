<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_demo_id',
    'ingredient_id',
    'ingredient_name',
    'quantity',
    'unit',
    'unit_price',
    'line_total',
    'received_quantity',
    'quality_status',
])]
class PurchaseOrderDemoItem extends Model
{
    use HasFactory;

    public function purchaseOrderDemo(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderDemo::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'received_quantity' => 'decimal:2',
        ];
    }
}
