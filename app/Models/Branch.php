<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * An office of an agency, e.g. one per city. Properties belong to a branch, and their leases,
 * payments and expenses follow it.
 *
 * @property int $id
 * @property int $account_id
 * @property string $name
 * @property string|null $document The branch CNPJ, as digits, when it differs from the agency's.
 * @property string|null $creci The branch CRECI, when it differs from the agency's.
 * @property string|null $phone
 * @property string|null $zip_code
 * @property string|null $street
 * @property string|null $number
 * @property string|null $complement
 * @property string|null $neighborhood
 * @property string|null $city
 * @property string|null $state
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Collection<int, Property> $properties
 * @property-read Collection<int, Lease> $leases
 * @property-read Collection<int, Tenant> $tenants
 */
#[Fillable([
    'account_id',
    'name',
    'document',
    'creci',
    'phone',
    'zip_code',
    'street',
    'number',
    'complement',
    'neighborhood',
    'city',
    'state',
    'is_active',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * The name of the branch every agency starts with.
     */
    public const string MAIN_BRANCH_NAME = 'Matriz';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * @return HasManyThrough<Lease, Property, $this>
     */
    public function leases(): HasManyThrough
    {
        return $this->hasManyThrough(Lease::class, Property::class);
    }

    /**
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class);
    }

    /**
     * Scope the query to the branches that are still in use.
     *
     * @param  Builder<Branch>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope the query to the branches the given user may work with.
     *
     * @param  Builder<Branch>  $query
     */
    public function scopeAccessibleBy(Builder $query, User $user): void
    {
        $branchIds = $user->accessibleBranchIds();

        $query->when($branchIds !== null, fn (Builder $query) => $query->whereKey($branchIds));
    }

    /**
     * Get the branches whose records the signed in user is looking at: the one picked in the
     * branch selector, or all the branches they can access. Null means no restriction, as for
     * single owner accounts and code running without a signed in user.
     *
     * Remembered for the rest of the request, since every branch scoped query asks for it.
     *
     * @return list<int>|null
     */
    public static function visibleIds(): ?array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $attributes = request()->attributes;
        $key = 'visible_branch_ids.'.$user->id;

        if (! $attributes->has($key)) {
            $attributes->set($key, $user->visibleBranchIds());
        }

        return $attributes->get($key);
    }

    /**
     * Forget the visible branches remembered for this request, after the selection changes.
     */
    public static function forgetVisibleIds(): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            request()->attributes->remove('visible_branch_ids.'.$user->id);
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
