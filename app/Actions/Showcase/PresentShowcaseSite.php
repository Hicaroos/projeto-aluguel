<?php

namespace App\Actions\Showcase;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;

/**
 * Present what every page of an account's showcase shows about the account, and its home.
 *
 * @phpstan-import-type ShowcaseCard from PresentShowcaseProperty
 *
 * @phpstan-type ShowcaseSite array{name: string, slug: string, creci: string|null, phone: string|null, whatsapp: string|null, email: string|null, office: string|null, headline: string, texts: array<string, string>, socials: list<array{network: string, label: string, url: string}>, color: array{accent: string, strong: string, wash: string}, logo_url: string|null, is_agency: bool, branches: list<array{name: string, city: string|null, state: string|null, phone: string|null}>, offers: array{sale: int, rent: int}}
 * @phpstan-type ShowcaseHome array{site: ShowcaseSite, featured: ShowcaseCard|null, heroPhoto: string|null, forSale: list<ShowcaseCard>, forRent: list<ShowcaseCard>, neighborhoods: list<string>}
 */
class PresentShowcaseSite
{
    /**
     * How many properties each carousel on the home shows.
     */
    private const int PER_CAROUSEL = 8;

    public function __construct(private PresentShowcaseProperty $present) {}

    /**
     * Get the showcase home: the featured property's cover behind the hero and carousels of the latest
     * properties for rent and for sale, the featured ones first.
     *
     * @return ShowcaseHome
     */
    public function home(Account $showcase): array
    {
        $featured = $this->properties($showcase)
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->latest('id')
            ->first();
        $cover = $featured?->photos->first();

        return [
            'site' => $this->site($showcase),
            'featured' => $featured === null ? null : $this->present->card($showcase, $featured),
            'heroPhoto' => $cover === null ? null : route('showcase.photos.show', ['showcase' => $showcase->showcase_slug, 'photo' => $cover->id]),
            'forSale' => $this->latestCards($showcase, 'sale'),
            'forRent' => $this->latestCards($showcase, 'rent'),
            'neighborhoods' => $this->neighborhoods($showcase),
        ];
    }

    /**
     * Get what every showcase page shows about the account: its name, contacts, look and offers.
     *
     * @return ShowcaseSite
     */
    public function site(Account $showcase): array
    {
        $offers = $this->offers($showcase);
        $color = $showcase->showcaseColor();

        $branches = Branch::withoutGlobalScopes()
            ->where('account_id', $showcase->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'city', 'state', 'phone']);

        return [
            'name' => $showcase->name,
            'slug' => (string) $showcase->showcase_slug,
            'creci' => $showcase->creci,
            'phone' => $showcase->phone,
            'whatsapp' => $showcase->showcase_whatsapp,
            'email' => $showcase->email,
            'office' => $showcase->street === null ? null : collect([
                trim("{$showcase->street}, {$showcase->number}", ', '),
                $showcase->neighborhood,
                $showcase->city === null ? null : trim("{$showcase->city}, {$showcase->state}", ', '),
            ])->filter()->join(' · '),
            'headline' => $showcase->showcase_headline ?? $this->headlineFor($showcase, $offers),
            'texts' => $showcase->showcaseTexts(),
            'socials' => array_values(array_filter([
                $showcase->showcase_instagram === null ? null : ['network' => 'instagram', 'label' => 'Instagram', 'url' => $showcase->showcase_instagram],
                $showcase->showcase_facebook === null ? null : ['network' => 'facebook', 'label' => 'Facebook', 'url' => $showcase->showcase_facebook],
                $showcase->showcase_youtube === null ? null : ['network' => 'youtube', 'label' => 'YouTube', 'url' => $showcase->showcase_youtube],
            ])),
            'color' => ['accent' => $color->hex(), 'strong' => $color->strongHex(), 'wash' => $color->washHex()],
            'logo_url' => $showcase->logo_path === null ? null : route('showcase.logo', ['showcase' => $showcase->showcase_slug, 'v' => $showcase->updated_at?->timestamp]),
            'is_agency' => $showcase->isAgency(),
            'branches' => $branches->count() > 1
                ? array_values($branches->map(fn (Branch $branch): array => [
                    'name' => $branch->name,
                    'city' => $branch->city,
                    'state' => $branch->state,
                    'phone' => $branch->phone,
                ])->all())
                : [],
            'offers' => $offers,
        ];
    }

    /**
     * Get the home title the showcase writes from the account's city and offers, while the account keeps its own blank.
     */
    public function defaultHeadline(Account $showcase): string
    {
        return $this->headlineFor($showcase, $this->offers($showcase));
    }

    /**
     * Get the account's properties on the showcase, with their photos.
     *
     * @param  'sale'|'rent'|null  $purpose
     * @return Builder<Property>
     */
    public function properties(Account $showcase, ?string $purpose = null): Builder
    {
        return Property::query()
            ->where('account_id', $showcase->id)
            ->onShowcase($purpose)
            ->with('photos:id,property_id,sort_order');
    }

    /**
     * Get the neighborhoods with properties on the showcase, to filter by.
     *
     * @return list<string>
     */
    public function neighborhoods(Account $showcase): array
    {
        return array_values(Property::query()
            ->where('account_id', $showcase->id)
            ->onShowcase()
            ->distinct()
            ->orderBy('neighborhood')
            ->pluck('neighborhood')
            ->all());
    }

    /**
     * Count the properties on the showcase for sale and for rent.
     *
     * @return array{sale: int, rent: int}
     */
    private function offers(Account $showcase): array
    {
        return [
            'sale' => $this->properties($showcase, 'sale')->count(),
            'rent' => $this->properties($showcase, 'rent')->count(),
        ];
    }

    /**
     * Write the home title from the account's city and what it offers.
     *
     * @param  array{sale: int, rent: int}  $offers
     */
    private function headlineFor(Account $showcase, array $offers): string
    {
        $city = $showcase->showcaseCity();
        $where = $city === null ? '' : " em {$city}";

        return match (true) {
            $offers['sale'] > 0 && $offers['rent'] > 0 => "Imóveis para comprar e alugar{$where}.",
            $offers['rent'] > 0 => "Imóveis para alugar{$where}.",
            default => "Imóveis à venda{$where}.",
        };
    }

    /**
     * Get the latest properties for the purpose as cards, the featured ones first.
     *
     * @param  'sale'|'rent'  $purpose
     * @return list<ShowcaseCard>
     */
    private function latestCards(Account $showcase, string $purpose): array
    {
        return array_values($this->properties($showcase, $purpose)
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->latest('id')
            ->limit(self::PER_CAROUSEL)
            ->get()
            ->map(fn (Property $property): array => $this->present->card($showcase, $property))
            ->all());
    }
}
