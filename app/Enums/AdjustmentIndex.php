<?php

namespace App\Enums;

enum AdjustmentIndex: string
{
    case Igpm = 'igpm';
    case Ipca = 'ipca';
    case Inpc = 'inpc';
    case Ivar = 'ivar';
    case Negotiated = 'negotiated';

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
            self::Negotiated => 'livre negociação entre as partes',
        };
    }

    /**
     * Get the contract clause describing how the rent is adjusted every 12 months.
     */
    public function clause(): string
    {
        return $this === self::Negotiated
            ? 'O aluguel será reajustado a cada período de 12 (doze) meses, em valor livremente negociado entre as partes, observada a legislação vigente.'
            : "O aluguel será reajustado a cada período de 12 (doze) meses, pela variação acumulada do índice {$this->label()} ou, na sua extinção, por outro índice oficial que venha a substituí-lo.";
    }
}
