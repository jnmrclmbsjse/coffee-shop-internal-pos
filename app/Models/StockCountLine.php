<?php

namespace App\Models;

use App\Enums\ParStatus;
use App\Enums\StockLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single counted item on a stock count. Send `counted_qty` for quantity items
 * or `counted_level` for level items — trg_stock_count_line_biu enforces the
 * right field per the item's count_method and fills `computed_status` from
 * par_level. `expected_qty`/`variance` are snapshotted at close by
 * fn_close_business_day for reconciled (cup/lid) items. Those three columns are
 * DB-owned and read-only: kept out of #[Fillable], never written from PHP.
 * This table has no timestamp columns.
 */
#[Fillable(['stock_count_id', 'stock_item_id', 'counted_qty', 'counted_level', 'notes'])]
class StockCountLine extends PosModel
{
    protected $table = 'stock_count_line';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'counted_qty' => 'decimal:2',
            'counted_level' => StockLevel::class,
            'computed_status' => ParStatus::class,
            'expected_qty' => 'decimal:2',
            'variance' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<StockCount, $this>
     */
    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }
}
