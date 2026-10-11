<?php

namespace Database\Factories;

use App\Enums\ExpenseDocumentType;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseDocument>
 */
class ExpenseDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'account_id' => fn (array $attributes) => Expense::withoutGlobalScopes()->whereKey($attributes['expense_id'])->value('account_id'),
            'type' => ExpenseDocumentType::SignedReceipt,
            'name' => 'recibo-assinado.pdf',
            'path' => fn (array $attributes) => "expenses/{$attributes['expense_id']}/".fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(50_000, 2_000_000),
        ];
    }
}
