<?php

namespace Database\Factories;

use App\Enums\VisitStatus;
use App\Models\Deal;
use App\Models\DealVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealVisit>
 */
class DealVisitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deal_id' => Deal::factory(),
            'account_id' => fn (array $attributes) => Deal::withoutGlobalScopes()->find($attributes['deal_id'])?->account_id,
            'scheduled_at' => now()->addDays(2)->setTime(10, 0),
            'status' => VisitStatus::Scheduled,
        ];
    }
}
