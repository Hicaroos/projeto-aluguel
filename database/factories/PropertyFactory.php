<?php

namespace Database\Factories;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Account;
use App\Models\Owner;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
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
            'owner_id' => fn (array $attributes) => Owner::factory()->create(['account_id' => $attributes['account_id']])->id,
            'type' => PropertyType::House,
            'zip_code' => fake()->numerify('#####-###'),
            'street' => fake()->streetName(),
            'number' => fake()->buildingNumber(),
            'complement' => null,
            'neighborhood' => ucfirst(fake()->word()).' '.ucfirst(fake()->word()),
            'city' => fake()->city(),
            'state' => fake()->randomElement(['SP', 'RJ', 'MG', 'PR', 'SC', 'RS', 'BA', 'GO', 'PE', 'CE']),
            'rent_amount' => fake()->randomFloat(2, 500, 5000),
            'status' => PropertyStatus::Available,
        ];
    }
}
