<?php

namespace Database\Factories;

use App\Enums\CountPhase;
use App\Models\BusinessDay;
use App\Models\Staff;
use App\Models\StockCount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockCount>
 */
class StockCountFactory extends Factory
{
    /**
     * Define the model's default state — an opening count submitted by one staffer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_day_id' => BusinessDay::factory(),
            'phase' => CountPhase::Opening,
            'shift_lead_id' => Staff::factory(),
            'submitted_by_id' => Staff::factory(),
        ];
    }

    public function opening(): static
    {
        return $this->state(fn (array $attributes) => ['phase' => CountPhase::Opening]);
    }

    public function closing(): static
    {
        return $this->state(fn (array $attributes) => ['phase' => CountPhase::Closing]);
    }
}
