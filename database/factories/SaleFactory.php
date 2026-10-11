<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Property;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
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
            'buyer_name' => fake()->name(),
            'price' => fake()->numberBetween(100, 900) * 1000,
            'sold_on' => today(),
        ];
    }
}
