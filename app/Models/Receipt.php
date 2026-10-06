<?php

namespace App\Models;

use App\Concerns\SpellsMoney;
use App\Enums\PaymentMethod;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $account_id
 * @property int $payment_id
 * @property string $amount
 * @property Carbon $date
 * @property PaymentMethod|null $payment_method
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Payment $payment
 */
#[Fillable(['account_id', 'payment_id', 'amount', 'date', 'payment_method', 'notes'])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory, SpellsMoney;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the received amount written in Brazilian Portuguese words, e.g. "mil e quinhentos reais e vinte centavos".
     */
    public function amountInWords(): string
    {
        return $this->spellMoney($this->amount);
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
            'date' => 'date:Y-m-d',
            'payment_method' => PaymentMethod::class,
        ];
    }
}
