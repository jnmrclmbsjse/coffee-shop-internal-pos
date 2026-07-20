<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * A member of the shop's staff roster. Roster data, not a login — staff are
 * referenced by business days and orders, never authenticated (shared login).
 */
#[Fillable(['name', 'is_active'])]
class Staff extends PosModel
{
    protected $table = 'staff';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
