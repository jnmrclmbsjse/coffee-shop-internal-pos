<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A grouping of stock items (e.g. Cups, Lids, Dairy, Others). The name of a
 * reconciled item's category also distinguishes cups from lids — see
 * EXISTING-PATTERNS §6 (cup/lid selects filter by category name).
 */
#[Fillable(['name', 'sort_weight', 'is_active'])]
class StockCategory extends PosModel
{
    protected $table = 'stock_category';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_weight' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<StockItem, $this>
     */
    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class, 'category_id');
    }
}
