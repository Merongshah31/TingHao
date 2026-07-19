<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

#[Fillable([
    'category_id',
    'supplier_id',
    'name',
    'sku',
    'unit',
    'quantity',
    'minimum_stock',
    'cost_price',
    'selling_price',
    'expiry_date',
    'notes',
    'created_by',
    'updated_by',
])]
class Ingredient extends Model
{
    use HasFactory;

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function restockRequests(): HasMany
    {
        return $this->hasMany(RestockRequest::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function stockAllocations(): HasMany
    {
        return $this->hasMany(StockAllocation::class);
    }

    public function supplierReturns(): HasMany
    {
        return $this->hasMany(SupplierReturn::class);
    }

    public function expiryLossRecommendations(): HasMany
    {
        return $this->hasMany(ExpiryLossRecommendation::class);
    }

    public function latestRestockRequest(): HasMany
    {
        return $this->restockRequests()->latest();
    }

    public function currentRestockRequest(): HasOne
    {
        return $this->hasOne(RestockRequest::class)->latestOfMany();
    }

    public function activeRestockRequest(): HasOne
    {
        return $this->hasOne(RestockRequest::class)
            ->whereIn('status', RestockRequest::ACTIVE_STATUSES)
            ->latestOfMany();
    }

    public function isLowStock(): bool
    {
        return (float) $this->quantity <= (float) $this->minimum_stock;
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'minimum_stock');
    }

    public function scopeExpiringWithin(Builder $query, int $days = 30): Builder
    {
        return $query
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->whereDate('expiry_date', '<=', now()->addDays($days)->toDateString());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString());
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }
}
