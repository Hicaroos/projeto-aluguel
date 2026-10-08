<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\LeaseRenewal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaseRenewal>
 */
class LeaseRenewalFactory extends Factory
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
            'previous_end_date' => fn (array $attributes) => Lease::find($attributes['lease_id'])?->end_date ?? now(),
            'new_end_date' => fn (array $attributes) => Lease::find($attributes['lease_id'])?->end_date->addYear() ?? now()->addYear(),
            'notes' => null,
        ];
    }
}
