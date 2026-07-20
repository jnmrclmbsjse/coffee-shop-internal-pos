<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Postgres enum `count_phase`. One opening and one closing count per business day.
 */
enum CountPhase: string implements HasLabel
{
    case Opening = 'opening';
    case Closing = 'closing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Opening => 'Opening',
            self::Closing => 'Closing',
        };
    }
}
