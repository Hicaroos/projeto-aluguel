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

    case SellerQualification = 'seller.qualification';
    case SellerName = 'seller.name';
    case SellerDocument = 'seller.document';
    case SellerAddress = 'seller.address';

    case BuyerQualification = 'buyer.qualification';
    case BuyerName = 'buyer.name';
    case BuyerDocument = 'buyer.document';
    case BuyerAddress = 'buyer.address';
    case BuyerPhone = 'buyer.phone';
    case BuyerEmail = 'buyer.email';

    case PropertyAddress = 'property.address';
    case PropertyType = 'property.type';
    case PropertyCity = 'property.city';
    case PropertyRegistration = 'property.registration';
    case PropertyFeatures = 'property.features';

    case LeaseAmount = 'lease.amount';
    case LeaseAmountInWords = 'lease.amount_in_words';
    case LeaseDueDay = 'lease.due_day';
    case LeaseStartDate = 'lease.start_date';
    case LeaseEndDate = 'lease.end_date';
    case LeaseDurationMonths = 'lease.duration_months';
    case LeasePurpose = 'lease.purpose';
    case LeaseAdjustmentIndex = 'lease.adjustment_index';
    case LeaseAdjustmentClause = 'lease.adjustment_clause';
    case LeaseLateFee = 'lease.late_fee';
    case LeaseMonthlyInterest = 'lease.monthly_interest';
    case LeaseTerminationFeeMonths = 'lease.termination_fee_months';
    case LeaseNotes = 'lease.notes';

    case GuaranteeClause = 'guarantee.clause';
    case GuaranteeType = 'guarantee.type';
    case GuarantorName = 'guarantor.name';
    case GuarantorQualification = 'guarantor.qualification';
    case GuarantorSpouseName = 'guarantor.spouse_name';

    case SalePrice = 'sale.price';
    case SalePriceInWords = 'sale.price_in_words';
    case SaleDownPayment = 'sale.down_payment';
    case SaleDownPaymentInWords = 'sale.down_payment_in_words';
    case SaleBalance = 'sale.balance';
    case SaleBalanceInWords = 'sale.balance_in_words';
    case SalePaymentPlan = 'sale.payment_plan';
    case SaleDate = 'sale.date';
    case SaleDeedDeadline = 'sale.deed_deadline';
    case SaleIntermediationClause = 'sale.intermediation_clause';
    case SaleNotes = 'sale.notes';

    case Today = 'general.today';
    case Court = 'general.court';
    case Signatures = 'general.signatures';
    case SaleSignatures = 'general.sale_signatures';
    case ReceiptSignature = 'general.receipt_signature';

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
            self::SellerQualification => 'Qualificação completa do vendedor',
            self::SellerName => 'Nome do vendedor',
            self::SellerDocument => 'CPF/CNPJ do vendedor',
            self::SellerAddress => 'Endereço do vendedor',
            self::BuyerQualification => 'Qualificação completa do comprador',
            self::BuyerName => 'Nome do comprador',
            self::BuyerDocument => 'CPF/CNPJ do comprador',
            self::BuyerAddress => 'Endereço do comprador',
            self::BuyerPhone => 'Telefone do comprador',
            self::BuyerEmail => 'E-mail do comprador',
            self::PropertyAddress => 'Endereço do imóvel',
            self::PropertyType => 'Tipo do imóvel',
            self::PropertyCity => 'Cidade/UF do imóvel',
            self::PropertyRegistration => 'Matrícula do imóvel',
            self::PropertyFeatures => 'Características do imóvel',
            self::LeaseAmount => 'Valor do aluguel',
            self::LeaseAmountInWords => 'Valor do aluguel por extenso',
            self::LeaseDueDay => 'Dia do vencimento',
            self::LeaseStartDate => 'Data de início',
            self::LeaseEndDate => 'Data de término',
            self::LeaseDurationMonths => 'Prazo em meses',
            self::LeasePurpose => 'Finalidade',
            self::LeaseAdjustmentIndex => 'Índice de reajuste',
            self::LeaseAdjustmentClause => 'Cláusula de reajuste',
            self::LeaseLateFee => 'Multa por atraso',
            self::LeaseMonthlyInterest => 'Juros ao mês',
            self::LeaseTerminationFeeMonths => 'Multa rescisória (aluguéis)',
            self::LeaseNotes => 'Observações',
            self::GuaranteeClause => 'Cláusula de garantia',
            self::GuaranteeType => 'Tipo de garantia',
            self::GuarantorName => 'Nome do fiador',
            self::GuarantorQualification => 'Qualificação completa do fiador',
            self::GuarantorSpouseName => 'Nome do cônjuge do fiador',
            self::SalePrice => 'Valor da venda',
            self::SalePriceInWords => 'Valor da venda por extenso',
            self::SaleDownPayment => 'Valor do sinal',
            self::SaleDownPaymentInWords => 'Valor do sinal por extenso',
            self::SaleBalance => 'Saldo a pagar (venda menos sinal)',
            self::SaleBalanceInWords => 'Saldo a pagar por extenso',
            self::SalePaymentPlan => 'Forma de pagamento',
            self::SaleDate => 'Data da venda',
            self::SaleDeedDeadline => 'Prazo para a escritura',
            self::SaleIntermediationClause => 'Cláusula de intermediação',
            self::SaleNotes => 'Observações da venda',
            self::Today => 'Data de hoje',
            self::Court => 'Foro (cidade/UF do imóvel)',
            self::Signatures => 'Assinaturas (partes, fiador e testemunhas)',
            self::SaleSignatures => 'Assinaturas (vendedor, comprador e testemunhas)',
            self::ReceiptSignature => 'Assinatura de quem recebe o sinal',
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
            'seller' => 'Vendedor',
            'buyer' => 'Comprador',
            'property' => 'Imóvel',
            'lease' => 'Contrato',
            'guarantee', 'guarantor' => 'Garantia',
            'sale' => 'Venda',
            default => 'Geral',
        };
    }

    /**
     * Get the kinds of template the variable can be used in.
     *
     * @return list<ContractTemplateKind>
     */
    public function kinds(): array
    {
        $sales = [ContractTemplateKind::SaleContract, ContractTemplateKind::DownPaymentReceipt];

        return match ($this) {
            self::PropertyAddress, self::PropertyType, self::PropertyCity, self::Today, self::Court => ContractTemplateKind::cases(),
            self::PropertyRegistration, self::PropertyFeatures => $sales,
            self::SaleSignatures => [ContractTemplateKind::SaleContract],
            self::ReceiptSignature => [ContractTemplateKind::DownPaymentReceipt],
            self::Signatures => [ContractTemplateKind::Lease],
            default => in_array(explode('.', $this->value)[0], ['seller', 'buyer', 'sale'], true)
                ? $sales
                : [ContractTemplateKind::Lease],
        };
    }

    /**
     * Get the variables a kind of template can use.
     *
     * @return list<self>
     */
    public static function forKind(ContractTemplateKind $kind): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $variable): bool => in_array($kind, $variable->kinds(), true),
        ));
    }

    /**
     * Get the catalog sent to the editor for the given kind of template.
     *
     * @return list<array{key: string, label: string, group: string}>
     */
    public static function catalog(ContractTemplateKind $kind = ContractTemplateKind::Lease): array
    {
        return array_map(
            fn (self $variable): array => [
                'key' => $variable->value,
                'label' => $variable->label(),
                'group' => $variable->group(),
            ],
            self::forKind($kind),
        );
    }
}
