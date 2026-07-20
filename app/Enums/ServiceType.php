<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Postgres enum `service_type`. Dine-in vs take-out on a sales order.
 */
enum ServiceType: string implements HasLabel
{
    case DineIn = 'dine_in';
    case TakeOut = 'take_out';

    public function getLabel(): string
    {
        return match ($this) {
            self::DineIn => 'Dine-in',
            self::TakeOut => 'Take-out',
        };
    }
}
