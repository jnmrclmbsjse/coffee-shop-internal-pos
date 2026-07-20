<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Postgres enum `business_day_status`. The lifecycle of a business-day session.
 */
enum BusinessDayStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'gray',
        };
    }
}
