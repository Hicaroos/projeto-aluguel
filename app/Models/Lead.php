<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Concerns\BelongsToVisibleBranches;
use App\Enums\LeadInterest;
use App\Enums\LeadSource;
use App\Enums\PaymentPlan;
use App\Enums\PropertyType;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Someone looking to buy or rent a property: a contact the account follows through its funnel.
 *
 * @property int $id
 * @property int $account_id
 * @property int|null $branch_id
 * @property int|null $user_id The team member in charge of the lead.
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property LeadSource|null $source
 * @property LeadInterest $interest
 * @property PropertyType|null $property_type
 * @property string|null $max_price
 * @property string|null $neighborhoods
 * @property PaymentPlan|null $payment_plan
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Branch|null $branch
 * @property-read User|null $user
 * @property-read Collection<int, Deal> $deals
 */
#[Fillable([
    'account_id',
    'branch_id',
    'user_id',
    'name',
    'phone',
    'email',
    'source',
    'interest',
    'property_type',
    'max_price',
    'neighborhoods',
    'payment_plan',
    'notes',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use BelongsToAccount, BelongsToVisibleBranches, HasFactory;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'interest' => 'buy',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /**
     * Scope the query to leads whose name, phone or e-mail matches the given term.
     *
     * @param  Builder<Lead>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->when($term !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        }));
    }

    /**
     * Limit the query to the leads of the given branches, plus the ones of no branch in particular.
     *
     * @param  Builder<static>  $query
     * @param  array<int, int>  $branchIds
     */
    public static function restrictToBranches(Builder $query, array $branchIds): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn($query->qualifyColumn('branch_id'), $branchIds)
            ->orWhereNull($query->qualifyColumn('branch_id')));
    }

    /**
     * Get a subquery selecting the ids of the leads of the given branches.
     *
     * @param  array<int, int>  $branchIds
     * @return Builder<Lead>
     */
    public static function idsInBranches(array $branchIds): Builder
    {
        return static::withoutGlobalScopes()
            ->where(fn (Builder $query) => $query->whereIn('branch_id', $branchIds)->orWhereNull('branch_id'))
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
            'source' => LeadSource::class,
            'interest' => LeadInterest::class,
            'property_type' => PropertyType::class,
            'payment_plan' => PaymentPlan::class,
            'max_price' => 'decimal:2',
        ];
    }
}
