<?php

namespace App\Support;

use Illuminate\Support\Str;

class QuantityFormatter
{
    /**
     * Format stock quantities without duplicating broken numeric unit values.
     */
    public static function format(float|int|string|null $quantity, ?string $unit, int $decimalPlaces = 2): string
    {
        $quantity = is_numeric($quantity) ? (float) $quantity : 0.0;
        $unit = self::cleanUnit($unit);
        $decimals = self::usesWholeNumbers($unit) && floor($quantity) === $quantity ? 0 : $decimalPlaces;

        return trim(number_format($quantity, $decimals).' '.$unit);
    }

    public static function cleanUnit(?string $unit): string
    {
        $unit = trim((string) $unit);

        if ($unit === '' || is_numeric($unit)) {
            return '';
        }

        $unit = preg_replace('/^\d+(?:\.\d+)?\s+/', '', $unit) ?: $unit;

        if (Str::lower($unit) === 'botol') {
            return 'bottle';
        }

        return trim($unit);
    }

    private static function usesWholeNumbers(string $unit): bool
    {
        return Str::of($unit)->lower()->is([
            'botol',
            'bottle',
            'bottles',
            'carton',
            'cartons',
            'box',
            'boxes',
            'pack',
            'packs',
            'packet',
            'packets',
            'pc',
            'pcs',
            'piece',
            'pieces',
            'unit',
            'units',
        ]);
    }
}
