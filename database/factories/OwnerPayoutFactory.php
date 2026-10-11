<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Owner;
use App\Models\OwnerPayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnerPayout>
 */
class OwnerPayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => Owner::factory(),
            'account_id' => fn (array $attributes) => Owner::withoutGlobalScopes()->whereKey($attributes['owner_id'])->value('account_id'),
            'amount' => fake()->randomFloat(2, 100, 2000),
            'paid_on' => today(),
            'payment_method' => PaymentMethod::Pix,
        ];
    }
}
