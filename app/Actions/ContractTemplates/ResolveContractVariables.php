<?php

namespace App\Actions\ContractTemplates;

use App\Concerns\SpellsMoney;
use App\Enums\ContractVariable;
use App\Enums\GuaranteeType;
use App\Models\Guarantor;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Property;
use App\Models\Tenant;
use Carbon\CarbonInterface;

class ResolveContractVariables
{
    use SpellsMoney;

    /**
     * Placeholder printed for every detail that was not filled in, so it can be completed by hand.
     */
    public const string BLANK = '____________________';

    /**
     * Resolve the value of every contract variable for the given lease. Missing details are null.
     *
     * @return array<string, string|null>
     */
    public function handle(Lease $lease): array
    {
        $lease->loadMissing(['tenant', 'property.owner', 'guarantor']);

        $owner = $lease->property->owner;
        $tenant = $lease->tenant;
        $property = $lease->property;
        $guarantor = $lease->guarantor;

        $values = [
            ContractVariable::OwnerQualification->value => $this->qualification($owner),
            ContractVariable::OwnerName->value => $owner->name,
            ContractVariable::OwnerDocument->value => $this->document($owner->cpf_cnpj),
            ContractVariable::OwnerRg->value => $owner->rg,
            ContractVariable::OwnerNationality->value => $owner->nationality,
            ContractVariable::OwnerMaritalStatus->value => $owner->marital_status?->label(),
            ContractVariable::OwnerProfession->value => $owner->profession,
            ContractVariable::OwnerAddress->value => $this->address($owner),
            ContractVariable::OwnerEmail->value => $owner->email,
            ContractVariable::OwnerPhone->value => $this->phone($owner->phone),
            ContractVariable::OwnerPixKey->value => $owner->pix_key,

            ContractVariable::TenantQualification->value => $this->qualification($tenant),
            ContractVariable::TenantName->value => $tenant->name,
            ContractVariable::TenantDocument->value => $this->document($tenant->cpf_cnpj),
            ContractVariable::TenantRg->value => $tenant->rg,
            ContractVariable::TenantNationality->value => $tenant->nationality,
            ContractVariable::TenantMaritalStatus->value => $tenant->marital_status?->label(),
            ContractVariable::TenantProfession->value => $tenant->profession,
            ContractVariable::TenantAddress->value => $this->address($tenant),
            ContractVariable::TenantEmail->value => $tenant->email,
            ContractVariable::TenantPhone->value => $this->phone($tenant->phone),

            ContractVariable::PropertyAddress->value => $this->address($property),
            ContractVariable::PropertyType->value => $property->type->label(),
            ContractVariable::PropertyCity->value => "{$property->city}/{$property->state}",

            ContractVariable::LeaseAmount->value => $this->money($lease->amount),
            ContractVariable::LeaseAmountInWords->value => $this->spellMoney($lease->amount),
            ContractVariable::LeaseDueDay->value => (string) $lease->due_day,
            ContractVariable::LeaseStartDate->value => $this->longDate($lease->start_date),
            ContractVariable::LeaseEndDate->value => $this->longDate($lease->end_date),
            ContractVariable::LeaseDurationMonths->value => (string) (int) round($lease->start_date->diffInMonths($lease->end_date)),
            ContractVariable::LeasePurpose->value => $lease->purpose->label(),
            ContractVariable::LeaseAdjustmentIndex->value => $lease->adjustment_index->label(),
            ContractVariable::LeaseLateFee->value => $this->percent($lease->late_fee_percent),
            ContractVariable::LeaseMonthlyInterest->value => $this->percent($lease->monthly_interest_percent),
            ContractVariable::LeaseTerminationFeeMonths->value => (string) $lease->termination_fee_months,
            ContractVariable::LeaseNotes->value => $lease->notes,

            ContractVariable::GuaranteeClause->value => $this->guaranteeClause($lease),
            ContractVariable::GuaranteeType->value => $this->guaranteeTypeLabel($lease->guarantee_type),
            ContractVariable::GuarantorName->value => $guarantor?->name,
            ContractVariable::GuarantorQualification->value => $guarantor ? $this->qualification($guarantor) : null,
            ContractVariable::GuarantorSpouseName->value => $guarantor?->spouse_name,

            ContractVariable::Today->value => $this->longDate(today()),
            ContractVariable::Court->value => "{$property->city}/{$property->state}",
            ContractVariable::Signatures->value => $this->signatures($lease),
        ];

        return array_map(fn (?string $value): ?string => blank($value) ? null : $value, $values);
    }

    /**
     * Describe a person the way contracts identify the parties, using only the details that were filled in,
     * e.g. "Maria Souza, brasileira, casado(a), engenheira, portador(a) do RG nº 1234, inscrito(a) no CPF sob o nº 123.456.789-00,
     * residente e domiciliado(a) em Rua A, 10, Centro, Curitiba/PR, CEP 80000-000".
     */
    private function qualification(Owner|Tenant|Guarantor $person): string
    {
        $address = $this->address($person);
        $document = $this->document($person->cpf_cnpj);
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

    /**
     * Build the guarantee clause matching how the lease is guaranteed.
     */
    private function guaranteeClause(Lease $lease): string
    {
        return match ($lease->guarantee_type) {
            GuaranteeType::None => 'A presente locação é celebrada sem garantia locatícia.',
            GuaranteeType::Deposit => sprintf(
                'Como garantia das obrigações assumidas, o LOCATÁRIO entrega ao LOCADOR, a título de caução, a importância de %s (%s), que será devolvida ao término da locação, desde que cumpridas todas as obrigações deste contrato, nos termos do art. 38, § 2º, da Lei nº 8.245/91.',
                $lease->deposit_amount !== null ? $this->money($lease->deposit_amount) : self::BLANK,
                $lease->deposit_amount !== null ? $this->spellMoney($lease->deposit_amount) : self::BLANK,
            ),
            GuaranteeType::Guarantor => $this->guarantorClause($lease->guarantor),
            GuaranteeType::SuretyBond => sprintf(
                'Como garantia das obrigações assumidas, o LOCATÁRIO contrata seguro de fiança locatícia junto à seguradora %s, apólice nº %s, que deverá permanecer vigente durante todo o prazo da locação.',
                $lease->surety_insurer ?: self::BLANK,
                $lease->surety_policy_number ?: self::BLANK,
            ),
        };
    }

    /**
     * Build the clause in which the guarantor takes joint responsibility for the lease.
     */
    private function guarantorClause(?Guarantor $guarantor): string
    {
        $clause = sprintf(
            'Assina o presente contrato, na qualidade de FIADOR e principal pagador, solidariamente responsável com o LOCATÁRIO por todas as obrigações aqui assumidas até a efetiva entrega das chaves, %s',
            $guarantor ? $this->qualification($guarantor) : self::BLANK,
        );

        if ($guarantor?->spouse_name) {
            $clause .= ", com a anuência de seu cônjuge, {$guarantor->spouse_name}";
            $clause .= $guarantor->spouse_cpf ? ", inscrito(a) no CPF sob o nº {$this->document($guarantor->spouse_cpf)}" : '';
        }

        if ($guarantor?->property_registration) {
            $clause .= ", oferecendo como garantia o imóvel objeto da {$guarantor->property_registration}";
        }

        return $clause.', renunciando expressamente aos benefícios previstos nos arts. 827, 835 e 838 do Código Civil.';
    }

    /**
     * Build the signature lines, one block per line break: the parties, the guarantor and spouse when there is one,
     * and two witnesses.
     */
    private function signatures(Lease $lease): string
    {
        $line = '______________________________________________';
        $signers = [
            "LOCADOR: {$lease->property->owner->name}",
            "LOCATÁRIO: {$lease->tenant->name}",
        ];

        if ($lease->guarantee_type === GuaranteeType::Guarantor && $lease->guarantor !== null) {
            $signers[] = "FIADOR: {$lease->guarantor->name}";

            if ($lease->guarantor->spouse_name) {
                $signers[] = "CÔNJUGE DO FIADOR: {$lease->guarantor->spouse_name}";
            }
        }

        $signers[] = 'TESTEMUNHA 1 — Nome e CPF:';
        $signers[] = 'TESTEMUNHA 2 — Nome e CPF:';

        return collect($signers)
            ->map(fn (string $signer): string => "\n\n{$line}\n{$signer}")
            ->implode('');
    }

    private function guaranteeTypeLabel(GuaranteeType $type): string
    {
        return match ($type) {
            GuaranteeType::None => 'sem garantia',
            GuaranteeType::Deposit => 'caução',
            GuaranteeType::Guarantor => 'fiança',
            GuaranteeType::SuretyBond => 'seguro-fiança',
        };
    }

    private function address(Owner|Tenant|Guarantor|Property $place): ?string
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

    private function document(?string $value): ?string
    {
        $digits = (string) preg_replace('/\D/', '', (string) $value);

        return match (strlen($digits)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits),
            default => $value,
        };
    }

    private function phone(?string $value): ?string
    {
        $digits = (string) preg_replace('/\D/', '', (string) $value);

        return match (strlen($digits)) {
            11 => preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits),
            10 => preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits),
            default => $value,
        };
    }

    private function money(string $amount): string
    {
        return 'R$ '.number_format((float) $amount, 2, ',', '.');
    }

    private function percent(string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
    }

    private function longDate(CarbonInterface $date): string
    {
        return $date->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y');
    }
}
