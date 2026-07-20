<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sellable drink/item. Price lives on each `ProductSize`, not on the product.
 */
#[Fillable(['category_id', 'name', 'is_active'])]
class Product extends PosModel
{
    protected $table = 'product';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * @return HasMany<ProductSize, $this>
     */
    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class, 'product_id');
    }
}
