<?php

namespace App\Enums;

/**
 * The placeholders a contract template can use, stored in the template as <span data-variable="key">.
 */
enum ContractVariable: string
{
    case OwnerQualification = 'owner.qualification';
    case OwnerName = 'owner.name';
    case OwnerDocument = 'owner.document';
    case OwnerRg = 'owner.rg';
    case OwnerNationality = 'owner.nationality';
    case OwnerMaritalStatus = 'owner.marital_status';
    case OwnerProfession = 'owner.profession';
    case OwnerAddress = 'owner.address';
    case OwnerEmail = 'owner.email';
    case OwnerPhone = 'owner.phone';
    case OwnerPixKey = 'owner.pix_key';

    case TenantQualification = 'tenant.qualification';
    case TenantName = 'tenant.name';
    case TenantDocument = 'tenant.document';
    case TenantRg = 'tenant.rg';
    case TenantNationality = 'tenant.nationality';
    case TenantMaritalStatus = 'tenant.marital_status';
    case TenantProfession = 'tenant.profession';
    case TenantAddress = 'tenant.address';
    case TenantEmail = 'tenant.email';
    case TenantPhone = 'tenant.phone';

    case PropertyAddress = 'property.address';
    case PropertyType = 'property.type';
    case PropertyCity = 'property.city';

    case LeaseAmount = 'lease.amount';
    case LeaseAmountInWords = 'lease.amount_in_words';
    case LeaseDueDay = 'lease.due_day';
    case LeaseStartDate = 'lease.start_date';
    case LeaseEndDate = 'lease.end_date';
    case LeaseDurationMonths = 'lease.duration_months';
    case LeasePurpose = 'lease.purpose';
    case LeaseAdjustmentIndex = 'lease.adjustment_index';
    case LeaseLateFee = 'lease.late_fee';
    case LeaseMonthlyInterest = 'lease.monthly_interest';
    case LeaseTerminationFeeMonths = 'lease.termination_fee_months';
    case LeaseNotes = 'lease.notes';

    case GuaranteeClause = 'guarantee.clause';
    case GuaranteeType = 'guarantee.type';
    case GuarantorName = 'guarantor.name';
    case GuarantorQualification = 'guarantor.qualification';
    case GuarantorSpouseName = 'guarantor.spouse_name';

    case Today = 'general.today';
    case Court = 'general.court';
    case Signatures = 'general.signatures';

    /**
     * Get the label shown on the variable tag in the editor.
     */
    public function label(): string
    {
        return match ($this) {
            self::OwnerQualification => 'Qualificação completa do locador',
            self::OwnerName => 'Nome do locador',
            self::OwnerDocument => 'CPF/CNPJ do locador',
            self::OwnerRg => 'RG do locador',
            self::OwnerNationality => 'Nacionalidade do locador',
            self::OwnerMaritalStatus => 'Estado civil do locador',
            self::OwnerProfession => 'Profissão do locador',
            self::OwnerAddress => 'Endereço do locador',
            self::OwnerEmail => 'E-mail do locador',
            self::OwnerPhone => 'Telefone do locador',
            self::OwnerPixKey => 'Chave PIX do locador',
            self::TenantQualification => 'Qualificação completa do locatário',
            self::TenantName => 'Nome do locatário',
            self::TenantDocument => 'CPF/CNPJ do locatário',
            self::TenantRg => 'RG do locatário',
            self::TenantNationality => 'Nacionalidade do locatário',
            self::TenantMaritalStatus => 'Estado civil do locatário',
            self::TenantProfession => 'Profissão do locatário',
            self::TenantAddress => 'Endereço atual do locatário',
            self::TenantEmail => 'E-mail do locatário',
            self::TenantPhone => 'Telefone do locatário',
            self::PropertyAddress => 'Endereço do imóvel',
            self::PropertyType => 'Tipo do imóvel',
            self::PropertyCity => 'Cidade/UF do imóvel',
            self::LeaseAmount => 'Valor do aluguel',
            self::LeaseAmountInWords => 'Valor do aluguel por extenso',
            self::LeaseDueDay => 'Dia do vencimento',
            self::LeaseStartDate => 'Data de início',
            self::LeaseEndDate => 'Data de término',
            self::LeaseDurationMonths => 'Prazo em meses',
            self::LeasePurpose => 'Finalidade',
            self::LeaseAdjustmentIndex => 'Índice de reajuste',
            self::LeaseLateFee => 'Multa por atraso',
            self::LeaseMonthlyInterest => 'Juros ao mês',
            self::LeaseTerminationFeeMonths => 'Multa rescisória (aluguéis)',
            self::LeaseNotes => 'Observações',
            self::GuaranteeClause => 'Cláusula de garantia',
            self::GuaranteeType => 'Tipo de garantia',
            self::GuarantorName => 'Nome do fiador',
            self::GuarantorQualification => 'Qualificação completa do fiador',
            self::GuarantorSpouseName => 'Nome do cônjuge do fiador',
            self::Today => 'Data de hoje',
            self::Court => 'Foro (cidade/UF do imóvel)',
            self::Signatures => 'Assinaturas (partes, fiador e testemunhas)',
        };
    }

    /**
     * Get the group the variable is listed under in the editor.
     */
    public function group(): string
    {
        return match (explode('.', $this->value)[0]) {
            'owner' => 'Locador',
            'tenant' => 'Locatário',
            'property' => 'Imóvel',
            'lease' => 'Contrato',
            'guarantee', 'guarantor' => 'Garantia',
            default => 'Geral',
        };
    }

    /**
     * Get the catalog sent to the editor.
     *
     * @return list<array{key: string, label: string, group: string}>
     */
    public static function catalog(): array
    {
        return array_map(
            fn (self $variable): array => [
                'key' => $variable->value,
                'label' => $variable->label(),
                'group' => $variable->group(),
            ],
            self::cases(),
        );
    }
}
