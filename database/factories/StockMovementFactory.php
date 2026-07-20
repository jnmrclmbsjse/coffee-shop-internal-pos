<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\BusinessDay;
use App\Models\Staff;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state — a delivery of a reconciled cup.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_day_id' => BusinessDay::factory(),
            'stock_item_id' => StockItem::factory()->cup(),
            'type' => StockMovementType::Delivery,
            'quantity' => 50,
            'reason' => fake()->sentence(3),
            'created_by' => Staff::factory(),
        ];
    }

    public function wastage(): static
    {
        return $this->state(fn (array $attributes) => ['type' => StockMovementType::Wastage]);
    }
}
