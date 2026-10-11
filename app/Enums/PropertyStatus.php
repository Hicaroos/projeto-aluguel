<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case Available = 'available';
    case Rented = 'rented';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';

    /** Sold and out of the portfolio, kept for its history. */
    case Sold = 'sold';
}
