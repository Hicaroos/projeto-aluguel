<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Carbon\CarbonInterface;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $account_id
 * @property int $lease_id
 * @property PaymentType $type
 * @property string|null $description
 * @property Carbon|null $reference_month Null for extra charges.
 * @property Carbon $due_date
 * @property string $amount
 * @property PaymentStatus $status
 * @property-read string|null $received_amount Sum of the receipts, when loaded with withSum().
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Lease $lease
 * @property-read Collection<int, Receipt> $receipts
 */
#[Fillable([
    'account_id',
    'lease_id',
    'type',
    'description',
    'reference_month',
    'due_date',
    'amount',
    'status',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'type' => 'rent',
        'status' => 'pending',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Lease, $this>
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Scope the query to payments that are still open (pending or partially paid).
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('status'), [PaymentStatus::Pending, PaymentStatus::Partial]);
    }

    /**
     * Scope the query to open payments whose due date has already passed.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereDate('due_date', '<', today());
    }

    /**
     * Scope the query to payments due within the given month.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeDueInMonth(Builder $query, CarbonInterface $month): void
    {
        $query->whereBetween('due_date', [
            $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
        ]);
    }

    /**
     * Scope the query by a display status: any stored status, or "overdue".
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeFilterByStatus(Builder $query, string $status): void
    {
        $query
            ->when($status === 'overdue', fn (Builder $query) => $query->overdue())
            ->when(PaymentStatus::tryFrom($status) !== null, fn (Builder $query) => $query->where('status', $status));
    }

    /**
     * Scope the query to payments whose tenant name or property address matches the given term.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->when($term !== '', fn (Builder $query) => $query->whereHas('lease', fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
            $query->whereHas('tenant', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                ->orWhereHas('property', fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
                    $query->where('street', 'like', "%{$term}%")
                        ->orWhere('neighborhood', 'like', "%{$term}%");
                }));
        })));
    }

    /**
     * Eager load what payment lists display: tenant, property, receipts and the received total.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeWithListDetails(Builder $query): void
    {
        $query
            ->with([
                'lease:id,property_id,tenant_id,due_day,status,late_fee_percent,monthly_interest_percent',
                'lease.tenant:id,name,deleted_at',
                'lease.property:id,type,street,number,complement,neighborhood,city,state,deleted_at',
                'receipts' => fn ($query) => $query->orderBy('date')->orderBy('id'),
            ])
            ->withSum('receipts as received_amount', 'amount');
    }

    /**
     * Scope the query to monthly rent payments, generated from the lease terms.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeRent(Builder $query): void
    {
        $query->where('type', PaymentType::Rent);
    }

    /**
     * Scope the query to extra charges, such as a repair or fine owed by the tenant.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeExtra(Builder $query): void
    {
        $query->where('type', PaymentType::Extra);
    }

    /**
     * Determine whether the payment is an extra charge rather than monthly rent.
     */
    public function isExtra(): bool
    {
        return $this->type === PaymentType::Extra;
    }

    /**
     * Determine whether the payment can still receive amounts.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [PaymentStatus::Pending, PaymentStatus::Partial], true);
    }

    /**
     * Get the amount still left to be received for this payment.
     */
    public function remainingAmount(): float
    {
        return max(0, round((float) $this->amount - (float) $this->receipts()->sum('amount'), 2));
    }

    /**
     * Recalculate the payment status from the receipts registered for it.
     */
    public function refreshStatus(): void
    {
        if ($this->status === PaymentStatus::Canceled) {
            return;
        }

        $received = (float) $this->receipts()->sum('amount');

        $this->update([
            'status' => match (true) {
                $received >= (float) $this->amount => PaymentStatus::Paid,
                $received > 0 => PaymentStatus::Partial,
                default => PaymentStatus::Pending,
            },
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_month' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'type' => PaymentType::class,
            'status' => PaymentStatus::class,
        ];
    }
}
