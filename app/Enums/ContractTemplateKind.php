<?php

namespace App\Enums;

/**
 * The documents the templates generate. Each kind has its own variables and default template.
 */
enum ContractTemplateKind: string
{
    case Lease = 'lease';
    case SaleContract = 'sale_contract';
    case DownPaymentReceipt = 'down_payment_receipt';
    case ServiceReceipt = 'service_receipt';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Lease => 'Contrato de locação',
            self::SaleContract => 'Contrato de compra e venda',
            self::DownPaymentReceipt => 'Recibo de sinal',
            self::ServiceReceipt => 'Recibo de prestação de serviço',
        };
    }

    /**
     * Get the name of the standard template every account starts with.
     */
    public function defaultTemplateName(): string
    {
        return match ($this) {
            self::Lease => 'Contrato residencial padrão',
            self::SaleContract => 'Compra e venda padrão',
            self::DownPaymentReceipt => 'Recibo de sinal padrão',
            self::ServiceReceipt => 'Recibo de serviço padrão',
        };
    }

    /**
     * Get the file holding the body of the standard template.
     */
    public function defaultTemplatePath(): string
    {
        return resource_path(match ($this) {
            self::Lease => 'views/contracts/default-template.html',
            self::SaleContract => 'views/contracts/sale-contract-template.html',
            self::DownPaymentReceipt => 'views/contracts/down-payment-receipt-template.html',
            self::ServiceReceipt => 'views/contracts/service-receipt-template.html',
        });
    }

    /**
     * Determine whether the kind is generated from a sale.
     */
    public function isForSales(): bool
    {
        return in_array($this, [self::SaleContract, self::DownPaymentReceipt], true);
    }

    /**
     * Determine whether the kind is generated from an expense, signed by whoever did the job.
     */
    public function isForExpenses(): bool
    {
        return $this === self::ServiceReceipt;
    }
}
