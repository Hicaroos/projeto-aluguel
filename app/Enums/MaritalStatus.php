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
}
