<?php

namespace App\Concerns;

trait FormatsBrazilianNumbers
{
    /**
     * Format a CPF (000.000.000-00) or CNPJ (00.000.000/0000-00) stored as digits.
     */
    protected function formatDocument(?string $value): ?string
    {
        $digits = (string) preg_replace('/\D/', '', (string) $value);

        return match (strlen($digits)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits),
            default => $value,
        };
    }

    /**
     * Format a phone number with area code stored as digits, e.g. (87) 99123-4567.
     */
    protected function formatPhone(?string $value): ?string
    {
        $digits = (string) preg_replace('/\D/', '', (string) $value);

        return match (strlen($digits)) {
            11 => preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits),
            10 => preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits),
            default => $value,
        };
    }
}
