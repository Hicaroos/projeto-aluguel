<?php

namespace App\Enums;

enum AdjustmentIndex: string
{
    case Igpm = 'igpm';
    case Ipca = 'ipca';
    case Inpc = 'inpc';
    case Ivar = 'ivar';

    /**
     * Get the label used in lease contracts.
     */
    public function label(): string
    {
        return match ($this) {
            self::Igpm => 'IGP-M/FGV',
            self::Ipca => 'IPCA/IBGE',
            self::Inpc => 'INPC/IBGE',
            self::Ivar => 'IVAR/FGV',
        };
    }
}
