<?php

namespace App\Enums;

/**
 * Whether a lead wants to buy, to rent, or to list their own property with the agency.
 */
enum LeadInterest: string
{
    case Buy = 'buy';
    case Rent = 'rent';
    case List = 'list';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Comprar',
            self::Rent => 'Alugar',
            self::List => 'Anunciar imóvel',
        };
    }
}
