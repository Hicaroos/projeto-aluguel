<?php

namespace App\Http\Controllers;

use App\Actions\Showcase\PresentShowcaseProperty;
use App\Actions\Showcase\PresentShowcaseSite;
use App\Enums\PropertyAmenity;
use App\Enums\PropertyType;
use App\Models\Account;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The public showcase of an account's properties, open to anyone with its address.
 */
class ShowcaseController extends Controller
{
    /**
     * How many properties each page of the listing shows.
     */
    private const int PER_PAGE = 12;

    /**
     * How the visitor may sort the listing, as written in the address.
     *
     * @var list<string>
     */
    private const array SORTS = ['recentes', 'menor-preco', 'maior-preco', 'maior-area'];

    public function __construct(
        private PresentShowcaseProperty $present,
        private PresentShowcaseSite $presentSite,
    ) {}

    /**
     * Show the showcase home: a search over the featured property's photo and carousels of the latest
     * properties for rent and for sale, the featured ones first.
     */
    public function home(Account $showcase): Response
    {
        $home = $this->presentSite->home($showcase);

        return Inertia::render('showcase/Home', $home)->withViewData(['isShowcase' => true, 'meta' => [
            'title' => $showcase->name,
            'description' => $home['site']['headline'],
            'image' => $home['heroPhoto'],
            'url' => route('showcase.home', $showcase->showcase_slug),
        ]]);
    }

    /**
     * List the properties for sale or for rent, filtered and sorted as the visitor chose.
     */
    public function index(Request $request, Account $showcase): Response
    {
        $site = $this->presentSite->site($showcase);
        $purpose = match ($request->query('finalidade')) {
            'comprar' => 'sale',
            'alugar' => 'rent',
            default => $site['offers']['sale'] > 0 || $site['offers']['rent'] === 0 ? 'sale' : 'rent',
        };
        $priceColumn = $purpose === 'sale' ? 'sale_price' : 'rent_amount';
        $filters = [
            'purpose' => $purpose,
            'neighborhood' => $request->string('bairro')->toString(),
            'bedrooms' => max(0, $request->integer('quartos')),
            'max' => max(0, $request->integer('ate')),
            'sort' => in_array($request->query('ordem'), self::SORTS, true) ? (string) $request->query('ordem') : 'recentes',
            'type' => PropertyType::tryFrom($request->string('tipo')->toString())->value ?? '',
            'min' => max(0, $request->integer('de')),
            'bathrooms' => max(0, $request->integer('banheiros')),
            'parking' => max(0, $request->integer('vagas')),
            'area' => max(0, $request->integer('area')),
            'amenities' => array_values(array_filter(
                array_map(fn (PropertyAmenity $amenity): string => $amenity->value, PropertyAmenity::cases()),
                fn (string $amenity): bool => in_array($amenity, explode(',', $request->string('comodidades')->toString()), true),
            )),
        ];

        $properties = $this->presentSite->properties($showcase, $purpose)
            ->when($filters['neighborhood'] !== '', fn (Builder $query) => $query->where('neighborhood', $filters['neighborhood']))
            ->when($filters['bedrooms'] > 0, fn (Builder $query) => $query->where('bedrooms', '>=', $filters['bedrooms']))
            ->when($filters['max'] > 0, fn (Builder $query) => $query->where($priceColumn, '<=', $filters['max']))
            ->when($filters['min'] > 0, fn (Builder $query) => $query->where($priceColumn, '>=', $filters['min']))
            ->when($filters['type'] !== '', fn (Builder $query) => $query->where('type', $filters['type']))
            ->when($filters['bathrooms'] > 0, fn (Builder $query) => $query->where('bathrooms', '>=', $filters['bathrooms']))
            ->when($filters['parking'] > 0, fn (Builder $query) => $query->where('parking_spaces', '>=', $filters['parking']))
            ->when($filters['area'] > 0, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('built_area', '>=', $filters['area'])
                ->orWhere(fn (Builder $query) => $query->whereNull('built_area')->where('land_area', '>=', $filters['area']))))
            ->when($filters['amenities'] !== [], function (Builder $query) use ($filters): void {
                foreach ($filters['amenities'] as $amenity) {
                    $query->whereJsonContains('amenities', $amenity);
                }
            })
            ->when($filters['sort'] === 'menor-preco', fn (Builder $query) => $query->orderBy($priceColumn))
            ->when($filters['sort'] === 'maior-preco', fn (Builder $query) => $query->orderByDesc($priceColumn))
            ->when($filters['sort'] === 'maior-area', fn (Builder $query) => $query->orderByDesc('built_area'))
            ->latest('published_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Property $property): array => $this->present->card($showcase, $property));

        $title = $purpose === 'sale' ? __('Imóveis à venda') : __('Imóveis para alugar');

        return Inertia::render('showcase/Index', [
            'site' => $site,
            'filters' => $filters,
            'properties' => $properties,
            'neighborhoods' => $this->presentSite->neighborhoods($showcase),
            'types' => $this->types($showcase),
            'amenities' => $this->amenities($showcase),
        ])->withViewData(['isShowcase' => true, 'meta' => [
            'title' => "{$title} · {$showcase->name}",
            'description' => $site['headline'],
            'image' => $this->ogImage($showcase, null),
            'url' => route('showcase.index', ['showcase' => $showcase->showcase_slug, 'finalidade' => $purpose === 'sale' ? 'comprar' : 'alugar']),
        ]]);
    }

    /**
     * Show a property with all its photos, its details and the ways to talk to the agency about it.
     */
    public function show(Account $showcase, int $property): Response
    {
        $shown = $this->presentSite->properties($showcase)->findOrFail($property);
        $details = $this->present->details($showcase, $shown);
        $purpose = $details['sale_price'] !== null ? 'sale' : 'rent';

        $others = $this->presentSite->properties($showcase, $purpose)
            ->whereKeyNot($shown->id)
            ->orderByRaw('case when neighborhood = ? then 0 else 1 end', [$shown->neighborhood])
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Property $property): array => $this->present->card($showcase, $property))
            ->all();

        $price = $details['sale_price'] !== null
            ? PresentShowcaseProperty::price($details['sale_price'])
            : PresentShowcaseProperty::price((float) $details['rent']).'/mês';

        return Inertia::render('showcase/Show', [
            'site' => $this->presentSite->site($showcase),
            'property' => $details,
            'others' => $others,
            'neighborhoods' => $this->presentSite->neighborhoods($showcase),
        ])->withViewData(['isShowcase' => true, 'meta' => [
            'title' => $details['headline'],
            'description' => collect([
                $price,
                $details['bedrooms'] ? trans_choice(':count quarto|:count quartos', $details['bedrooms']) : null,
                $details['built_area'] ? PresentShowcaseProperty::squareMeters($details['built_area']) : null,
                "{$details['neighborhood']}, {$details['city']}",
            ])->filter()->join(' · '),
            'image' => $this->ogImage($showcase, $shown),
            'url' => route('showcase.show', ['showcase' => $showcase->showcase_slug, 'property' => $shown->id]),
        ]]);
    }

    /**
     * Serve a photo of a property on the showcase in full size.
     */
    public function photo(Account $showcase, int $photo): StreamedResponse
    {
        return $this->serve($this->publishedPhoto($showcase, $photo)->path);
    }

    /**
     * Serve the thumbnail of a photo of a property on the showcase, falling back to the full size photo.
     */
    public function thumbnail(Account $showcase, int $photo): StreamedResponse
    {
        $published = $this->publishedPhoto($showcase, $photo);

        return $this->serve($published->thumbnail_path ?? $published->path);
    }

    /**
     * Serve the logo of the account, shown in the showcase header.
     */
    public function logo(Account $showcase): StreamedResponse
    {
        abort_if($showcase->logo_path === null, 404);

        return $this->serve($showcase->logo_path, Account::LOGO_DISK);
    }

    /**
     * Get the property types on the showcase, to filter by.
     *
     * @return list<string>
     */
    private function types(Account $showcase): array
    {
        $types = Property::query()
            ->where('account_id', $showcase->id)
            ->onShowcase()
            ->distinct()
            ->pluck('type');

        return array_values(array_map(
            fn (PropertyType $type): string => $type->value,
            array_filter(PropertyType::cases(), fn (PropertyType $type): bool => $types->contains($type)),
        ));
    }

    /**
     * Get what the properties on the showcase offer, in the order of the list, to filter by.
     *
     * @return list<string>
     */
    private function amenities(Account $showcase): array
    {
        $offered = Property::query()
            ->where('account_id', $showcase->id)
            ->onShowcase()
            ->whereNotNull('amenities')
            ->pluck('amenities')
            ->flatten();

        return array_values(array_map(
            fn (PropertyAmenity $amenity): string => $amenity->value,
            array_filter(PropertyAmenity::cases(), fn (PropertyAmenity $amenity): bool => $offered->contains($amenity)),
        ));
    }

    /**
     * Get the photo link previews show for the page: the property's cover, or the featured property's.
     */
    private function ogImage(Account $showcase, ?Property $property): ?string
    {
        $property ??= $this->presentSite->properties($showcase)->orderByDesc('is_featured')->latest('published_at')->first();
        $cover = $property?->photos->first();

        return $cover === null ? null : route('showcase.photos.show', ['showcase' => $showcase->showcase_slug, 'photo' => $cover->id]);
    }

    private function publishedPhoto(Account $showcase, int $photo): PropertyPhoto
    {
        return PropertyPhoto::query()
            ->where('account_id', $showcase->id)
            ->whereIn('property_id', Property::query()->where('account_id', $showcase->id)->onShowcase()->select('id'))
            ->findOrFail($photo);
    }

    private function serve(string $path, string $disk = PropertyPhoto::DISK): StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
