<?php

namespace App\Models;

use App\Enums\BusinessDayStatus;
use App\Enums\DayType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The session everything anchors on: open -> orders / movements / counts -> close.
 * One row per calendar date (business_date is UNIQUE). The cash-reconciliation
 * snapshot columns (cash_sales..net_cash_turnover) plus closed_by/closed_at are
 * populated by fn_close_business_day at close — they are DB-authoritative and
 * kept out of #[Fillable], never written from PHP (see EXISTING-PATTERNS §6/§7).
 */
#[Fillable(['business_date', 'day_type', 'status', 'cash_float', 'opened_by'])]
class BusinessDay extends PosModel
{
    protected $table = 'business_day';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'day_type' => DayType::class,
            'status' => BusinessDayStatus::class,
            'cash_float' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'cash_sales' => 'decimal:2',
            'online_sales' => 'decimal:2',
            'gross_sales' => 'decimal:2',
            'total_expenses' => 'decimal:2',
            'total_cash_in' => 'decimal:2',
            'total_cash_out' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'cash_discrepancy' => 'decimal:2',
            'net_cash_turnover' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'opened_by');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'closed_by');
    }

    /**
     * @return HasMany<StockCount, $this>
     */
    public function stockCounts(): HasMany
    {
        return $this->hasMany(StockCount::class, 'business_day_id');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'business_day_id');
    }
}
