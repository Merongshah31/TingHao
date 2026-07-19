<?php

namespace App\Support;

use Illuminate\Support\Str;

class StockPlannerDisplay
{
    public static function ingredientName(?string $name): string
    {
        $name = trim((string) $name);

        return match (Str::lower($name)) {
            'cadburry choc' => 'Cadbury Choc',
            'cookies chocholate' => 'Cookies Chocolate',
            default => $name,
        };
    }

    public static function supplierName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $name = trim($name);

        return match (Str::lower($name)) {
            'bahtera barem' => 'Bahtera Barem',
            default => $name,
        };
    }
}
