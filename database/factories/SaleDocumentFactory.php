<?php

namespace Database\Factories;

use App\Enums\SaleDocumentType;
use App\Models\Sale;
use App\Models\SaleDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleDocument>
 */
class SaleDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'account_id' => fn (array $attributes) => Sale::withoutGlobalScopes()->find($attributes['sale_id'])?->account_id,
            'type' => SaleDocumentType::SignedContract,
            'name' => 'contrato-assinado.pdf',
            'path' => fn (array $attributes) => "sales/{$attributes['sale_id']}/".fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(50_000, 2_000_000),
        ];
    }
}
