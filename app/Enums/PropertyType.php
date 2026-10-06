<?php

namespace App\Enums;

enum PropertyType: string
{
    case House = 'house';
    case Apartment = 'apartment';
    case Commercial = 'commercial';
    case Land = 'land';

    /**
     * Get the label used in lease contracts.
     */
    public function label(): string
    {
        return match ($this) {
            self::House => 'casa',
            self::Apartment => 'apartamento',
            self::Commercial => 'imóvel comercial',
            self::Land => 'terreno',
        };
    }
}
