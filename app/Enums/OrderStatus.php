<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Postgres enum `order_status`. Corrections are void + re-enter, never edits.
 */
enum OrderStatus: string implements HasColor, HasLabel
{
    case Parked = 'parked';
    case Completed = 'completed';
    case Void = 'void';

    public function getLabel(): string
    {
        return match ($this) {
            self::Parked => 'Parked',
            self::Completed => 'Completed',
            self::Void => 'Void',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Parked => 'warning',
            self::Completed => 'success',
            self::Void => 'danger',
        };
    }
}
