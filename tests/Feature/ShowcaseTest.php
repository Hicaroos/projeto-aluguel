<?php

use App\Actions\Dashboard\BuildDayAgenda;
use App\Enums\DealType;
use App\Enums\LeadInterest;
use App\Enums\LeadSource;
use App\Enums\PropertyAmenity;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Lead;
use App\Models\Owner;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\ShowcaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * An agency showcase with properties for rent, for sale and both, plus properties that must stay off it:
 * unpublished, rented, sold, and published by another account.
 *
 * @return array{account: Account, owner: Owner, admin: User, rent: Property, sale: Property, both: Property, rentedForSale: Property, hidden: list<Property>}
 */
function showcaseScenario(): array
{
    $account = Account::factory()->agency()->withShowcase('imobizilla')->create(['name' => 'Imobizilla', 'city' => 'Ouricuri']);
    $owner = Owner::factory()->for($account, 'account')->create(['name' => 'Antônio Proprietário']);
    $property = fn (array $attributes) => Property::factory()
        ->for($account, 'account')
        ->for($owner, 'owner')
        ->published()
        ->create(['city' => 'Ouricuri', 'state' => 'PE', 'bedrooms' => 3, ...$attributes]);

    $rent = $property(['neighborhood' => 'Centro', 'street' => 'Rua Secreta', 'number' => '42', 'rent_amount' => 1300]);
    $sale = $property(['neighborhood' => 'Horizonte', 'for_rent' => false, 'for_sale' => true, 'rent_amount' => null, 'sale_price' => 280000, 'is_featured' => true]);
    $both = $property(['neighborhood' => 'São Sebastião', 'for_sale' => true, 'sale_price' => 320000, 'rent_amount' => 1500, 'bedrooms' => 4]);
    $rentedForSale = $property(['neighborhood' => 'Centro', 'status' => PropertyStatus::Rented, 'for_sale' => true, 'sale_price' => 250000, 'rent_amount' => 1000, 'bedrooms' => 2]);

    $hidden = [
        $property(['neighborhood' => 'Planalto', 'published_at' => null]),
        $property(['neighborhood' => 'Planalto', 'status' => PropertyStatus::Rented]),
        $property(['neighborhood' => 'Planalto', 'status' => PropertyStatus::Sold, 'for_rent' => false, 'for_sale' => true, 'sale_price' => 200000]),
        Property::factory()->for(Account::factory()->withShowcase('outra-imobiliaria'), 'account')->published()->create(['neighborhood' => 'Planalto']),
    ];

    return [
        'account' => $account,
        'owner' => $owner,
        'admin' => User::factory()->for($account, 'account')->create(),
        'rent' => $rent,
        'sale' => $sale,
        'both' => $both,
        'rentedForSale' => $rentedForSale,
        'hidden' => $hidden,
    ];
}

test('the showcase shows only the published properties still on offer, without their address or owner', function () {
    ['owner' => $owner, 'rent' => $rent, 'sale' => $sale, 'both' => $both, 'rentedForSale' => $rentedForSale] = showcaseScenario();

    $this->get('/imobizilla')
        ->assertOk()
        ->assertDontSee('Rua Secreta')
        ->assertDontSee($owner->name)
        ->assertSee('<meta property="og:title" content="Imobizilla">', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('showcase/Home')
            ->where('site.name', 'Imobizilla')
            ->where('site.offers', ['sale' => 3, 'rent' => 2])
            ->where('site.headline', 'Imóveis para comprar e alugar em Ouricuri.')
            ->where('featured.id', $sale->id)
            ->where('featured.title', 'Casa com 3 quartos')
            ->where('heroPhoto', null)
            ->where('forSale.0.id', $sale->id)
            ->where('forSale', fn ($cards) => collect($cards)->pluck('id')->sort()->values()->all() === collect([$sale->id, $both->id, $rentedForSale->id])->sort()->values()->all())
            ->where('forRent', fn ($cards) => collect($cards)->pluck('id')->sort()->values()->all() === collect([$rent->id, $both->id])->sort()->values()->all())
            ->where('neighborhoods', ['Centro', 'Horizonte', 'São Sebastião'])
        );
});

test('the listing filters by purpose, neighborhood and price, and sorts', function () {
    ['rent' => $rent, 'sale' => $sale, 'both' => $both, 'rentedForSale' => $rentedForSale] = showcaseScenario();

    $this->get(route('showcase.index', ['showcase' => 'imobizilla', 'finalidade' => 'alugar', 'bairro' => 'Centro']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('showcase/Index')
            ->where('filters.purpose', 'rent')
            ->where('properties.total', 1)
            ->where('properties.data.0.id', $rent->id)
        );

    $this->get(route('showcase.index', ['showcase' => 'imobizilla', 'finalidade' => 'comprar', 'ordem' => 'menor-preco']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('properties.data', fn ($cards) => collect($cards)->pluck('id')->all() === [$rentedForSale->id, $sale->id, $both->id])
            ->where('properties.data.0.rent', null)
        );

    $this->get(route('showcase.index', ['showcase' => 'imobizilla', 'finalidade' => 'comprar', 'ate' => 300000, 'quartos' => 3]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('properties.total', 1)
            ->where('properties.data.0.id', $sale->id)
        );
});

test('the listing filters by city, each city with its own neighborhoods', function () {
    ['account' => $account, 'owner' => $owner, 'rent' => $rent] = showcaseScenario();
    $elsewhere = Property::factory()
        ->for($account, 'account')
        ->for($owner, 'owner')
        ->published()
        ->create(['city' => 'Araripina', 'state' => 'PE', 'neighborhood' => 'Centro', 'rent_amount' => 900]);

    $this->get(route('showcase.home', 'imobizilla'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cities', [
                ['name' => 'Araripina', 'neighborhoods' => ['Centro']],
                ['name' => 'Ouricuri', 'neighborhoods' => ['Centro', 'Horizonte', 'São Sebastião']],
            ])
        );

    $this->get(route('showcase.index', ['showcase' => 'imobizilla', 'finalidade' => 'alugar', 'cidade' => 'Araripina', 'bairro' => 'Centro']))
        ->assertSee('<meta property="og:title" content="Imóveis para alugar em Araripina · Imobizilla">', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.city', 'Araripina')
            ->where('properties.total', 1)
            ->where('properties.data.0.id', $elsewhere->id)
            ->has('cities', 2)
        );

    $this->get(route('showcase.index', ['showcase' => 'imobizilla', 'finalidade' => 'alugar', 'bairro' => 'Centro']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.city', '')
            ->where('properties.data', fn ($cards) => collect($cards)->pluck('id')->sort()->values()->all() === collect([$rent->id, $elsewhere->id])->sort()->values()->all())
        );
});

test('the listing also filters by type, price range, bathrooms, parking, area and every amenity asked', function () {
    ['rent' => $rent, 'both' => $both] = showcaseScenario();
    $rent->update(['type' => PropertyType::Apartment, 'bathrooms' => 1, 'parking_spaces' => 1, 'built_area' => 70, 'amenities' => [PropertyAmenity::Pool]]);
    $both->update(['type' => PropertyType::House, 'bathrooms' => 3, 'parking_spaces' => 2, 'built_area' => 160, 'amenities' => [PropertyAmenity::Gym, PropertyAmenity::Pool]]);
    $listing = fn (array $query) => $this->get(route('showcase.index', ['showcase' => 'imobizilla', 'finalidade' => 'alugar', ...$query]));

    $listing([])->assertInertia(fn (Assert $page) => $page
        ->where('properties.total', 2)
        ->where('types', ['house', 'apartment'])
        ->where('amenities', ['pool', 'gym'])
    );

    foreach ([['tipo' => 'apartment'], ['tipo' => 'house'], ['de' => 1400, 'ate' => 2000], ['banheiros' => 2], ['vagas' => 2], ['area' => 100], ['comodidades' => 'pool,gym']] as $query) {
        $expected = $query === ['tipo' => 'apartment'] ? $rent->id : $both->id;

        $listing($query)->assertInertia(fn (Assert $page) => $page
            ->where('properties.total', 1)
            ->where('properties.data.0.id', $expected)
        );
    }

    $listing(['comodidades' => 'pool,heliporto'])->assertInertia(fn (Assert $page) => $page
        ->where('filters.amenities', ['pool'])
        ->where('properties.total', 2)
    );
});

test('the property page shows its details and a link preview, but not its address', function () {
    Storage::fake('local');
    ['both' => $both] = showcaseScenario();
    $photo = PropertyPhoto::factory()->for($both)->create();
    Storage::disk('local')->put($photo->path, 'photo');

    $this->get(route('showcase.show', ['showcase' => 'imobizilla', 'property' => $both->id]))
        ->assertOk()
        ->assertDontSee($both->street)
        ->assertSee('<meta property="og:image" content="'.route('showcase.photos.show', ['showcase' => 'imobizilla', 'photo' => $photo->id]).'">', false)
        ->assertSee('<meta property="og:description" content="R$ 320.000 · 4 quartos · São Sebastião, Ouricuri">', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('showcase/Show')
            ->where('property.ref', str_pad((string) $both->id, 4, '0', STR_PAD_LEFT))
            ->where('property.headline', 'Casa com 4 quartos à venda ou para alugar no bairro São Sebastião, em Ouricuri, PE')
            ->where('neighborhoods', ['Centro', 'Horizonte', 'São Sebastião'])
            ->where('property.sale_price', 320000)
            ->where('property.rent', 1500)
            ->has('property.photos', 1)
            ->missing('property.street')
            ->missing('property.number')
            ->missing('property.owner_id')
        );

    $this->get(route('showcase.photos.show', ['showcase' => 'imobizilla', 'photo' => $photo->id]))->assertOk();
});

test('properties off the showcase, their photos and offline showcases are not found', function () {
    Storage::fake('local');
    ['account' => $account, 'hidden' => $hidden] = showcaseScenario();
    $photo = PropertyPhoto::factory()->for($hidden[0])->create();
    Storage::disk('local')->put($photo->path, 'photo');

    foreach ($hidden as $property) {
        $this->get(route('showcase.show', ['showcase' => 'imobizilla', 'property' => $property->id]))->assertNotFound();
    }

    $this->get(route('showcase.photos.show', ['showcase' => 'imobizilla', 'photo' => $photo->id]))->assertNotFound();
    $this->get('/nao-existe')->assertNotFound();

    $account->update(['showcase_enabled' => false]);

    $this->get('/imobizilla')->assertNotFound();
});

test('a visit request becomes a lead in the funnel, once per phone', function () {
    ['admin' => $admin, 'both' => $both] = showcaseScenario();
    $visit = ['name' => 'Ana Souza', 'phone' => '(87) 98888-7777', 'period' => 'tarde', 'purpose' => 'rent'];

    $this->post(route('showcase.visits.store', ['showcase' => 'imobizilla', 'property' => $both->id]), $visit)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $lead = Lead::withoutGlobalScopes()->sole();
    $deal = Deal::withoutGlobalScopes()->sole();

    expect($lead)
        ->account_id->toBe($both->account_id)
        ->branch_id->toBe($both->branch_id)
        ->phone->toBe('87988887777')
        ->source->toBe(LeadSource::Website)
        ->notes->toBeNull()
        ->and(ShowcaseRequest::withoutGlobalScopes()->sole())
        ->lead_id->toBe($lead->id)
        ->property_id->toBe($both->id)
        ->name->toBe('Ana Souza')
        ->summary->toContain('REF '.str_pad((string) $both->id, 4, '0', STR_PAD_LEFT))
        ->summary->toContain('pela tarde')
        ->and($deal)
        ->lead_id->toBe($lead->id)
        ->property_id->toBe($both->id)
        ->type->toBe(DealType::Rent)
        ->value->toBe('1500.00')
        ->and($deal->stage->is(DealStage::withoutGlobalScopes()->where('account_id', $both->account_id)->whereNull('outcome')->orderBy('sort_order')->first()))->toBeTrue();

    $this->post(route('showcase.visits.store', ['showcase' => 'imobizilla', 'property' => $both->id]), [...$visit, 'period' => 'sabado']);

    expect(Lead::withoutGlobalScopes()->count())->toBe(1)
        ->and(Deal::withoutGlobalScopes()->count())->toBe(1)
        ->and(ShowcaseRequest::withoutGlobalScopes()->latest('id')->first()->summary)->toContain('no sábado');

    $this->actingAs($admin);
    $items = collect(app(BuildDayAgenda::class)->handle($admin)['items'])->keyBy('key');

    expect($items['showcase_requests']['count'])->toBe(1);
});

test('visit requests need a name and a phone, and ignore bots', function () {
    ['rent' => $rent, 'sale' => $sale] = showcaseScenario();
    $url = route('showcase.visits.store', ['showcase' => 'imobizilla', 'property' => $rent->id]);

    $this->post($url, ['name' => '', 'phone' => '123', 'period' => 'manha', 'purpose' => 'rent'])
        ->assertSessionHasErrors(['name', 'phone']);

    $this->post(route('showcase.visits.store', ['showcase' => 'imobizilla', 'property' => $sale->id]), ['name' => 'Ana', 'phone' => '87988887777', 'period' => 'manha', 'purpose' => 'rent'])
        ->assertSessionHasErrors('purpose');

    $this->post($url, ['name' => 'Robô', 'phone' => '87988887777', 'period' => 'manha', 'purpose' => 'rent', 'website' => 'https://spam.example'])
        ->assertRedirect();

    expect(Lead::withoutGlobalScopes()->count())->toBe(0);
});

test('an owner who wants to list a property becomes a lead, once per phone', function () {
    ['account' => $account] = showcaseScenario();
    $url = route('showcase.listings.store', ['showcase' => 'imobizilla']);
    $listing = ['name' => 'João Dono', 'phone' => '(87) 97777-6666', 'goal' => 'alugar', 'neighborhood' => 'Centro', 'message' => 'Casa de 3 quartos, desocupada.'];

    $this->post($url, $listing)->assertSessionHasNoErrors()->assertRedirect();

    expect(Lead::withoutGlobalScopes()->sole())
        ->account_id->toBe($account->id)
        ->phone->toBe('87977776666')
        ->source->toBe(LeadSource::Website)
        ->interest->toBe(LeadInterest::List)
        ->notes->toBeNull()
        ->and(ShowcaseRequest::withoutGlobalScopes()->sole())
        ->name->toBe('João Dono')
        ->summary->toContain('alugar o imóvel, no bairro Centro')
        ->message->toBe('Casa de 3 quartos, desocupada.');

    $this->post($url, [...$listing, 'goal' => 'vender', 'neighborhood' => '', 'message' => '']);

    expect(Lead::withoutGlobalScopes()->count())->toBe(1)
        ->and(ShowcaseRequest::withoutGlobalScopes()->latest('id')->first()->summary)->toContain('vender o imóvel.')
        ->and(Deal::withoutGlobalScopes()->count())->toBe(0);
});

test('a request with the phone of a lead leaves their notes alone and shows apart on the lead', function () {
    ['account' => $account, 'admin' => $admin, 'both' => $both] = showcaseScenario();
    $lead = Lead::factory()->for($account, 'account')->create(['name' => 'Cliente Real', 'phone' => '87977776666', 'notes' => 'Prefere contato à noite.']);

    $this->post(route('showcase.listings.store', ['showcase' => 'imobizilla']), [
        'name' => 'Outra Pessoa', 'phone' => '(87) 97777-6666', 'goal' => 'vender', 'message' => 'Deposite na conta 123.',
    ])->assertSessionHasNoErrors();
    $this->post(route('showcase.visits.store', ['showcase' => 'imobizilla', 'property' => $both->id]), [
        'name' => 'Outra Pessoa', 'phone' => '(87) 97777-6666', 'period' => 'tarde', 'purpose' => 'rent',
    ])->assertSessionHasNoErrors();

    expect($lead->fresh())
        ->name->toBe('Cliente Real')
        ->notes->toBe('Prefere contato à noite.');

    $this->actingAs($admin)
        ->get(route('leads.index', ['show' => $lead->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('selected.showcase_requests', 2)
            ->where('selected.showcase_requests.0.name', 'Outra Pessoa')
            ->where('selected.showcase_requests.1.message', 'Deposite na conta 123.'));
});

test('a listing request waits on the agenda until the person is registered as an owner', function () {
    ['account' => $account, 'admin' => $admin] = showcaseScenario();
    $this->post(route('showcase.listings.store', ['showcase' => 'imobizilla']), ['name' => 'João Dono', 'phone' => '(87) 97777-6666', 'goal' => 'alugar']);
    $listingRequests = function () use ($admin): int {
        $this->actingAs($admin);

        return collect(app(BuildDayAgenda::class)->handle($admin)['items'])->firstWhere('key', 'listing_requests')['count'] ?? 0;
    };

    expect($listingRequests())->toBe(1);

    Owner::factory()->for($account, 'account')->create(['name' => 'João Dono', 'phone' => '87977776666']);

    expect($listingRequests())->toBe(0);
});

test('listing requests need a name, a phone and a goal, ignore bots, and only agencies take them', function () {
    showcaseScenario();
    $url = route('showcase.listings.store', ['showcase' => 'imobizilla']);

    $this->post($url, ['name' => '', 'phone' => '123', 'goal' => 'trocar'])
        ->assertSessionHasErrors(['name', 'phone', 'goal']);

    $this->post($url, ['name' => 'Robô', 'phone' => '87988887777', 'goal' => 'vender', 'website' => 'https://spam.example'])
        ->assertRedirect();

    Account::factory()->withShowcase('dono-direto')->create();

    $this->post(route('showcase.listings.store', ['showcase' => 'dono-direto']), ['name' => 'Ana', 'phone' => '87988887777', 'goal' => 'vender'])
        ->assertNotFound();

    expect(Lead::withoutGlobalScopes()->count())->toBe(0);
});

test('people signed in to another account see the showcase as any visitor', function () {
    ['rent' => $rent] = showcaseScenario();
    $outsider = User::factory()->for(Account::factory()->agency(), 'account')->create();

    $this->actingAs($outsider)
        ->get('/imobizilla')
        ->assertInertia(fn (Assert $page) => $page->where('site.offers', ['sale' => 3, 'rent' => 2]));

    $this->actingAs($outsider)
        ->post(route('showcase.visits.store', ['showcase' => 'imobizilla', 'property' => $rent->id]), ['name' => 'Ana', 'phone' => '87988887777', 'period' => 'manha', 'purpose' => 'rent'])
        ->assertSessionHasNoErrors();

    expect(Lead::withoutGlobalScopes()->sole()->account_id)->toBe($rent->account_id)
        ->and(DealStage::withoutGlobalScopes()->where('account_id', $outsider->account_id)->exists())->toBeFalse();
});

test('the app pages keep their addresses', function () {
    showcaseScenario();

    $this->get('/login')->assertOk();
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('administrators and single owners set up the showcase; its address must be free', function () {
    ['admin' => $admin, 'account' => $account] = showcaseScenario();
    $settings = ['showcase_enabled' => true, 'showcase_whatsapp' => '(87) 99999-1234', 'showcase_color' => 'ocean'];

    $this->actingAs($admin)
        ->patch(route('showcase-settings.update'), [...$settings, 'showcase_slug' => ' Imobizilla-Ouricuri '])
        ->assertSessionHasNoErrors();

    expect($account->fresh())
        ->showcase_slug->toBe('imobizilla-ouricuri')
        ->showcase_whatsapp->toBe('87999991234');

    foreach (['dashboard', 'login', 'outra-imobiliaria', 'com espaço'] as $slug) {
        $this->actingAs($admin)
            ->patch(route('showcase-settings.update'), [...$settings, 'showcase_slug' => $slug])
            ->assertSessionHasErrors('showcase_slug');
    }

    $agent = User::factory()->for($account, 'account')->withRole(Role::Agent)->create();
    $this->actingAs($agent)->get(route('showcase-settings.edit'))->assertForbidden();

    $singleOwner = User::factory()->for(Account::factory(), 'account')->create();
    $this->actingAs($singleOwner)
        ->get(route('showcase-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Showcase')
            ->where('showcase.showcase_enabled', false)
        );
});

test('publishing a property keeps the date it first went on the showcase', function () {
    $account = Account::factory()->create();
    Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();
    $payload = [
        'type' => 'house',
        'zip_code' => '56200-000',
        'street' => 'Rua Nova',
        'number' => '10',
        'neighborhood' => 'Centro',
        'city' => 'Ouricuri',
        'state' => 'PE',
        'rent_amount' => '1200.00',
        'status' => 'available',
        'is_published' => '1',
    ];

    $this->actingAs($user)->post(route('properties.store'), $payload)->assertSessionHasNoErrors();

    $property = Property::sole();
    expect($property->published_at?->toDateTimeString())->toBe('2026-10-15 09:00:00');

    $this->travel(2)->days();
    $this->actingAs($user)->put(route('properties.update', $property), [...$payload, 'is_featured' => '1']);

    expect($property->fresh())
        ->published_at->toDateTimeString()->toBe('2026-10-15 09:00:00')
        ->is_featured->toBeTrue();

    $this->actingAs($user)->put(route('properties.update', $property), [...$payload, 'is_published' => '0']);

    expect($property->fresh()->published_at)->toBeNull();
});

test('the property form saves the amenities, and unchecking them all clears them', function () {
    $account = Account::factory()->create();
    Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();
    $payload = [
        'type' => 'apartment',
        'zip_code' => '56200-000',
        'street' => 'Rua Nova',
        'number' => '10',
        'neighborhood' => 'Centro',
        'city' => 'Ouricuri',
        'state' => 'PE',
        'rent_amount' => '1200.00',
        'status' => 'available',
    ];

    $this->actingAs($user)
        ->post(route('properties.store'), [...$payload, 'amenities' => ['pool', 'gym']])
        ->assertSessionHasNoErrors();

    $property = Property::sole();
    expect($property->amenities->all())->toBe([PropertyAmenity::Pool, PropertyAmenity::Gym]);

    $this->actingAs($user)
        ->put(route('properties.update', $property), [...$payload, 'amenities' => ['heliport']])
        ->assertSessionHasErrors('amenities.0');

    $this->actingAs($user)->put(route('properties.update', $property), $payload)->assertSessionHasNoErrors();

    expect($property->fresh()->amenities->all())->toBe([]);
});

test('the property page lists the amenities apart from the condominium ones, and the address only when the account shows it', function () {
    ['account' => $account, 'rent' => $rent] = showcaseScenario();
    $rent->update(['amenities' => [PropertyAmenity::Gym, PropertyAmenity::Pool, PropertyAmenity::AirConditioning]]);
    $url = route('showcase.show', ['showcase' => 'imobizilla', 'property' => $rent->id]);

    $this->get($url)
        ->assertDontSee('Rua Secreta')
        ->assertInertia(fn (Assert $page) => $page
            ->where('property.amenities', ['property' => ['Ar-condicionado', 'Piscina'], 'condo' => ['Academia']])
            ->where('property.address', null)
        );

    $account->update(['showcase_shows_address' => true]);

    $this->get($url)
        ->assertInertia(fn (Assert $page) => $page->where('property.address', 'Rua Secreta, 42'));
});

test('the showcase settings save the social profiles as links and leave the texts alone', function () {
    ['admin' => $admin, 'account' => $account] = showcaseScenario();
    $account->update(['showcase_headline' => 'Casas em Ouricuri.', 'showcase_texts' => ['why_lead' => 'Vinte anos cuidando de imóveis.']]);
    $settings = [
        'showcase_enabled' => true,
        'showcase_slug' => 'imobizilla',
        'showcase_whatsapp' => '(87) 99999-1234',
        'showcase_color' => 'forest',
        'showcase_instagram' => '@imobizilla',
        'showcase_facebook' => 'facebook.com/imobizilla',
        'showcase_youtube' => 'https://www.youtube.com/@imobizilla',
    ];

    $this->actingAs($admin)->patch(route('showcase-settings.update'), $settings)->assertSessionHasNoErrors();

    expect($account->fresh())
        ->showcase_instagram->toBe('https://www.instagram.com/imobizilla')
        ->showcase_facebook->toBe('https://facebook.com/imobizilla')
        ->showcase_youtube->toBe('https://www.youtube.com/@imobizilla')
        ->showcase_headline->toBe('Casas em Ouricuri.')
        ->showcase_texts->toBe(['why_lead' => 'Vinte anos cuidando de imóveis.']);

    $this->get('/imobizilla')
        ->assertInertia(fn (Assert $page) => $page
            ->where('site.socials.0', ['network' => 'instagram', 'label' => 'Instagram', 'url' => 'https://www.instagram.com/imobizilla'])
            ->has('site.socials', 3)
        );

    $this->actingAs($admin)
        ->patch(route('showcase-settings.update'), [...$settings, 'showcase_instagram' => 'https://facebook.com/imobizilla', 'showcase_youtube' => 'meu canal'])
        ->assertSessionHasErrors(['showcase_instagram', 'showcase_youtube']);
});

test('the showcase texts are edited on its home, shown as visitors see it', function () {
    ['admin' => $admin, 'account' => $account] = showcaseScenario();
    $otherOwner = Owner::factory()->for($account, 'account')->create();

    $this->actingAs($admin)
        ->withSession([User::SELECTED_OWNER_SESSION_KEY => $otherOwner->id])
        ->get(route('showcase-settings.texts.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('showcase/Home')
            ->where('site.offers', ['sale' => 3, 'rent' => 2])
            ->where('site.texts.why_title', 'Por que escolher Imobizilla?')
            ->where('editor.defaults.headline', 'Imóveis para comprar e alugar em Ouricuri.')
            ->where('editor.defaults.owner_title', 'Tem um imóvel para alugar ou vender?')
            ->where('editor.limits.headline', 120)
            ->where('editor.limits.hero_lead', 240)
        );

    $agent = User::factory()->for($account, 'account')->withRole(Role::Agent)->create();
    $this->actingAs($agent)->get(route('showcase-settings.texts.edit'))->assertForbidden();
    $this->actingAs($agent)->put(route('showcase-settings.texts.update'), [])->assertForbidden();

    $account->update(['showcase_enabled' => false]);

    $this->actingAs($admin)
        ->get(route('showcase-settings.texts.edit'))
        ->assertRedirect(route('showcase-settings.edit'));
});

test('the showcase editor saves only the texts that differ from the defaults', function () {
    ['admin' => $admin, 'account' => $account] = showcaseScenario();
    $texts = [
        ...$account->showcaseTexts(),
        'why_lead' => '  Vinte anos cuidando de imóveis em Ouricuri.  ',
        'benefit_1_title' => '',
    ];

    $this->actingAs($admin)
        ->put(route('showcase-settings.texts.update'), [
            'showcase_headline' => 'Imóveis para comprar e alugar em Ouricuri.',
            'showcase_texts' => $texts,
        ])
        ->assertRedirect(route('showcase-settings.texts.edit'));

    expect($account->fresh())
        ->showcase_headline->toBeNull()
        ->showcase_texts->toBe(['why_lead' => 'Vinte anos cuidando de imóveis em Ouricuri.']);

    $this->actingAs($admin)
        ->put(route('showcase-settings.texts.update'), [
            'showcase_headline' => 'Casas e terrenos no sertão.',
            'showcase_texts' => [...$texts, 'owner_title' => 'Quer anunciar?'],
        ])
        ->assertSessionHasNoErrors();

    expect($account->fresh())
        ->showcase_headline->toBe('Casas e terrenos no sertão.')
        ->showcase_texts->toBe(['why_lead' => 'Vinte anos cuidando de imóveis em Ouricuri.', 'owner_title' => 'Quer anunciar?']);

    $this->get('/imobizilla')
        ->assertInertia(fn (Assert $page) => $page
            ->where('site.headline', 'Casas e terrenos no sertão.')
            ->where('site.texts.owner_title', 'Quer anunciar?')
            ->where('site.texts.benefit_1_title', 'Repasse todo mês')
        );

    $this->actingAs($admin)
        ->put(route('showcase-settings.texts.update'), [
            'showcase_headline' => str_repeat('a', 121),
            'showcase_texts' => [...$texts, 'banner_1_action' => str_repeat('a', 31)],
        ])
        ->assertSessionHasErrors(['showcase_headline', 'showcase_texts.banner_1_action']);
});
