<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory()->agency(),
            'name' => fake()->unique()->city(),
            'document' => null,
            'creci' => null,
            'phone' => fake()->numerify('119########'),
            'zip_code' => null,
            'street' => null,
            'number' => null,
            'complement' => null,
            'neighborhood' => null,
            'city' => fake()->city(),
            'state' => fake()->randomElement(['SP', 'RJ', 'MG', 'PE', 'CE']),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the branch is no longer in use.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
