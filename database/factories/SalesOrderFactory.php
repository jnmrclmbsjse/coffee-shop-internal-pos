<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ServiceType;
use App\Models\BusinessDay;
use App\Models\SalesOrder;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    /**
     * Define the model's default state — a parked order with no payment yet.
     * order_number is UNIQUE per business_day; unique() keeps generated orders
     * distinct even when several share a day.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_day_id' => BusinessDay::factory(),
            'order_number' => fake()->unique()->numberBetween(1, 1_000_000),
            'customer_name' => fake()->optional()->firstName(),
            'service_type' => ServiceType::TakeOut,
            'payment_method' => null,
            'status' => OrderStatus::Parked,
            'created_by' => Staff::factory(),
        ];
    }

    /**
     * A completed order — must declare a payment method (chk_completed_has_payment).
     */
    public function completed(PaymentMethod $method = PaymentMethod::Cash): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Completed,
            'payment_method' => $method,
            'completed_at' => now(),
        ]);
    }

    /**
     * A voided order — excluded from every sales/cash/cup sum.
     */
    public function void(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Void,
            'voided_at' => now(),
            'void_reason' => fake()->sentence(3),
        ]);
    }
}
