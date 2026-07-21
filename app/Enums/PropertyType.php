<?php

namespace App\Enums;

enum PropertyType: string
{
    case House = 'house';
    case Apartment = 'apartment';
    case Commercial = 'commercial';
    case Land = 'land';
}
