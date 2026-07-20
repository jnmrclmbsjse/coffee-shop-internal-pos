<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Postgres enum `payment_method`. Null until an order is completed. Online
 * sales are excluded from the cash reconciliation.
 */
enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Online = 'online';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Online => 'Online',
        };
    }
}
