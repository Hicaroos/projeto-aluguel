<?php

namespace Database\Factories;

use App\Enums\PaymentPlan;
use App\Enums\ProposalStatus;
use App\Models\Deal;
use App\Models\DealProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealProposal>
 */
class DealProposalFactory extends Factory
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
            'amount' => fake()->numberBetween(100, 900) * 1000,
            'payment_plan' => PaymentPlan::Financing,
            'status' => ProposalStatus::Pending,
            'proposed_on' => today(),
        ];
    }
}
