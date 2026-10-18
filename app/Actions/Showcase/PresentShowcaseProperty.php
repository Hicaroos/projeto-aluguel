<?php

namespace App\Actions\Showcase;

use App\Enums\PropertyType;
use App\Models\Account;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Support\Str;

/**
 * @phpstan-type ShowcaseCard array{id: int, ref: string, type: string, title: string, neighborhood: string, city: string, state: string, sale_price: float|null, rent: float|null, bedrooms: int|null, suites: int|null, bathrooms: int|null, parking_spaces: int|null, built_area: float|null, land_area: float|null, is_new: bool, photo: string|null}
 * @phpstan-type ShowcaseDetails array{id: int, ref: string, type: string, title: string, neighborhood: string, city: string, state: string, sale_price: float|null, rent: float|null, bedrooms: int|null, suites: int|null, bathrooms: int|null, parking_spaces: int|null, built_area: float|null, land_area: float|null, is_new: bool, photo: string|null, headline: string, land_front: float|null, land_depth: float|null, description: string|null, photos: list<array{url: string, thumbnail_url: string}>}
 */
class PresentShowcaseProperty
{
    /**
     * How long a newly published property shows as new.
     */
    private const int NEW_FOR_DAYS = 14;

    /**
     * Describe the property for the showcase cards. Only what visitors may see: the neighborhood and
     * city, never the street, the number, the owner or the registration.
     *
     * @return ShowcaseCard
     */
    public function card(Account $account, Property $property): array
    {
        $cover = $property->photos->first();

        return [
            'id' => $property->id,
            'ref' => self::ref($property),
            'type' => $property->type->value,
            'title' => self::title($property),
            'neighborhood' => $property->neighborhood,
            'city' => $property->city,
            'state' => $property->state,
            'sale_price' => self::amount($property->showcaseSalePrice()),
            'rent' => self::amount($property->showcaseRent()),
            'bedrooms' => $property->bedrooms,
            'suites' => $property->suites,
            'bathrooms' => $property->bathrooms,
            'parking_spaces' => $property->parking_spaces,
            'built_area' => self::amount($property->built_area),
            'land_area' => self::amount($property->land_area),
            'is_new' => $property->published_at?->gt(now()->subDays(self::NEW_FOR_DAYS)) ?? false,
            'photo' => $cover === null ? null : route('showcase.photos.thumbnail', ['showcase' => $account->showcase_slug, 'photo' => $cover->id]),
        ];
    }

    /**
     * Describe the property for its own showcase page, with every photo and the description.
     *
     * @return ShowcaseDetails
     */
    public function details(Account $account, Property $property): array
    {
        return [
            ...$this->card($account, $property),
            'headline' => self::headline($property),
            'land_front' => self::amount($property->land_front),
            'land_depth' => self::amount($property->land_depth),
            'description' => $property->description,
            'photos' => array_values($property->photos
                ->map(fn (PropertyPhoto $photo): array => [
                    'url' => route('showcase.photos.show', ['showcase' => $account->showcase_slug, 'photo' => $photo->id]),
                    'thumbnail_url' => route('showcase.photos.thumbnail', ['showcase' => $account->showcase_slug, 'photo' => $photo->id]),
                ])
                ->all()),
        ];
    }

    /**
     * Get the code visitors quote to the agency, e.g. "0042".
     */
    public static function ref(Property $property): string
    {
        return str_pad((string) $property->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Write the title of the property from what it is, e.g. "Casa com 3 quartos" or "Terreno com 300 m²".
     */
    public static function title(Property $property): string
    {
        $type = Str::ucfirst($property->type->label());

        if (($property->bedrooms ?? 0) > 0 && $property->type !== PropertyType::Land) {
            return $property->bedrooms === 1 ? "{$type} com 1 quarto" : "{$type} com {$property->bedrooms} quartos";
        }

        $area = $property->type === PropertyType::Land || $property->built_area === null
            ? $property->land_area
            : $property->built_area;

        return $area !== null && (float) $area > 0
            ? "{$type} com ".self::squareMeters((float) $area)
            : $type;
    }

    /**
     * Write the headline of the property page the way people search for it, e.g. "Casa com 3 quartos
     * para alugar no bairro Centro, em Ouricuri, PE".
     */
    public static function headline(Property $property): string
    {
        $isForSale = $property->showcaseSalePrice() !== null;
        $isForRent = $property->showcaseRent() !== null;
        $purpose = match (true) {
            $isForSale && $isForRent => 'à venda ou para alugar',
            $isForSale => 'à venda',
            default => 'para alugar',
        };
        $city = collect([$property->city, $property->state])->filter()->join(', ');

        return self::title($property)." {$purpose} no bairro {$property->neighborhood}".($city === '' ? '' : ", em {$city}");
    }

    /**
     * Format an area as people write it, e.g. "300 m²" or "72,5 m²".
     */
    public static function squareMeters(float $area): string
    {
        return number_format($area, fmod($area, 1.0) === 0.0 ? 0 : 1, ',', '.').' m²';
    }

    /**
     * Format a price as the showcase shows it, without cents, e.g. "R$ 320.000".
     */
    public static function price(float $value): string
    {
        return 'R$ '.number_format($value, 0, ',', '.');
    }

    private static function amount(?string $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
