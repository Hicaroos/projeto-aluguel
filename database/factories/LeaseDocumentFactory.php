<?php

namespace Database\Factories;

use App\Enums\LeaseDocumentType;
use App\Models\Lease;
use App\Models\LeaseDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaseDocument>
 */
class LeaseDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lease_id' => Lease::factory(),
            'account_id' => fn (array $attributes) => Lease::withoutGlobalScope(Lease::SCOPE)->find($attributes['lease_id'])?->account_id,
            'type' => LeaseDocumentType::SignedContract,
            'name' => 'contrato-assinado.pdf',
            'path' => fn (array $attributes) => "leases/{$attributes['lease_id']}/".fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(50_000, 2_000_000),
        ];
    }
}
