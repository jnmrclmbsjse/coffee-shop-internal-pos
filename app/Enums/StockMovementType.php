<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Postgres enum `stock_movement_type`. Adjusts cup/lid balance between counts.
 */
enum StockMovementType: string implements HasLabel
{
    case Delivery = 'delivery';
    case Wastage = 'wastage';

    public function getLabel(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery',
            self::Wastage => 'Wastage',
        };
    }
}
