<?php

namespace Database\Factories;

use App\Enums\GuaranteeType;
use App\Enums\LeaseStatus;
use App\Models\Account;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lease>
 */
class LeaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'account_id' => Account::factory(),
            'property_id' => fn (array $attributes) => Property::factory()->create(['account_id' => $attributes['account_id']])->id,
            'tenant_id' => fn (array $attributes) => Tenant::factory()->create(['account_id' => $attributes['account_id']])->id,
            'start_date' => $startDate,
            'end_date' => (clone $startDate)->modify('+30 months'),
            'amount' => fake()->numberBetween(8, 60) * 100,
            'due_day' => fake()->numberBetween(1, 28),
            'guarantee_type' => GuaranteeType::None,
            'deposit_amount' => null,
            'status' => LeaseStatus::Active,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the lease has ended.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaseStatus::Ended,
        ]);
    }
}
