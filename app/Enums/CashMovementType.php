<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Postgres enum `cash_movement_type`. Cash in/out of the drawer during the day
 * (feeds expected_cash: + cash_in − cash_out).
 */
enum CashMovementType: string implements HasLabel
{
    case CashIn = 'cash_in';
    case CashOut = 'cash_out';

    public function getLabel(): string
    {
        return match ($this) {
            self::CashIn => 'Cash in',
            self::CashOut => 'Cash out',
        };
    }
}
