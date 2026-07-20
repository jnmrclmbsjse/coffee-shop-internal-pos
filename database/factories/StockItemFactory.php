<?php

namespace Database\Factories;

use App\Enums\StockCountMethod;
use App\Models\StockCategory;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockItem>
 */
class StockItemFactory extends Factory
{
    /**
     * Define the model's default state — a plain count-only quantity item.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => StockCategory::factory(),
            'name' => fake()->unique()->words(2, true),
            'unit' => 'pcs',
            'size' => null,
            'count_method' => StockCountMethod::Quantity,
            'is_reconciled' => false,
            'is_critical' => false,
            'is_active' => true,
        ];
    }

    /**
     * Count-by-level item (e.g. milk). Cannot be reconciled.
     */
    public function level(): static
    {
        return $this->state(fn (array $attributes) => [
            'count_method' => StockCountMethod::Level,
            'is_reconciled' => false,
        ]);
    }

    /**
     * A reconciled cup, in a "Cups" category (drives cup balancing + the
     * category-name filter on product-size cup selects).
     */
    public function cup(): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => StockCategory::factory()->state(['name' => 'Cups']),
            'count_method' => StockCountMethod::Quantity,
            'is_reconciled' => true,
            'size' => 'M',
        ]);
    }

    /**
     * A reconciled lid, in a "Lids" category.
     */
    public function lid(): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => StockCategory::factory()->state(['name' => 'Lids']),
            'count_method' => StockCountMethod::Quantity,
            'is_reconciled' => true,
            'size' => 'M',
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => ['is_critical' => true]);
    }
}
