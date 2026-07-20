<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Postgres enum `day_type`. Drives which par-level target set applies for a day.
 */
enum DayType: string implements HasLabel
{
    case Normal = 'normal';
    case Peak = 'peak';

    public function getLabel(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Peak => 'Peak',
        };
    }
}
