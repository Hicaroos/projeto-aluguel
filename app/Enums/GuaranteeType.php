<?php

namespace App\Enums;

enum GuaranteeType: string
{
    case None = 'none';
    case Deposit = 'deposit';
    case Guarantor = 'guarantor';
    case SuretyBond = 'surety_bond';
}
