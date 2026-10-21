<?php

namespace App\Actions\ContractTemplates;

use App\Concerns\FormatsContractValues;
use App\Concerns\SpellsMoney;
use App\Enums\ContractVariable;
use App\Enums\GuaranteeType;
use App\Models\Guarantor;
use App\Models\Lease;
use App\Models\Owner;
use Illuminate\Support\Collection;

class ResolveContractVariables
{
    use FormatsContractValues, SpellsMoney;

    /**
     * Placeholder printed for every detail that was not filled in, so it can be completed by hand.
     */
    public const string BLANK = '____________________';

    /**
     * Resolve the value of every contract variable for the given lease. Missing details are null.
     * The owner variables describe the main owner; a block repeated for each landlord gets every
     * owner's own through landlords().
     *
     * @return array<string, string|null>
     */
    public function handle(Lease $lease): array
    {
        $lease->loadMissing(['tenant', 'property.owner', 'property.owners', 'guarantor']);

        $tenant = $lease->tenant;
        $property = $lease->property;
        $guarantor = $lease->guarantor;

        $values = [
            ...$this->ownerValues($property->owner),

            ContractVariable::TenantQualification->value => $this->qualification($tenant),
            ContractVariable::TenantName->value => $tenant->name,
            ContractVariable::TenantDocument->value => $this->formatDocument($tenant->cpf_cnpj),
            ContractVariable::TenantRg->value => $tenant->rg,
            ContractVariable::TenantNationality->value => $tenant->nationality,
            ContractVariable::TenantMaritalStatus->value => $tenant->marital_status?->label(),
            ContractVariable::TenantProfession->value => $tenant->profession,
            ContractVariable::TenantAddress->value => $this->address($tenant),
            ContractVariable::TenantEmail->value => $tenant->email,
            ContractVariable::TenantPhone->value => $this->formatPhone($tenant->phone),

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
            ContractVariable::LeaseAdjustmentClause->value => $lease->adjustment_index->clause(),
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
     * Resolve the owner variables for each landlord, in the order a block repeated for each of them
     * follows: the main owner, then the co-owners by their share.
     *
     * @return list<array<string, string|null>>
     */
    public function landlords(Lease $lease): array
    {
        $lease->loadMissing(['property.owner', 'property.owners']);

        return array_values($this->owners($lease)
            ->map(fn (Owner $owner): array => array_map(fn (?string $value): ?string => blank($value) ? null : $value, $this->ownerValues($owner)))
            ->all());
    }

    /**
     * @return array<string, string|null>
     */
    private function ownerValues(Owner $owner): array
    {
        return [
            ContractVariable::OwnerQualification->value => $this->qualification($owner),
            ContractVariable::OwnerName->value => $owner->name,
            ContractVariable::OwnerDocument->value => $this->formatDocument($owner->cpf_cnpj),
            ContractVariable::OwnerRg->value => $owner->rg,
            ContractVariable::OwnerNationality->value => $owner->nationality,
            ContractVariable::OwnerMaritalStatus->value => $owner->marital_status?->label(),
            ContractVariable::OwnerProfession->value => $owner->profession,
            ContractVariable::OwnerAddress->value => $this->address($owner),
            ContractVariable::OwnerEmail->value => $owner->email,
            ContractVariable::OwnerPhone->value => $this->formatPhone($owner->phone),
            ContractVariable::OwnerPixKey->value => $owner->pix_key,
        ];
    }

    /**
     * Get the landlords: the main owner, then the co-owners by their share.
     *
     * @return Collection<int, Owner>
     */
    private function owners(Lease $lease): Collection
    {
        $property = $lease->property;
        $shares = $property->ownerShares();

        return $property->owners
            ->reject(fn (Owner $owner): bool => $owner->is($property->owner))
            ->sortByDesc(fn (Owner $owner): float => $shares[$owner->id] ?? 0.0)
            ->prepend($property->owner)
            ->values()
            ->toBase();
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
            $clause .= $guarantor->spouse_cpf ? ", inscrito(a) no CPF sob o nº {$this->formatDocument($guarantor->spouse_cpf)}" : '';
        }

        if ($guarantor?->property_registration) {
            $clause .= ", oferecendo como garantia o imóvel objeto da {$guarantor->property_registration}";
        }

        return $clause.', renunciando expressamente aos benefícios previstos nos arts. 827, 835 e 838 do Código Civil.';
    }

    /**
     * Build the signature lines, one block per line break: every landlord, the tenant, the guarantor and spouse
     * when there is one, and two witnesses.
     */
    private function signatures(Lease $lease): string
    {
        $line = '______________________________________________';
        $signers = [
            ...$this->owners($lease)->map(fn (Owner $owner): string => "LOCADOR: {$owner->name}"),
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
}
