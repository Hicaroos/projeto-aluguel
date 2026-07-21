<?php

namespace App\Enums;

enum AccountType: string
{
    case SingleOwner = 'single_owner';
    case Agency = 'agency';
}
