<?php

namespace App\Enums;

enum ExpenseType: string
{
    case PropertyTax = 'property_tax';
    case CondoFee = 'condo_fee';
    case Maintenance = 'maintenance';
    case Other = 'other';

    /**
     * Get the label shown in owner statements.
     */
    public function label(): string
    {
        return match ($this) {
            self::PropertyTax => 'IPTU',
            self::CondoFee => 'Condomínio',
            self::Maintenance => 'Manutenção',
            self::Other => 'Outra despesa',
        };
    }

    /**
     * Determine whether the expense pays someone for a job, so a service provider signs a receipt for it.
     */
    public function isService(): bool
    {
        return in_array($this, [self::Maintenance, self::Other], true);
    }
}
