<?php

namespace App\Models;

use App\Enums\CountPhase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One count sheet for a business day and phase (opening or closing) — unique on
 * (business_day_id, phase). Records who did the count (the roster roles) and its
 * lines. This table has no created_at/updated_at (only submitted_at), so
 * timestamps are disabled.
 */
#[Fillable([
    'business_day_id',
    'phase',
    'shift_lead_id',
    'production_support_id',
    'backup_staff_id',
    'submitted_by_id',
    'submitted_at',
    'notes',
])]
class StockCount extends PosModel
{
    protected $table = 'stock_count';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phase' => CountPhase::class,
            'submitted_at' => 'datetime',
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
    public function shiftLead(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'shift_lead_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function productionSupport(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'production_support_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function backupStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'backup_staff_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'submitted_by_id');
    }

    /**
     * @return HasMany<StockCountLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class, 'stock_count_id');
    }
}
