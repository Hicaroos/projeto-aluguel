<?php

namespace App\Actions\Leases;

use App\Actions\ContractTemplates\ResolveContractVariables;
use App\Enums\ContractVariable;
use App\Enums\GuaranteeType;
use App\Models\LeaseRenewal;
use Carbon\CarbonInterface;

class RenderLeaseRenewalAmendment
{
    public function __construct(private ResolveContractVariables $resolveContractVariables) {}

    /**
     * Build the body of the amendment that extends the lease term, as HTML.
     *
     * The parties are described with the lease details as they are now, so updating a
     * tenant's address before generating it is reflected in the amendment.
     */
    public function handle(LeaseRenewal $renewal): string
    {
        $lease = $renewal->lease;
        $values = $this->resolveContractVariables->handle($lease);
        $value = fn (ContractVariable $variable): string => $values[$variable->value] ?? ResolveContractVariables::BLANK;

        return view('pdf.partials.lease-renewal-amendment', [
            'ownerQualification' => $value(ContractVariable::OwnerQualification),
            'tenantQualification' => $value(ContractVariable::TenantQualification),
            'guarantorQualification' => $lease->guarantee_type === GuaranteeType::Guarantor
                ? $value(ContractVariable::GuarantorQualification)
                : null,
            'propertyAddress' => $value(ContractVariable::PropertyAddress),
            'startDate' => $this->longDate($lease->start_date),
            'previousEndDate' => $this->longDate($renewal->previous_end_date),
            'newEndDate' => $this->longDate($renewal->new_end_date),
            'extensionMonths' => (int) round($renewal->previous_end_date->diffInMonths($renewal->new_end_date)),
            'guaranteeClause' => match ($lease->guarantee_type) {
                GuaranteeType::Guarantor => 'O FIADOR declara sua expressa anuência com a presente prorrogação, permanecendo solidariamente responsável, como principal pagador, por todas as obrigações do contrato até a efetiva devolução do imóvel.',
                GuaranteeType::Deposit => 'A caução prestada no contrato original permanece vinculada à locação durante o novo prazo, como garantia das obrigações do LOCATÁRIO.',
                GuaranteeType::SuretyBond => 'O LOCATÁRIO obriga-se a manter vigente o seguro de fiança locatícia durante todo o novo prazo, renovando a apólice sempre que necessário.',
                GuaranteeType::None => null,
            },
            'city' => $value(ContractVariable::PropertyCity),
            'signedOn' => $this->longDate($renewal->created_at ?? today()),
            'signatures' => $value(ContractVariable::Signatures),
        ])->render();
    }

    private function longDate(CarbonInterface $date): string
    {
        return $date->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y');
    }
}
