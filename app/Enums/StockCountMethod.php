<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Postgres enum `stock_count_method`. Reconciled items (cups/lids) must use
 * `quantity` (see chk_reconciled_is_quantity); count-only items may use `level`.
 */
enum StockCountMethod: string implements HasLabel
{
    case Quantity = 'quantity';
    case Level = 'level';

    public function getLabel(): string
    {
        return match ($this) {
            self::Quantity => 'Quantity',
            self::Level => 'Level',
        };
    }
}
