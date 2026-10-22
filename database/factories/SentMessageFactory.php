<?php

namespace Database\Factories;

use App\Enums\MessageTemplate;
use App\Models\Payment;
use App\Models\SentMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SentMessage>
 */
class SentMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'messageable_type' => Payment::class,
            'messageable_id' => Payment::factory(),
            'account_id' => fn (array $attributes) => Payment::withoutGlobalScopes()->whereKey($attributes['messageable_id'])->value('account_id'),
            'template' => MessageTemplate::PaymentReminder,
            'phone' => '5587999990000',
            'body' => fake()->sentence(),
        ];
    }
}
