<?php

namespace Database\Factories;

use App\Enums\MaritalStatus;
use App\Models\Guarantor;
use App\Models\Lease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guarantor>
 */
class GuarantorFactory extends Factory
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
            'name' => fake()->name(),
            'cpf_cnpj' => fake()->numerify('###########'),
            'rg' => fake()->numerify('#########'),
            'nationality' => 'brasileira',
            'marital_status' => fake()->randomElement(MaritalStatus::cases()),
            'profession' => fake()->jobTitle(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('###########'),
            'zip_code' => fake()->numerify('########'),
            'street' => fake()->streetName(),
            'number' => fake()->buildingNumber(),
            'complement' => null,
            'neighborhood' => fake()->word(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'spouse_name' => null,
            'spouse_cpf' => null,
            'property_registration' => null,
        ];
    }
}
