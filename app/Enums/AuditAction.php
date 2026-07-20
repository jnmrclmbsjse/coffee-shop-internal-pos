<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Postgres enum `audit_action`. Recorded on the append-only audit_log.
 */
enum AuditAction: string implements HasLabel
{
    case Create = 'create';
    case Update = 'update';
    case Void = 'void';
    case Close = 'close';
    case Reopen = 'reopen';

    public function getLabel(): string
    {
        return match ($this) {
            self::Create => 'Create',
            self::Update => 'Update',
            self::Void => 'Void',
            self::Close => 'Close',
            self::Reopen => 'Reopen',
        };
    }
}
