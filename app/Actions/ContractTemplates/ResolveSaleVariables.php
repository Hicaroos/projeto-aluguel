<?php

namespace App\Actions\ContractTemplates;

use App\Concerns\FormatsContractValues;
use App\Concerns\SpellsMoney;
use App\Enums\ContractVariable;
use App\Models\Account;
use App\Models\Property;
use App\Models\Sale;

class ResolveSaleVariables
{
    use FormatsContractValues, SpellsMoney;

    /**
     * Resolve the value of every sale document variable for the given sale. Missing details are null.
     *
     * @return array<string, string|null>
     */
    public function handle(Sale $sale): array
    {
        $sale->loadMissing(['property', 'seller', 'account']);

        $property = $sale->property;
        $seller = $sale->seller;
        $buyer = $sale->buyer();
        $balance = $sale->down_payment !== null
            ? number_format(max(0, (float) $sale->price - (float) $sale->down_payment), 2, '.', '')
            : null;

        $values = [
            ContractVariable::SellerQualification->value => $seller ? $this->qualification($seller) : null,
            ContractVariable::SellerName->value => $seller?->name,
            ContractVariable::SellerDocument->value => $seller ? $this->formatDocument($seller->cpf_cnpj) : null,
            ContractVariable::SellerAddress->value => $seller ? $this->address($seller) : null,

            ContractVariable::BuyerQualification->value => $this->qualification($buyer),
            ContractVariable::BuyerName->value => $buyer->name,
            ContractVariable::BuyerDocument->value => $this->formatDocument($buyer->cpf_cnpj),
            ContractVariable::BuyerAddress->value => $this->address($buyer),
            ContractVariable::BuyerPhone->value => $this->formatPhone($buyer->phone),
            ContractVariable::BuyerEmail->value => $buyer->email,

            ContractVariable::PropertyAddress->value => $this->address($property),
            ContractVariable::PropertyType->value => $property->type->label(),
            ContractVariable::PropertyCity->value => "{$property->city}/{$property->state}",
            ContractVariable::PropertyRegistration->value => $property->registration_number,
            ContractVariable::PropertyFeatures->value => $this->features($property),

            ContractVariable::SalePrice->value => $this->money($sale->price),
            ContractVariable::SalePriceInWords->value => $this->spellMoney($sale->price),
            ContractVariable::SaleDownPayment->value => $sale->down_payment !== null ? $this->money($sale->down_payment) : null,
            ContractVariable::SaleDownPaymentInWords->value => $sale->down_payment !== null ? $this->spellMoney($sale->down_payment) : null,
            ContractVariable::SaleBalance->value => $balance !== null ? $this->money($balance) : null,
            ContractVariable::SaleBalanceInWords->value => $balance !== null ? $this->spellMoney($balance) : null,
            ContractVariable::SalePaymentPlan->value => $sale->payment_plan?->label(),
            ContractVariable::SaleDate->value => $this->longDate($sale->sold_on),
            ContractVariable::SaleDeedDeadline->value => $sale->deed_deadline !== null ? $this->longDate($sale->deed_deadline) : null,
            ContractVariable::SaleIntermediationClause->value => $this->intermediationClause($sale, $sale->account),
            ContractVariable::SaleNotes->value => $sale->notes,

            ContractVariable::Today->value => $this->longDate(today()),
            ContractVariable::Court->value => "{$property->city}/{$property->state}",
            ContractVariable::SaleSignatures->value => $this->signatures([
                'VENDEDOR: '.($seller->name ?? ''),
                "COMPRADOR: {$buyer->name}",
                'TESTEMUNHA 1 — Nome e CPF:',
                'TESTEMUNHA 2 — Nome e CPF:',
            ]),
            ContractVariable::ReceiptSignature->value => $this->signatures([
                'RECEBEDOR (VENDEDOR): '.($seller->name ?? ''),
            ]),
        ];

        return array_map(fn (?string $value): ?string => blank($value) ? null : $value, $values);
    }

    /**
     * Describe the property for the contract, e.g. "3 quartos, sendo 1 suíte, 2 banheiros, 2 vagas,
     * 120 m² de área construída, terreno de 250 m² (10 x 25 m)".
     */
    private function features(Property $property): ?string
    {
        $area = fn (?string $value): string => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

        $land = $property->land_area !== null ? "terreno de {$area($property->land_area)} m²" : null;

        if ($property->land_front !== null && $property->land_depth !== null) {
            $dimensions = "{$area($property->land_front)} x {$area($property->land_depth)} m";
            $land = $land !== null ? "{$land} ({$dimensions})" : "terreno de {$dimensions}";
        }

        $parts = collect([
            $property->bedrooms ? $property->bedrooms.($property->bedrooms === 1 ? ' quarto' : ' quartos').($property->suites ? ", sendo {$property->suites}".($property->suites === 1 ? ' suíte' : ' suítes') : '') : null,
            $property->bathrooms ? $property->bathrooms.($property->bathrooms === 1 ? ' banheiro' : ' banheiros') : null,
            $property->parking_spaces ? $property->parking_spaces.($property->parking_spaces === 1 ? ' vaga de garagem' : ' vagas de garagem') : null,
            $property->built_area !== null ? "{$area($property->built_area)} m² de área construída" : null,
            $land,
        ])->filter();

        return $parts->isEmpty() ? null : $parts->implode(', ');
    }

    /**
     * Say who intermediated the sale and the commission due, when an agency did.
     */
    private function intermediationClause(Sale $sale, Account $account): string
    {
        if (! $account->isAgency()) {
            return 'A presente transação foi realizada diretamente entre as partes, sem intermediação imobiliária.';
        }

        $agency = collect([
            $account->legal_name ?? $account->name,
            $account->document ? 'inscrita no CNPJ sob o nº '.$this->formatDocument($account->document) : null,
            $account->creci ? "CRECI {$account->creci}" : null,
        ])->filter()->implode(', ');

        $clause = "A presente transação foi intermediada por {$agency}";

        if ($sale->commission_amount !== null) {
            $clause .= sprintf(
                ', a quem é devida a comissão de corretagem de %s (%s), correspondente a %s do valor da venda',
                $this->money($sale->commission_amount),
                $this->spellMoney($sale->commission_amount),
                $this->percent((string) $sale->commission_percent),
            );
        }

        return "{$clause}.";
    }

    /**
     * Build the signature lines, one block per signer.
     *
     * @param  list<string>  $signers
     */
    private function signatures(array $signers): string
    {
        $line = '______________________________________________';

        return collect($signers)
            ->map(fn (string $signer): string => "\n\n{$line}\n{$signer}")
            ->implode('');
    }
}
