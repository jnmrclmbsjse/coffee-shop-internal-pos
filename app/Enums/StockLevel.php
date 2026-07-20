<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Postgres enum `stock_level`. Coarse fill level for count-by-level items.
 */
enum StockLevel: string implements HasLabel
{
    case Empty = 'empty';
    case Low = 'low';
    case Quarter = 'quarter';
    case Third = 'third';
    case Half = 'half';
    case TwoThirds = 'two_thirds';
    case ThreeQuarters = 'three_quarters';
    case Full = 'full';

    public function getLabel(): string
    {
        return match ($this) {
            self::Empty => 'Empty',
            self::Low => 'Low',
            self::Quarter => 'Quarter',
            self::Third => 'One-third',
            self::Half => 'Half',
            self::TwoThirds => 'Two-thirds',
            self::ThreeQuarters => 'Three-quarters',
            self::Full => 'Full',
        };
    }
}
