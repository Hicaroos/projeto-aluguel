<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Concerns\BelongsToVisibleBranches;
use App\Enums\DealType;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A negotiation between a lead and a property, moving through the account's funnel.
 *
 * @property int $id
 * @property int $account_id
 * @property int $lead_id
 * @property int|null $property_id
 * @property int $deal_stage_id
 * @property int|null $user_id The team member in charge of the deal.
 * @property DealType $type
 * @property string|null $value The expected value of the deal.
 * @property int $position The order of the deal within its stage.
 * @property string|null $lost_reason
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Lead $lead
 * @property-read Property|null $property
 * @property-read DealStage $stage
 * @property-read User|null $user
 * @property-read Collection<int, DealVisit> $visits
 * @property-read Collection<int, DealProposal> $proposals
 * @property-read Sale|null $sale
 */
#[Fillable([
    'account_id',
    'lead_id',
    'property_id',
    'deal_stage_id',
    'user_id',
    'type',
    'value',
    'position',
    'lost_reason',
    'closed_at',
])]
class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use BelongsToAccount, BelongsToVisibleBranches, HasFactory;

    /**
     * @var array<string, string|int>
     */
    protected $attributes = [
        'type' => 'sale',
        'position' => 0,
    ];

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    /**
     * @return BelongsTo<DealStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(DealStage::class, 'deal_stage_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the sale that closed the deal, if any.
     *
     * @return HasOne<Sale, $this>
     */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    /**
     * Get the visits, the latest first.
     *
     * @return HasMany<DealVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(DealVisit::class)->latest('scheduled_at');
    }

    /**
     * Get the proposals, the latest first.
     *
     * @return HasMany<DealProposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(DealProposal::class)->latest('proposed_on')->latest('id');
    }

    /**
     * Load everything the funnel cards and the deal details show.
     *
     * @param  Builder<Deal>  $query
     */
    public function scopeWithDetails(Builder $query): void
    {
        $query->with([
            'lead:id,name,phone,email,interest,payment_plan,max_price,branch_id',
            'property:id,type,street,number,neighborhood,city,state,sale_price,rent_amount,status,deleted_at',
            'user:id,name',
            'visits.user:id,name',
            'proposals',
            'sale:id,deal_id',
        ]);
    }

    /**
     * Find the tenant the lead already is, by their phone or e-mail, to lease the property to.
     */
    public function leadTenant(): ?Tenant
    {
        $phone = $this->lead->phone;
        $email = $this->lead->email;

        if ($phone === null && $email === null) {
            return null;
        }

        return Tenant::query()
            ->where('account_id', $this->account_id)
            ->where(fn (Builder $query) => $query
                ->when($phone !== null, fn (Builder $query) => $query->orWhere('phone', $phone))
                ->when($email !== null, fn (Builder $query) => $query->orWhere('email', $email)))
            ->first();
    }

    /**
     * Find the rent deal a lease is being registered from, among the user's deals.
     */
    public static function findForLease(mixed $id): ?self
    {
        return is_numeric($id)
            ? static::query()->where('type', DealType::Rent)->with(['lead', 'property'])->find((int) $id)
            : null;
    }

    /**
     * Determine whether the deal is still being negotiated.
     */
    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Limit the query to the deals of the leads of the given branches.
     *
     * @param  Builder<static>  $query
     * @param  array<int, int>  $branchIds
     */
    public static function restrictToBranches(Builder $query, array $branchIds): void
    {
        $query->whereIn($query->qualifyColumn('lead_id'), Lead::idsInBranches($branchIds));
    }

    /**
     * Get a subquery selecting the ids of the deals of the given branches.
     *
     * @param  array<int, int>  $branchIds
     * @return Builder<Deal>
     */
    public static function idsInBranches(array $branchIds): Builder
    {
        return static::withoutGlobalScopes()->whereIn('lead_id', Lead::idsInBranches($branchIds))->select('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DealType::class,
            'value' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }
}
