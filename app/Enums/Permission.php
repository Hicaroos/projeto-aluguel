<?php

namespace App\Enums;

/**
 * What a team member may do. Each value is also the name of the gate checked by routes and pages.
 */
enum Permission: string
{
    /** Manage the agency itself: its details, branches and team. */
    case ManageAgency = 'manage-agency';

    /** Create and change properties, tenants, leases and contract templates. */
    case ManageRentals = 'manage-rentals';

    /** See the payments and register what tenants paid. */
    case RegisterReceipts = 'register-receipts';

    /** Handle the money: extra charges, undoing receipts, deposits, expenses and revenue figures. */
    case ManageFinance = 'manage-finance';
}
