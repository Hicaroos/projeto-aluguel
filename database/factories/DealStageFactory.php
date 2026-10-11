<?php

namespace Database\Factories;

use App\Enums\DealOutcome;
use App\Models\Account;
use App\Models\DealStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealStage>
 */
class DealStageFactory extends Factory
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
            'name' => fake()->unique()->word(),
            'outcome' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the stage closes deals as won.
     */
    public function won(): static
    {
        return $this->state(fn (array $attributes) => ['outcome' => DealOutcome::Won, 'name' => 'Fechado']);
    }

    /**
     * Indicate that the stage closes deals as lost.
     */
    public function lost(): static
    {
        return $this->state(fn (array $attributes) => ['outcome' => DealOutcome::Lost, 'name' => 'Perdido']);
    }
}
