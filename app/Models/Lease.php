<?php

namespace App\Models;

use App\Enums\GuaranteeType;
use App\Enums\LeaseStatus;
use Database\Factories\LeaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $account_id
 * @property int $property_id
 * @property int $tenant_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $amount
 * @property int $due_day
 * @property GuaranteeType $guarantee_type
 * @property string|null $deposit_amount
 * @property LeaseStatus $status
 * @property string|null $notes
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Property $property
 * @property-read Tenant $tenant
 * @property-read Collection<int, Payment> $payments
 */
#[Fillable([
    'account_id',
    'property_id',
    'tenant_id',
    'start_date',
    'end_date',
    'amount',
    'due_day',
    'guarantee_type',
    'deposit_amount',
    'status',
    'notes',
])]
class Lease extends Model
{
    /** @use HasFactory<LeaseFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'active',
        'guarantee_type' => 'none',
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
            'status' => LeaseStatus::class,
        ];
    }
}
