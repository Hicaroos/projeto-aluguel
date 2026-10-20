<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\ShowcaseRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShowcaseRequest>
 */
class ShowcaseRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'account_id' => fn (array $attributes) => Lead::withoutGlobalScopes()->whereKey($attributes['lead_id'])->value('account_id'),
            'name' => fake()->name(),
            'summary' => __('Pediu pela vitrine para alugar o imóvel.'),
            'message' => null,
        ];
    }
}
