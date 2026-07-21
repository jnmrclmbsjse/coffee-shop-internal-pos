<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cash expense paid out of the drawer during a business day. Subtracted from
 * expected_cash in the reconciliation. Append-only. created_at only.
 */
#[Fillable(['business_day_id', 'amount', 'category', 'reason', 'created_by'])]
class Expense extends PosModel
{
    protected $table = 'expense';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<BusinessDay, $this>
     */
    public function businessDay(): BelongsTo
    {
        return $this->belongsTo(BusinessDay::class, 'business_day_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }
}
