<?php

namespace App\Models;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $account_id
 * @property int $owner_id
 * @property PropertyType $type
 * @property string $zip_code
 * @property string $street
 * @property string $number
 * @property string|null $complement
 * @property string $neighborhood
 * @property string $city
 * @property string $state
 * @property string $rent_amount
 * @property PropertyStatus $status
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Owner $owner
 * @property-read Collection<int, PropertyPhoto> $photos
 * @property-read Collection<int, Lease> $leases
 * @property-read Lease|null $activeLease
 * @property-read Collection<int, Expense> $expenses
 */
#[Fillable([
    'account_id',
    'owner_id',
    'type',
    'zip_code',
    'street',
    'number',
    'complement',
    'neighborhood',
    'city',
    'state',
    'rent_amount',
    'status',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'available',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Owner, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
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
        ]);
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'status' => PropertyStatus::class,
            'rent_amount' => 'decimal:2',
        ];
    }
}
