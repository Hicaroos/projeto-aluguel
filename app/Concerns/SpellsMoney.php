<?php

namespace App\Concerns;

use Illuminate\Support\Number;
use RuntimeException;

trait SpellsMoney
{
    /**
     * Write an amount in Brazilian Portuguese words, e.g. "mil e quinhentos reais e vinte centavos".
     */
    protected function spellMoney(string|float|int $amount): string
    {
        $totalCents = (int) round((float) $amount * 100);
        $reais = intdiv($totalCents, 100);
        $centavos = $totalCents % 100;
        $parts = [];

        if ($reais > 0) {
            $words = $this->spellNumber($reais);
            $connector = preg_match('/(milhão|milhões|bilhão|bilhões)$/u', $words) === 1 ? ' de' : '';

            $parts[] = $words.$connector.($reais === 1 ? ' real' : ' reais');
        }

        if ($centavos > 0) {
            $parts[] = $this->spellNumber($centavos).($centavos === 1 ? ' centavo' : ' centavos');
        }

        return $parts === [] ? 'zero reais' : implode(' e ', $parts);
    }

    /**
     * Spell the given number in Brazilian Portuguese.
     *
     * @throws RuntimeException when the number cannot be spelled (e.g. the intl extension is missing).
     */
    protected function spellNumber(int $number): string
    {
        $words = Number::spell($number, locale: 'pt_BR');

        if ($words === false) {
            throw new RuntimeException('Não foi possível escrever o valor por extenso. Verifique se a extensão intl do PHP está ativa.');
        }

        return $words;
    }
}
