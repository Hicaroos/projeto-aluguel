<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'type' => AccountType::SingleOwner,
            'plan' => null,
            'status' => AccountStatus::Trial,
        ];
    }

    /**
     * Indicate that the account is an agency.
     */
    public function agency(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountType::Agency,
        ]);
    }
}
