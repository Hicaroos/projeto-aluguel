<?php

namespace App\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case StableUnion = 'stable_union';
    case Divorced = 'divorced';
    case Separated = 'separated';
    case Widowed = 'widowed';

    /**
     * Get the label used in lease contracts.
     */
    public function label(): string
    {
        return match ($this) {
            self::Single => 'solteiro(a)',
            self::Married => 'casado(a)',
            self::StableUnion => 'em união estável',
            self::Divorced => 'divorciado(a)',
            self::Separated => 'separado(a)',
            self::Widowed => 'viúvo(a)',
        };
    }
}
