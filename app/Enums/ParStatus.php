<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Postgres enum `par_status`. Computed restock signal on v_inventory_status.
 */
enum ParStatus: string implements HasColor, HasLabel
{
    case Urgent = 'urgent';
    case Low = 'low';
    case BelowPar = 'below_par';
    case Enough = 'enough';

    public function getLabel(): string
    {
        return match ($this) {
            self::Urgent => 'Urgent',
            self::Low => 'Low',
            self::BelowPar => 'Below par',
            self::Enough => 'Enough',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Urgent => 'danger',
            self::Low => 'warning',
            self::BelowPar => 'info',
            self::Enough => 'success',
        };
    }
}
