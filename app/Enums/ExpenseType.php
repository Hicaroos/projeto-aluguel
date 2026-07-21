<?php

namespace App\Enums;

enum ExpenseType: string
{
    case PropertyTax = 'property_tax';
    case CondoFee = 'condo_fee';
    case Maintenance = 'maintenance';
    case Other = 'other';
}
