<?php

namespace Database\Factories;

use App\Enums\DayType;
use App\Enums\StockLevel;
use App\Models\ParLevel;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParLevel>
 */
class ParLevelFactory extends Factory
{
    /**
     * Define the model's default state — a quantity par for a quantity item.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_item_id' => StockItem::factory(),
            'day_type' => DayType::Normal,
            'par_qty' => 40,
            'low_qty_threshold' => 20,
            'urgent_qty_threshold' => 10,
            'par_level_value' => null,
            'low_level_threshold' => null,
            'urgent_level_threshold' => null,
        ];
    }

    /**
     * A level-based par (for count-by-level items). Clears the quantity set.
     */
    public function level(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_item_id' => StockItem::factory()->level(),
            'par_qty' => null,
            'low_qty_threshold' => null,
            'urgent_qty_threshold' => null,
            'par_level_value' => StockLevel::Half,
            'low_level_threshold' => StockLevel::Quarter,
            'urgent_level_threshold' => StockLevel::Low,
        ]);
    }

    public function peak(): static
    {
        return $this->state(fn (array $attributes) => ['day_type' => DayType::Peak]);
    }
}
