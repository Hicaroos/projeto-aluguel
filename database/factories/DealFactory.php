<?php

namespace Database\Factories;

use App\Enums\DealType;
use App\Models\Account;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
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
            'lead_id' => fn (array $attributes) => Lead::factory()->create(['account_id' => $attributes['account_id']])->id,
            'deal_stage_id' => fn (array $attributes) => DealStage::factory()->create(['account_id' => $attributes['account_id']])->id,
            'type' => DealType::Sale,
            'value' => fake()->numberBetween(100, 900) * 1000,
        ];
    }
}
