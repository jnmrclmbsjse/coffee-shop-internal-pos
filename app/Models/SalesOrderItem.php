<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a sales order: a product_size at a snapshotted unit_price. The line
 * money (gross_amount, discount_amount, line_total) is GENERATED ALWAYS … STORED
 * — the per-line 20% PWD/Senior discount is encoded there off discount_type, so
 * we set only discount_type and never compute peso values. These generated
 * columns are read-only and kept out of #[Fillable] (see EXISTING-PATTERNS §6).
 */
#[Fillable([
    'sales_order_id', 'product_size_id', 'quantity', 'unit_price',
    'discount_type', 'taste_preference',
])]
class SalesOrderItem extends PosModel
{
    protected $table = 'sales_order_item';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'gross_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /**
     * @return BelongsTo<ProductSize, $this>
     */
    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class, 'product_size_id');
    }
}
