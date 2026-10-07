<?php

namespace App\Concerns;

trait NormalizesBrazilianNumbers
{
    /**
     * Rule for a CPF (11 digits) or CNPJ (14 digits), once reduced to its digits.
     */
    protected const string DOCUMENT_RULE = 'regex:/^(\d{11}|\d{14})$/';

    /**
     * Rule for a CPF only (11 digits), once reduced to its digits.
     */
    protected const string CPF_RULE = 'regex:/^\d{11}$/';

    /**
     * Rule for a landline (10 digits) or mobile (11 digits) number with area code, once reduced to its digits.
     */
    protected const string PHONE_RULE = 'regex:/^\d{10,11}$/';

    /**
     * Keep only the digits of a masked number, e.g. "123.456.789-00" becomes "12345678900",
     * so documents and phones are stored and compared the same way however they were typed.
     */
    protected function digitsOnly(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $digits = preg_replace('/\D/', '', $value);

        return $digits === '' ? null : $digits;
    }

    /**
     * Get the messages for the document and phone rules of the given fields.
     *
     * @param  list<string>  $documents
     * @param  list<string>  $cpfs
     * @param  list<string>  $phones
     * @return array<string, string>
     */
    protected function brazilianNumberMessages(array $documents = [], array $cpfs = [], array $phones = []): array
    {
        return [
            ...array_fill_keys(array_map(fn (string $field): string => "{$field}.regex", $documents), __('Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.')),
            ...array_fill_keys(array_map(fn (string $field): string => "{$field}.regex", $cpfs), __('Informe um CPF com 11 dígitos.')),
            ...array_fill_keys(array_map(fn (string $field): string => "{$field}.regex", $phones), __('Informe o número com DDD (10 ou 11 dígitos).')),
        ];
    }
}
