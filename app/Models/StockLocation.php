<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'type',
    'notes',
    'is_active',
])]
class StockLocation extends Model
{
    use HasFactory;

    public const TYPE_STORAGE = 'storage';

    public const TYPE_PRODUCTION = 'production';

    public const TYPE_FRONT = 'front';

    public const TYPE_QUARANTINE = 'quarantine';

    public function stockAllocations(): HasMany
    {
        return $this->hasMany(StockAllocation::class);
    }

    public function isQuarantine(): bool
    {
        return $this->type === self::TYPE_QUARANTINE;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
