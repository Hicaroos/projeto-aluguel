<?php

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnerLedgerEntry>
 */
class OwnerLedgerEntryFactory extends Factory
{
    /**
     * Define the model's default state: an adjustment in the owner's favor, available right away.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => Owner::factory(),
            'account_id' => fn (array $attributes) => Owner::withoutGlobalScopes()->whereKey($attributes['owner_id'])->value('account_id'),
            'type' => LedgerEntryType::Adjustment,
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 100, 2000),
            'occurred_on' => today(),
            'available_on' => today(),
        ];
    }
}
