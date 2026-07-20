<?php

namespace App\Models;

use App\Enums\DayType;
use App\Enums\StockLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Par thresholds for a stock item, per day type. Either the *_qty set
 * (quantity items) or the *_level set (level items) is populated, and at least
 * one target must be set (chk_par_has_target). Unlike other POS tables, this one
 * has no timestamp columns at all.
 */
#[Fillable([
    'stock_item_id',
    'day_type',
    'par_qty',
    'low_qty_threshold',
    'urgent_qty_threshold',
    'par_level_value',
    'low_level_threshold',
    'urgent_level_threshold',
])]
class ParLevel extends PosModel
{
    protected $table = 'par_level';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_type' => DayType::class,
            'par_qty' => 'decimal:2',
            'low_qty_threshold' => 'decimal:2',
            'urgent_qty_threshold' => 'decimal:2',
            'par_level_value' => StockLevel::class,
            'low_level_threshold' => StockLevel::class,
            'urgent_level_threshold' => StockLevel::class,
        ];
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }
}
