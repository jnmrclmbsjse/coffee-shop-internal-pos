<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A delivery or wastage of a stock item during a business day. Adjusts the
 * cup/lid balance between the opening and closing counts (see v_cup_balance).
 * Append-only, like every transactional record. Has created_at only.
 */
#[Fillable(['business_day_id', 'stock_item_id', 'type', 'quantity', 'reason', 'created_by'])]
class StockMovement extends PosModel
{
    protected $table = 'stock_movement';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:2',
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
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }
}
