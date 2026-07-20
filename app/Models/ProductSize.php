<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A purchasable size of a product (S/M/L). Holds the `price` and maps to the
 * specific cup + lid stock items that a completed sale draws down (cup balance).
 */
#[Fillable(['product_id', 'label', 'price', 'cup_stock_item_id', 'lid_stock_item_id', 'sort_weight', 'is_active'])]
class ProductSize extends PosModel
{
    protected $table = 'product_size';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sort_weight' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function cupStockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'cup_stock_item_id');
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function lidStockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'lid_stock_item_id');
    }
}
