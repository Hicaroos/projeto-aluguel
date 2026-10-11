<?php

namespace App\Enums;

/**
 * When the agency pays the owner the rent of a month.
 */
enum PayoutMode: string
{
    /** Only what the tenant paid, after it was received. */
    case Received = 'received';

    /** On the agreed date whether the tenant paid or not: the agency takes the risk of late payments. */
    case Guaranteed = 'guaranteed';
}
