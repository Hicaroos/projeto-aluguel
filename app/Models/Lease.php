<?php

namespace App\Models;

use App\Enums\LeaseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
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
 * @property LeaseStatus $status
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
    'status',
])]
class Lease extends Model
{
    use SoftDeletes;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'active',
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
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'amount' => 'decimal:2',
            'status' => LeaseStatus::class,
        ];
    }
}
