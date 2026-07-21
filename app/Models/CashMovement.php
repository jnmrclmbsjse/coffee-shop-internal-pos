<?php

namespace App\Models;

use App\Enums\CashMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cash added to or removed from the drawer during a business day. Feeds the cash
 * reconciliation (expected_cash: + cash_in − cash_out). Append-only. created_at only.
 */
#[Fillable(['business_day_id', 'type', 'amount', 'reason', 'created_by'])]
class CashMovement extends PosModel
{
    protected $table = 'cash_movement';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
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
