<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Trial = 'trial';
    case Suspended = 'suspended';
}
