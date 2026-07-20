<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Postgres enum `discount_type`. PWD/Senior are a flat 20% applied per line item.
 */
enum DiscountType: string implements HasLabel
{
    case None = 'none';
    case Pwd = 'pwd';
    case Senior = 'senior';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Pwd => 'PWD',
            self::Senior => 'Senior',
        };
    }
}
