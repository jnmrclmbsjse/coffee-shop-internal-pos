<?php

namespace Database\Factories;

use App\Enums\StockLevel;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockCountLine>
 */
class StockCountLineFactory extends Factory
{
    /**
     * Define the model's default state — a quantity count for a quantity item.
     * The count field must match the item's count_method (enforced by
     * trg_stock_count_line_biu), so the level() state swaps both together.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_count_id' => StockCount::factory(),
            'stock_item_id' => StockItem::factory(),
            'counted_qty' => 40,
            'counted_level' => null,
        ];
    }

    /**
     * A level count for a level item. Clears counted_qty.
     */
    public function level(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_item_id' => StockItem::factory()->level(),
            'counted_qty' => null,
            'counted_level' => StockLevel::Full,
        ]);
    }
}
