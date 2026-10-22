<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Concerns\BelongsToVisibleBranches;
use App\Concerns\FollowsSelectedOwner;
use App\Enums\LeaseStatus;
use App\Enums\PropertyAmenity;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $account_id
 * @property int|null $branch_id
 * @property int $owner_id
 * @property PropertyType $type
 * @property bool $for_rent
 * @property bool $for_sale
 * @property string $zip_code
 * @property string $street
 * @property string $number
 * @property string|null $complement
 * @property string $neighborhood
 * @property string $city
 * @property string $state
 * @property string|null $rent_amount
 * @property string|null $sale_price
 * @property int|null $bedrooms
 * @property int|null $suites
 * @property int|null $bathrooms
 * @property int|null $parking_spaces
 * @property string|null $built_area
 * @property string|null $land_area
 * @property string|null $land_front The front of the land, in meters.
 * @property string|null $land_depth The depth of the land, in meters.
 * @property string|null $description
 * @property \Illuminate\Support\Collection<int, PropertyAmenity>|null $amenities
 * @property Carbon|null $published_at When the property went on the account's showcase; null while it is off it.
 * @property bool $is_featured Whether the showcase highlights the property on its home page.
 * @property string|null $registration_number The registration at the land registry office (matrícula).
 * @property string|null $water_account The number of the water bill (matrícula or client code), to look it up.
 * @property string|null $energy_account The number of the energy bill (installation or client code), to look it up.
 * @property PropertyStatus $status
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Branch|null $branch
 * @property-read Owner $owner The main owner, who signs the contracts.
 * @property-read Collection<int, Owner> $owners Every owner, the main one included, with their share.
 * @property-read Collection<int, PropertyPhoto> $photos
 * @property-read Collection<int, Lease> $leases
 * @property-read Lease|null $activeLease
 * @property-read Collection<int, Expense> $expenses
 */
#[Fillable([
    'account_id',
    'branch_id',
    'owner_id',
    'type',
    'for_rent',
    'for_sale',
    'zip_code',
    'street',
    'number',
    'complement',
    'neighborhood',
    'city',
    'state',
    'rent_amount',
    'sale_price',
    'bedrooms',
    'suites',
    'bathrooms',
    'parking_spaces',
    'built_area',
    'land_area',
    'land_front',
    'land_depth',
    'description',
    'amenities',
    'published_at',
    'is_featured',
    'registration_number',
    'water_account',
    'energy_account',
    'status',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use BelongsToAccount, BelongsToVisibleBranches, FollowsSelectedOwner, HasFactory, SoftDeletes;

    /**
     * @var array<string, string|bool>
     */
    protected $attributes = [
        'status' => 'available',
        'for_rent' => true,
        'for_sale' => false,
    ];

    /**
     * Link the owner of the property to its branch, and the tenants of a property moved to another
     * branch to that branch too, so the branch lists them.
     */
    protected static function booted(): void
    {
        static::created(function (Property $property): void {
            $property->owners()->syncWithoutDetaching([$property->owner_id => ['share_percent' => 100]]);
        });

        static::updated(function (Property $property): void {
            if ($property->wasChanged('owner_id')) {
                $property->passShareToMainOwner((int) $property->getOriginal('owner_id'));
            }
        });

        static::saved(function (Property $property): void {
            if ($property->wasRecentlyCreated || $property->wasChanged(['owner_id', 'branch_id'])) {
                Owner::linkToBranch($property->owners()->pluck('owners.id')->all(), $property->branch_id);
            }
        });

        static::updated(function (Property $property): void {
            if ($property->wasChanged('branch_id')) {
                Tenant::linkToBranch(
                    Lease::withoutGlobalScopes()
                        ->where('property_id', $property->id)
                        ->get(['tenant_id'])
                        ->map(fn (Lease $lease): int => $lease->tenant_id)
                        ->unique()
                        ->all(),
                    $property->branch_id,
                );
            }
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Owner, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /**
     * Get everyone who owns the property, the main owner included, with the share each one holds.
     *
     * @return BelongsToMany<Owner, $this, OwnerProperty, 'pivot'>
     */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Owner::class)->using(OwnerProperty::class)->withPivot('share_percent')->withTimestamps();
    }

    /**
     * Get the share of the property each owner holds, as percentages keyed by owner id. A property
     * whose shares were never recorded belongs wholly to its main owner.
     *
     * @return array<int, float>
     */
    public function ownerShares(): array
    {
        $shares = DB::table('owner_property')
            ->where('property_id', $this->id)
            ->pluck('share_percent', 'owner_id')
            ->map(fn (mixed $share): float => (float) $share)
            ->all();

        return $shares === [] ? [$this->owner_id => 100.0] : $shares;
    }

    /**
     * Hand the share of the former main owner to the new one, so the main owner always holds a share.
     */
    private function passShareToMainOwner(int $formerOwnerId): void
    {
        $shares = $this->ownerShares();
        $formerShare = $shares[$formerOwnerId] ?? 0.0;

        unset($shares[$formerOwnerId]);

        $shares[$this->owner_id] = ($shares[$this->owner_id] ?? 0.0) + ($formerShare > 0 ? $formerShare : max(0.0, 100 - array_sum($shares)));

        $this->owners()->sync(array_map(fn (float $share): array => ['share_percent' => $share], $shares));
    }

    /**
     * Get the property photos, starting with the cover.
     *
     * @return HasMany<PropertyPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(PropertyPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<Lease, $this>
     */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    /**
     * Get the lease currently renting the property, if any.
     *
     * @return HasOne<Lease, $this>
     */
    public function activeLease(): HasOne
    {
        return $this->hasOne(Lease::class)->where('status', LeaseStatus::Active);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Load everything the property details show: the current lease and the photos.
     *
     * @param  Builder<Property>  $query
     */
    public function scopeWithDetails(Builder $query): void
    {
        $query->with([
            'activeLease:id,property_id,tenant_id,start_date,end_date,amount,due_day,status',
            'activeLease.tenant:id,name,deleted_at',
            'photos:id,property_id,sort_order',
            'branch:id,name',
            'owner:id,name,deleted_at',
            'owners:id,name',
        ]);
    }

    /**
     * Scope the query to the properties of the rental portfolio: offered for rent and not sold.
     *
     * @param  Builder<Property>  $query
     */
    public function scopeRentable(Builder $query): void
    {
        $query->where($query->qualifyColumn('for_rent'), true)
            ->where($query->qualifyColumn('status'), '!=', PropertyStatus::Sold);
    }

    /**
     * Scope the query to properties offered for the given purpose: rent or sale.
     *
     * @param  Builder<Property>  $query
     */
    public function scopeOfferedFor(Builder $query, ?string $purpose): void
    {
        $query->when($purpose === 'rent', fn (Builder $query) => $query->where('for_rent', true))
            ->when($purpose === 'sale', fn (Builder $query) => $query->where('for_sale', true));
    }

    /**
     * Scope the query to the properties visitors see on the showcase: published, and still for sale or
     * free to rent. With a purpose, only the properties offered for it.
     *
     * @param  Builder<Property>  $query
     * @param  'sale'|'rent'|null  $purpose
     */
    public function scopeOnShowcase(Builder $query, ?string $purpose = null): void
    {
        $query->whereNotNull($query->qualifyColumn('published_at'))
            ->where(fn (Builder $query) => $query
                ->when($purpose !== 'rent', fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query
                    ->where('for_sale', true)
                    ->whereNotNull('sale_price')
                    ->whereNotIn('status', [PropertyStatus::Sold, PropertyStatus::Inactive])))
                ->when($purpose !== 'sale', fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query
                    ->where('for_rent', true)
                    ->whereNotNull('rent_amount')
                    ->where('status', PropertyStatus::Available))));
    }

    /**
     * Get the sale price the showcase shows, while the property is still for sale.
     */
    public function showcaseSalePrice(): ?string
    {
        return $this->for_sale && ! in_array($this->status, [PropertyStatus::Sold, PropertyStatus::Inactive], true)
            ? $this->sale_price
            : null;
    }

    /**
     * Get the rent the showcase shows, while the property is free to rent.
     */
    public function showcaseRent(): ?string
    {
        return $this->for_rent && $this->status === PropertyStatus::Available ? $this->rent_amount : null;
    }

    /**
     * Scope the query to properties whose address matches the given term.
     *
     * @param  Builder<Property>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->when($term !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
            $query->where('street', 'like', "%{$term}%")
                ->orWhere('neighborhood', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%")
                ->orWhere('zip_code', 'like', "%{$term}%");
        }));
    }

    /**
     * Determine whether the property is under an active lease.
     */
    public function hasActiveLease(): bool
    {
        return $this->leases()->active()->exists();
    }

    /**
     * Limit the query to the properties of the given branches.
     *
     * @param  Builder<static>  $query
     * @param  array<int, int>  $branchIds
     */
    public static function restrictToBranches(Builder $query, array $branchIds): void
    {
        $query->whereIn($query->qualifyColumn('branch_id'), $branchIds);
    }

    /**
     * Get a subquery selecting the ids of the properties of the given branches, deleted ones included.
     *
     * @param  array<int, int>  $branchIds
     * @return Builder<Property>
     */
    public static function idsInBranches(array $branchIds): Builder
    {
        return static::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->select('id');
    }

    /**
     * Limit the query to the properties the given owner owns, wholly or in part.
     *
     * @param  Builder<static>  $query
     */
    public static function restrictToOwner(Builder $query, int $ownerId): void
    {
        $query->whereIn($query->qualifyColumn('id'), static::idsOfOwner($ownerId));
    }

    /**
     * Get a subquery selecting the ids of the properties the given owner owns, wholly or in part,
     * deleted ones included.
     *
     * @return Builder<Property>
     */
    public static function idsOfOwner(int $ownerId): Builder
    {
        return static::withoutGlobalScopes()
            ->where(fn (Builder $query) => $query
                ->where('owner_id', $ownerId)
                ->orWhereIn('id', DB::table('owner_property')->where('owner_id', $ownerId)->select('property_id')))
            ->select('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'status' => PropertyStatus::class,
            'for_rent' => 'boolean',
            'for_sale' => 'boolean',
            'rent_amount' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'built_area' => 'decimal:2',
            'land_area' => 'decimal:2',
            'land_front' => 'decimal:2',
            'land_depth' => 'decimal:2',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'amenities' => AsEnumCollection::of(PropertyAmenity::class),
        ];
    }
}
