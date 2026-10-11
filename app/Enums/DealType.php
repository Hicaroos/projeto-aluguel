<?php

namespace App\Enums;

/**
 * What a deal is about: selling or renting a property.
 */
enum DealType: string
{
    case Sale = 'sale';
    case Rent = 'rent';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Venda',
            self::Rent => 'Locação',
        };
    }
}
