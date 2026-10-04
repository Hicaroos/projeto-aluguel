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
use RuntimeException;

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
            $words = $this->spell($reais);
            $connector = preg_match('/(milhão|milhões|bilhão|bilhões)$/u', $words) === 1 ? ' de' : '';

            $parts[] = $words.$connector.($reais === 1 ? ' real' : ' reais');
        }

        if ($centavos > 0) {
            $parts[] = $this->spell($centavos).($centavos === 1 ? ' centavo' : ' centavos');
        }

        return $parts === [] ? 'zero reais' : implode(' e ', $parts);
    }

    /**
     * Spell the given number in Brazilian Portuguese.
     *
     * @throws RuntimeException when the number cannot be spelled (e.g. the intl extension is missing).
     */
    private function spell(int $number): string
    {
        $words = Number::spell($number, locale: 'pt_BR');

        if ($words === false) {
            throw new RuntimeException('Não foi possível escrever o valor por extenso. Verifique se a extensão intl do PHP está ativa.');
        }

        return $words;
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
