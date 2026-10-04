<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

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
    use HasFactory;

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
        $totalCents = (int) round((float) $this->amount * 100);
        $reais = intdiv($totalCents, 100);
        $centavos = $totalCents % 100;
        $parts = [];

        if ($reais > 0) {
            $words = Number::spell($reais, locale: 'pt_BR');
            $connector = preg_match('/(milhão|milhões|bilhão|bilhões)$/u', $words) === 1 ? ' de' : '';

            $parts[] = $words.$connector.($reais === 1 ? ' real' : ' reais');
        }

        if ($centavos > 0) {
            $parts[] = Number::spell($centavos, locale: 'pt_BR').($centavos === 1 ? ' centavo' : ' centavos');
        }

        return $parts === [] ? 'zero reais' : implode(' e ', $parts);
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
