<?php

namespace App\Enums;

/**
 * Who bears an expense of a property managed by an agency.
 */
enum ExpensePayer: string
{
    /** Taken from the owner's payouts. */
    case Owner = 'owner';

    /** A cost of the agency itself. */
    case Agency = 'agency';
}
