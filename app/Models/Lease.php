<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Concerns\BelongsToVisibleBranches;
use App\Enums\AdjustmentIndex;
use App\Enums\GuaranteeType;
use App\Enums\LeasePurpose;
use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\LeaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property int $property_id
 * @property int $tenant_id
 * @property LeasePurpose $purpose
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $amount
 * @property int $due_day
 * @property AdjustmentIndex $adjustment_index
 * @property string $late_fee_percent
 * @property string $monthly_interest_percent
 * @property int $termination_fee_months
 * @property GuaranteeType $guarantee_type
 * @property string|null $deposit_amount
 * @property Carbon|null $deposit_settled_on When the deposit was settled, after the lease ended.
 * @property string|null $deposit_refunded_amount What was left of the deposit and given back to the tenant.
 * @property string|null $surety_insurer
 * @property string|null $surety_policy_number
 * @property LeaseStatus $status
 * @property string|null $notes
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Property $property
 * @property-read Tenant $tenant
 * @property-read Guarantor|null $guarantor
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, Payment> $openPayments
 * @property-read Collection<int, LeaseAdjustment> $adjustments
 * @property-read Collection<int, LeaseDocument> $documents
 * @property-read Collection<int, LeaseRenewal> $renewals
 * @property-read int|null $adjustments_count
 * @property-read string|null $next_adjustment_date The next anniversary the rent can be adjusted on, as Y-m-d.
 * @property-read string|null $adjustment_status 'available' within 30 days of the anniversary, 'overdue' after it.
 */
#[Fillable([
    'account_id',
    'property_id',
    'tenant_id',
    'purpose',
    'start_date',
    'end_date',
    'amount',
    'due_day',
    'adjustment_index',
    'late_fee_percent',
    'monthly_interest_percent',
    'termination_fee_months',
    'guarantee_type',
    'deposit_amount',
    'deposit_settled_on',
    'deposit_refunded_amount',
    'surety_insurer',
    'surety_policy_number',
    'status',
    'notes',
])]
class Lease extends Model
{
    /** @use HasFactory<LeaseFactory> */
    use BelongsToAccount, BelongsToVisibleBranches, HasFactory, SoftDeletes;

    /**
     * @var array<string, string|int>
     */
    protected $attributes = [
        'status' => 'active',
        'guarantee_type' => 'none',
        'purpose' => 'residential',
        'adjustment_index' => 'igpm',
        'late_fee_percent' => '10.00',
        'monthly_interest_percent' => '1.00',
        'termination_fee_months' => 3,
    ];

    /**
     * Link the tenant to the branch of the rented property, so that branch lists them.
     */
    protected static function booted(): void
    {
        static::saved(function (Lease $lease): void {
            if ($lease->wasRecentlyCreated || $lease->wasChanged(['tenant_id', 'property_id'])) {
                Tenant::linkToBranch([$lease->tenant_id], Property::withoutGlobalScopes()->whereKey($lease->property_id)->value('branch_id'));
            }
        });
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    /**
     * @return HasOne<Guarantor, $this>
     */
    public function guarantor(): HasOne
    {
        return $this->hasOne(Guarantor::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The payments the tenant still owes on this lease, oldest due first.
     *
     * @return HasMany<Payment, $this>
     */
    public function openPayments(): HasMany
    {
        return $this->payments()
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Partial])
            ->orderBy('due_date')
            ->orderBy('id');
    }

    /**
     * Determine whether the deposit was already settled.
     */
    public function isDepositSettled(): bool
    {
        return $this->deposit_settled_on !== null;
    }

    /**
     * Determine whether the deposit can be settled: a lease guaranteed by a deposit that has
     * already finished and whose deposit was not settled yet.
     */
    public function canSettleDeposit(): bool
    {
        return $this->guarantee_type === GuaranteeType::Deposit
            && (float) $this->deposit_amount > 0
            && ! $this->isActive()
            && ! $this->isDepositSettled();
    }

    /**
     * @return HasMany<LeaseAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(LeaseAdjustment::class)->orderBy('effective_on');
    }

    /**
     * Get the extensions of the lease term, oldest first.
     *
     * @return HasMany<LeaseRenewal, $this>
     */
    public function renewals(): HasMany
    {
        return $this->hasMany(LeaseRenewal::class)->orderBy('new_end_date');
    }

    /**
     * Get the files attached to the lease, newest first.
     *
     * @return HasMany<LeaseDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(LeaseDocument::class)->latest('id');
    }

    /**
     * Get the next anniversary on which the rent can be adjusted: one year after the start for every
     * adjustment already applied. Null when the lease is not active or ends before it.
     */
    public function nextAdjustmentAnniversary(): ?CarbonImmutable
    {
        if (! $this->isActive()) {
            return null;
        }

        $applied = $this->relationLoaded('adjustments')
            ? $this->adjustments->count()
            : ($this->adjustments_count ?? $this->adjustments()->count());
        $anniversary = CarbonImmutable::parse($this->start_date)->addYearsNoOverflow($applied + 1);

        return $anniversary->gt($this->end_date) ? null : $anniversary;
    }

    /**
     * Determine whether the next adjustment can be applied: from 30 days before the anniversary on.
     */
    public function canBeAdjusted(): bool
    {
        return $this->adjustment_status !== null;
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function nextAdjustmentDate(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->nextAdjustmentAnniversary()?->toDateString(),
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function adjustmentStatus(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $anniversary = $this->nextAdjustmentAnniversary();

                return match (true) {
                    $anniversary === null => null,
                    $anniversary->lt(today()) => 'overdue',
                    $anniversary->lte(today()->addDays(30)) => 'available',
                    default => null,
                };
            },
        );
    }

    /**
     * Scope the query to active leases only.
     *
     * @param  Builder<Lease>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), LeaseStatus::Active);
    }

    /**
     * Determine whether the lease is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === LeaseStatus::Active;
    }

    /**
     * Load everything the lease details show: parties, guarantee, history, documents and open payments.
     *
     * @param  Builder<Lease>  $query
     */
    public function scopeWithDetails(Builder $query): void
    {
        $query->with([
            'property:id,type,street,number,complement,neighborhood,city,state,rent_amount,status,deleted_at',
            'tenant:id,name,email,phone,deleted_at',
            'guarantor',
            'adjustments',
            'documents',
            'renewals',
            'openPayments' => fn ($query) => $query
                ->select(['id', 'lease_id', 'type', 'description', 'reference_month', 'due_date', 'amount', 'status'])
                ->withSum('receipts as received_amount', 'amount'),
        ]);
    }

    /**
     * Add the annual adjustment details the lease details show.
     */
    public function withAdjustmentInfo(): static
    {
        return $this->append(['next_adjustment_date', 'adjustment_status']);
    }

    /**
     * Scope the query to leases whose tenant name or property address matches the given term.
     *
     * @param  Builder<Lease>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->when($term !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
            $query->whereHas('tenant', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                ->orWhereHas('property', fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
                    $query->where('street', 'like', "%{$term}%")
                        ->orWhere('neighborhood', 'like', "%{$term}%")
                        ->orWhere('city', 'like', "%{$term}%");
                }));
        }));
    }

    /**
     * Scope the query to active leases ending within the given number of days, or already past their end date.
     *
     * @param  Builder<Lease>  $query
     */
    public function scopeEndingWithin(Builder $query, int $days): void
    {
        $query->active()->whereDate('end_date', '<=', today()->addDays($days));
    }

    /**
     * Limit the query to the leases of the properties of the given branches.
     *
     * @param  Builder<static>  $query
     * @param  array<int, int>  $branchIds
     */
    public static function restrictToBranches(Builder $query, array $branchIds): void
    {
        $query->whereIn($query->qualifyColumn('property_id'), Property::idsInBranches($branchIds));
    }

    /**
     * Get a subquery selecting the ids of the leases of the given branches, deleted ones included.
     *
     * @param  array<int, int>  $branchIds
     * @return Builder<Lease>
     */
    public static function idsInBranches(array $branchIds): Builder
    {
        return static::withoutGlobalScopes()->whereIn('property_id', Property::idsInBranches($branchIds))->select('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'deposit_settled_on' => 'date:Y-m-d',
            'deposit_refunded_amount' => 'decimal:2',
            'guarantee_type' => GuaranteeType::class,
            'purpose' => LeasePurpose::class,
            'adjustment_index' => AdjustmentIndex::class,
            'late_fee_percent' => 'decimal:2',
            'monthly_interest_percent' => 'decimal:2',
            'termination_fee_months' => 'integer',
            'status' => LeaseStatus::class,
        ];
    }
}
