<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyPhoto>
 */
class PropertyPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'account_id' => fn (array $attributes) => Property::withoutGlobalScope(Property::SCOPE)->find($attributes['property_id'])?->account_id,
            'path' => fn (array $attributes) => "properties/{$attributes['property_id']}/".fake()->uuid().'.jpg',
            'thumbnail_path' => null,
            'sort_order' => 0,
        ];
    }
}
