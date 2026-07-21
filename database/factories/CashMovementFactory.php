<?php

namespace Database\Factories;

use App\Enums\CashMovementType;
use App\Models\BusinessDay;
use App\Models\CashMovement;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    /**
     * Define the model's default state — cash added to the drawer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_day_id' => BusinessDay::factory(),
            'type' => CashMovementType::CashIn,
            'amount' => 500,
            'reason' => fake()->sentence(3),
            'created_by' => Staff::factory(),
        ];
    }

    public function cashOut(): static
    {
        return $this->state(fn (array $attributes) => ['type' => CashMovementType::CashOut]);
    }
}
