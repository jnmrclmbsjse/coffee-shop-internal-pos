<?php

namespace Database\Factories;

use App\Models\BusinessDay;
use App\Models\Expense;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state — a small supplies expense.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_day_id' => BusinessDay::factory(),
            'amount' => 200,
            'category' => 'supplies',
            'reason' => fake()->sentence(3),
            'created_by' => Staff::factory(),
        ];
    }
}
