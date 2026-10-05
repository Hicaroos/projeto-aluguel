<?php

namespace Database\Factories;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'property_id' => fn (array $attributes) => Property::factory()->create(['account_id' => $attributes['account_id']])->id,
            'type' => fake()->randomElement(ExpenseType::cases()),
            'description' => null,
            'amount' => fake()->numberBetween(5, 80) * 10,
            'due_date' => today()->startOfMonth()->addDays(fake()->numberBetween(0, 27)),
            'payment_date' => null,
            'status' => ExpenseStatus::Pending,
        ];
    }

    /**
     * Indicate that the expense has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpenseStatus::Paid,
            'payment_date' => $attributes['due_date'],
        ]);
    }
}
