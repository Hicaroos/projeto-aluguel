<?php

namespace Database\Factories;

use App\Enums\LeadInterest;
use App\Enums\LeadSource;
use App\Models\Account;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
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
            'phone' => fake()->numerify('879########'),
            'email' => fake()->safeEmail(),
            'source' => LeadSource::WhatsApp,
            'interest' => LeadInterest::Buy,
        ];
    }
}
