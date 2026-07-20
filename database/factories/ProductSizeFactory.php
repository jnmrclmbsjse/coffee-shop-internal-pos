<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductSize;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSize>
 */
class ProductSizeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'label' => fake()->randomElement(['S', 'M', 'L']),
            'price' => fake()->randomFloat(2, 40, 200),
            'cup_stock_item_id' => null,
            'lid_stock_item_id' => null,
            'sort_weight' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    /**
     * Map this size to a reconciled cup + lid (the drawdown for cup balancing).
     */
    public function withCupAndLid(): static
    {
        return $this->state(fn (array $attributes) => [
            'cup_stock_item_id' => StockItem::factory()->cup(),
            'lid_stock_item_id' => StockItem::factory()->lid(),
        ]);
    }
}
