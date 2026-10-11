<?php

namespace App\Enums;

/**
 * How a buyer intends to pay for a property.
 */
enum PaymentPlan: string
{
    case Cash = 'cash';
    case Financing = 'financing';
    case Fgts = 'fgts';
    case Exchange = 'exchange';
    case Installments = 'installments';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'À vista',
            self::Financing => 'Financiamento',
            self::Fgts => 'FGTS',
            self::Exchange => 'Permuta',
            self::Installments => 'Parcelado direto',
        };
    }
}
