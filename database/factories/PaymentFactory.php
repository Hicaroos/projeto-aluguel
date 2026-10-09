<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Lease;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lease_id' => Lease::factory(),
            'account_id' => fn (array $attributes) => Lease::withoutGlobalScopes()->find($attributes['lease_id'])->account_id,
            'reference_month' => today()->startOfMonth(),
            'due_date' => today()->startOfMonth()->addDays(9),
            'amount' => fn (array $attributes) => Lease::withoutGlobalScopes()->find($attributes['lease_id'])->amount,
            'status' => PaymentStatus::Pending,
        ];
    }

    /**
     * Indicate that the payment was due in the past and is still open.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_month' => today()->subMonthNoOverflow()->startOfMonth(),
            'due_date' => today()->subDays(5),
        ]);
    }
}
