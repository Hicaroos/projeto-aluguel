<?php

namespace App\Enums;

/**
 * What an entry of an owner's running account is about.
 */
enum LedgerEntryType: string
{
    /** Rent received from the tenant, or guaranteed by the agency. */
    case Rent = 'rent';

    /** The agency's monthly share of the rent. */
    case AdminFee = 'admin_fee';

    /** The agency's share of the first rent, for finding the tenant. */
    case IntermediationFee = 'intermediation_fee';

    /** The agency's share of the first rent after a renewal. */
    case RenewalFee = 'renewal_fee';

    /** The owner's part of the late fee and interest paid by the tenant. */
    case LateCharges = 'late_charges';

    /** An extra charge paid by the tenant and passed on to the owner, e.g. a repair. */
    case ExtraCharge = 'extra_charge';

    /** An expense of the property paid by the agency on the owner's behalf. */
    case Expense = 'expense';

    /** The down payment of a sale, received by the agency for the seller. */
    case SaleDownPayment = 'sale_down_payment';

    /** The agency's commission on a sale. */
    case SaleCommission = 'sale_commission';

    /** Anything else, entered by hand with a description. */
    case Adjustment = 'adjustment';

    /** Money paid to the owner. */
    case Payout = 'payout';

    /**
     * Get the label shown in the statement.
     */
    public function label(): string
    {
        return match ($this) {
            self::Rent => 'Aluguel',
            self::AdminFee => 'Taxa de administração',
            self::IntermediationFee => 'Taxa de intermediação',
            self::RenewalFee => 'Taxa de renovação',
            self::LateCharges => 'Multa e juros',
            self::ExtraCharge => 'Cobrança extra',
            self::Expense => 'Despesa',
            self::SaleDownPayment => 'Sinal da venda',
            self::SaleCommission => 'Comissão da venda',
            self::Adjustment => 'Ajuste',
            self::Payout => 'Repasse',
        };
    }

    /**
     * Determine whether the entry is what the agency earns.
     */
    public function isAgencyFee(): bool
    {
        return in_array($this, [self::AdminFee, self::IntermediationFee, self::RenewalFee, self::SaleCommission], true);
    }

    /**
     * Get the fee types the agency earns.
     *
     * @return list<self>
     */
    public static function agencyFees(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type): bool => $type->isAgencyFee()));
    }
}
