<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A grouping of products in the POS grid. `sort_weight` drives display order.
 */
#[Fillable(['name', 'sort_weight', 'is_active'])]
class ProductCategory extends PosModel
{
    protected $table = 'product_category';

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
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
