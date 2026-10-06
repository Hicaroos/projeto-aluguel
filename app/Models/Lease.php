<?php

namespace App\Models;

use App\Enums\AdjustmentIndex;
use App\Enums\GuaranteeType;
use App\Enums\LeasePurpose;
use App\Enums\LeaseStatus;
use Database\Factories\LeaseFactory;
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
    'surety_insurer',
    'surety_policy_number',
    'status',
    'notes',
])]
class Lease extends Model
{
    /** @use HasFactory<LeaseFactory> */
    use HasFactory, SoftDeletes;

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
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
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
     * Scope the query to active leases only.
     *
     * @param  Builder<Lease>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', LeaseStatus::Active);
    }

    /**
     * Determine whether the lease is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === LeaseStatus::Active;
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
