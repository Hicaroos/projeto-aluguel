<?php

namespace App\Enums;

enum LeasePurpose: string
{
    case Residential = 'residential';
    case Commercial = 'commercial';

    /**
     * Get the label used in lease contracts.
     */
    public function label(): string
    {
        return match ($this) {
            self::Residential => 'residencial',
            self::Commercial => 'comercial',
        };
    }
}
