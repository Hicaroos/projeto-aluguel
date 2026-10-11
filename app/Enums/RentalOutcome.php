<?php

namespace App\Enums;

/**
 * What happens to the active lease of a property when it is sold.
 */
enum RentalOutcome: string
{
    /** The tenant leaves: the lease is terminated on the sale date and the property leaves the portfolio. */
    case Terminate = 'terminate';

    /** The tenant stays: the lease carries on and the property stays in the portfolio as rented. */
    case Keep = 'keep';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Terminate => 'Contrato de aluguel encerrado',
            self::Keep => 'Contrato de aluguel continua',
        };
    }
}
