<?php

namespace App\Enums;

/**
 * Whether a lead wants to buy or to rent.
 */
enum LeadInterest: string
{
    case Buy = 'buy';
    case Rent = 'rent';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Comprar',
            self::Rent => 'Alugar',
        };
    }
}
