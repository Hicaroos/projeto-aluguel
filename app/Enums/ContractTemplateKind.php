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

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Lease => 'Contrato de locação',
            self::SaleContract => 'Contrato de compra e venda',
            self::DownPaymentReceipt => 'Recibo de sinal',
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
        });
    }

    /**
     * Determine whether the kind is generated from a sale.
     */
    public function isForSales(): bool
    {
        return $this !== self::Lease;
    }
}
