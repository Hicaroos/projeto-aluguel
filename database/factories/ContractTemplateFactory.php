<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\ContractTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractTemplate>
 */
class ContractTemplateFactory extends Factory
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
            'name' => 'Contrato '.fake()->word(),
            'body' => '<p>O LOCATÁRIO <span data-variable="tenant.name"></span> pagará <span data-variable="lease.amount"></span>.</p>',
            'is_default' => false,
        ];
    }

    /**
     * Indicate that the template is the account default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
