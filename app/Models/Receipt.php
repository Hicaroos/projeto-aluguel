<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Concerns\BelongsToVisibleBranches;
use App\Concerns\SpellsMoney;
use App\Enums\PaymentMethod;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $account_id
 * @property int $payment_id
 * @property string $amount The part of the rent paid, which is what reduces the payment balance.
 * @property string $late_fee_amount Late fee charged on top of the amount.
 * @property string $interest_amount Late interest charged on top of the amount.
 * @property Carbon $date
 * @property PaymentMethod|null $payment_method
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Payment $payment
 */
#[Fillable(['account_id', 'payment_id', 'amount', 'late_fee_amount', 'interest_amount', 'date', 'payment_method', 'notes'])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use BelongsToAccount, BelongsToVisibleBranches, HasFactory, SpellsMoney;

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the total received: the rent part plus the late fee and interest.
     */
    public function totalAmount(): float
    {
        return round((float) $this->amount + (float) $this->late_fee_amount + (float) $this->interest_amount, 2);
    }

    /**
     * Get the total received written in Brazilian Portuguese words, e.g. "mil e quinhentos reais e vinte centavos".
     */
    public function amountInWords(): string
    {
        return $this->spellMoney($this->totalAmount());
    }

    /**
     * Limit the query to the receipts of the leases of the given branches.
     *
     * @param  Builder<static>  $query
     * @param  list<int>  $branchIds
     */
    public static function restrictToBranches(Builder $query, array $branchIds): void
    {
        $query->whereIn(
            $query->qualifyColumn('payment_id'),
            Payment::withoutGlobalScopes()->whereIn('lease_id', Lease::idsInBranches($branchIds))->select('id'),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'late_fee_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'date' => 'date:Y-m-d',
            'payment_method' => PaymentMethod::class,
        ];
    }
}
