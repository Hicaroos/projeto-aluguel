<?php

namespace App\Actions\ContractTemplates;

use App\Concerns\FormatsContractValues;
use App\Concerns\SpellsMoney;
use App\Enums\ContractVariable;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Owner;
use App\Models\Property;
use Illuminate\Support\Collection;

class ResolveExpenseVariables
{
    use FormatsContractValues, SpellsMoney;

    /**
     * Resolve the value of every service receipt variable for the given expense. Missing details are null,
     * so the provider can complete them by hand.
     *
     * @return array<string, string|null>
     */
    public function handle(Expense $expense): array
    {
        $expense->loadMissing(['property.owner', 'property.owners', 'account']);

        $property = $expense->property;
        $owners = $this->owners($property);
        $providerDocument = $this->formatDocument($expense->provider_document);

        $values = [
            ContractVariable::ProviderName->value => $expense->provider_name,
            ContractVariable::ProviderDocument->value => $providerDocument,

            ContractVariable::ServiceDescription->value => $expense->description ?? $expense->type->label(),
            ContractVariable::ServiceAmount->value => $this->money($expense->amount),
            ContractVariable::ServiceAmountInWords->value => $this->spellMoney($expense->amount),
            ContractVariable::ServicePaymentDate->value => $expense->payment_date !== null ? $this->longDate($expense->payment_date) : null,
            ContractVariable::ServiceContractor->value => $this->contractor($expense->account, $owners),
            ContractVariable::ServicePropertyOwner->value => $this->names($owners),

            ContractVariable::PropertyAddress->value => $this->address($property),
            ContractVariable::PropertyType->value => $property->type->label(),
            ContractVariable::PropertyCity->value => "{$property->city}/{$property->state}",

            ContractVariable::Today->value => $this->longDate(today()),
            ContractVariable::Court->value => "{$property->city}/{$property->state}",
            ContractVariable::ServiceSignature->value => "\n\n______________________________________________"
                ."\nPRESTADOR: ".($expense->provider_name ?? '')
                ."\nCPF/CNPJ: ".($providerDocument ?? ''),
        ];

        return array_map(fn (?string $value): ?string => blank($value) ? null : $value, $values);
    }

    /**
     * Get the owners of the property, co-owners included.
     *
     * @return Collection<int, Owner>
     */
    private function owners(Property $property): Collection
    {
        $owners = $property->owners->toBase();

        return $owners->isEmpty() ? collect([$property->owner]) : $owners;
    }

    /**
     * Say who pays for the job: the agency that manages the property or, for owners who manage
     * their own properties, the owners themselves.
     *
     * @param  Collection<int, Owner>  $owners
     */
    private function contractor(Account $account, Collection $owners): ?string
    {
        if ($account->isAgency()) {
            return $this->identification($account->legal_name ?? $account->name, $account->document, 'inscrita');
        }

        return $owners->isEmpty()
            ? null
            : $owners->map(fn (Owner $owner): string => $this->identification($owner->name, $owner->cpf_cnpj, 'inscrito(a)'))->join('; ', ' e ');
    }

    /**
     * Name a person or company with their CPF or CNPJ, when known, e.g. "Maria Souza, inscrito(a) no CPF sob o nº 123.456.789-00".
     */
    private function identification(string $name, ?string $document, string $registered): string
    {
        $formatted = $this->formatDocument($document);

        if (blank($formatted)) {
            return $name;
        }

        $type = strlen((string) preg_replace('/\D/', '', (string) $document)) === 14 ? 'CNPJ' : 'CPF';

        return "{$name}, {$registered} no {$type} sob o nº {$formatted}";
    }

    /**
     * Join the owners' names, e.g. "Maria Souza e João Souza".
     *
     * @param  Collection<int, Owner>  $owners
     */
    private function names(Collection $owners): ?string
    {
        return $owners->isEmpty() ? null : $owners->pluck('name')->join(', ', ' e ');
    }
}
