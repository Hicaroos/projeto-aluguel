<?php

namespace App\Enums;

enum PropertyType: string
{
    case House = 'house';
    case Apartment = 'apartment';
    case Commercial = 'commercial';
    case Office = 'office';
    case Warehouse = 'warehouse';
    case Land = 'land';
    case Farm = 'farm';

    /**
     * Get the label used in lease contracts.
     */
    public function label(): string
    {
        return match ($this) {
            self::House => 'casa',
            self::Apartment => 'apartamento',
            self::Commercial => 'imóvel comercial',
            self::Office => 'sala comercial',
            self::Warehouse => 'galpão',
            self::Land => 'terreno',
            self::Farm => 'chácara',
        };
    }
}
