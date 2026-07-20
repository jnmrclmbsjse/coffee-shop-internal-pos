<?php

namespace Database\Factories;

use App\Enums\BusinessDayStatus;
use App\Enums\DayType;
use App\Models\BusinessDay;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessDay>
 */
class BusinessDayFactory extends Factory
{
    /**
     * Define the model's default state — an open, normal day. business_date is
     * UNIQUE, so each generated day gets a distinct date.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_date' => fake()->unique()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'day_type' => DayType::Normal,
            'status' => BusinessDayStatus::Open,
            'cash_float' => 1000,
            'opened_by' => Staff::factory(),
        ];
    }

    public function peak(): static
    {
        return $this->state(fn (array $attributes) => ['day_type' => DayType::Peak]);
    }

    /**
     * A closed day. Note: this only flips the status flag; the cash/cup snapshot
     * columns are produced by fn_close_business_day, not by this factory.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BusinessDayStatus::Closed]);
    }
}
