<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
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
            'name' => fake()->name(),
            'cpf_cnpj' => fake()->unique()->numerify('###########'),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('###########'),
        ];
    }
}
