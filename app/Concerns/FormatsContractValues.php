<?php

namespace App\Concerns;

use App\Models\Guarantor;
use App\Models\Owner;
use App\Models\Property;
use App\Models\Tenant;
use Carbon\CarbonInterface;

/**
 * Write people, places, amounts and dates the way contracts and receipts state them.
 */
trait FormatsContractValues
{
    use FormatsBrazilianNumbers;

    /**
     * Describe a person the way contracts identify the parties, using only the details that were filled in,
     * e.g. "Maria Souza, brasileira, casado(a), engenheira, portador(a) do RG nº 1234, inscrito(a) no CPF sob o nº 123.456.789-00,
     * residente e domiciliado(a) em Rua A, 10, Centro, Curitiba/PR, CEP 80000-000".
     */
    protected function qualification(Owner|Tenant|Guarantor $person): string
    {
        $address = $this->address($person);
        $document = $this->formatDocument($person->cpf_cnpj);
        $documentType = strlen((string) preg_replace('/\D/', '', (string) $person->cpf_cnpj)) === 14 ? 'CNPJ' : 'CPF';

        return collect([
            $person->name,
            $person->nationality,
            $person->marital_status?->label(),
            $person->profession,
            $person->rg ? "portador(a) do RG nº {$person->rg}" : null,
            $document ? "inscrito(a) no {$documentType} sob o nº {$document}" : null,
            $address ? "residente e domiciliado(a) em {$address}" : null,
        ])->filter()->implode(', ');
    }

    protected function address(Owner|Tenant|Guarantor|Property $place): ?string
    {
        $zipCode = preg_replace('/\D/', '', (string) $place->zip_code);

        $parts = collect([
            $place->street,
            $place->number,
            $place->complement,
            $place->neighborhood,
            collect([$place->city, $place->state])->filter()->implode('/'),
            strlen((string) $zipCode) === 8 ? 'CEP '.substr((string) $zipCode, 0, 5).'-'.substr((string) $zipCode, 5) : null,
        ])->filter();

        return $parts->isEmpty() ? null : $parts->implode(', ');
    }

    protected function money(string $amount): string
    {
        return 'R$ '.number_format((float) $amount, 2, ',', '.');
    }

    protected function percent(string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
    }

    protected function longDate(CarbonInterface $date): string
    {
        return $date->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y');
    }
}
