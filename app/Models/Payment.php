<?php

namespace App\Models;

use App\Enums\PaymentStatus;
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
 * @property Carbon $reference_month
 * @property Carbon $due_date
 * @property string $amount
 * @property PaymentStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Lease $lease
 * @property-read Collection<int, Receipt> $receipts
 */
#[Fillable([
    'account_id',
    'lease_id',
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
        $query->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Partial]);
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
            'status' => PaymentStatus::class,
        ];
    }
}
