<?php

namespace Database\Factories;

use App\Enums\AdjustmentIndex;
use App\Models\Lease;
use App\Models\LeaseAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaseAdjustment>
 */
class LeaseAdjustmentFactory extends Factory
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
            'effective_on' => fn (array $attributes) => Lease::find($attributes['lease_id'])?->start_date->addYear() ?? now(),
            'adjustment_index' => AdjustmentIndex::Igpm,
            'percent' => 4.5,
            'previous_amount' => 2000,
            'new_amount' => 2090,
            'notes' => null,
        ];
    }
}
