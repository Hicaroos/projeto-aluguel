<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'account_id' => fn (array $attributes) => Payment::find($attributes['payment_id'])->account_id,
            'amount' => fn (array $attributes) => Payment::find($attributes['payment_id'])->amount,
            'date' => today(),
            'payment_method' => PaymentMethod::Pix,
            'notes' => null,
        ];
    }
}
