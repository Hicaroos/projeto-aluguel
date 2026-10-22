<?php

namespace App\Enums;

/**
 * What a team member is: an administrator, who also runs the agency itself, or a general member, who does
 * the daily work. Finer roles wait until real agencies show how they split the work.
 */
enum Role: string
{
    case Admin = 'admin';
    case General = 'general';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::General => 'Geral',
        };
    }

    /**
     * Get what the role may do.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::General => [Permission::ManageRentals, Permission::RegisterReceipts, Permission::ManageFinance, Permission::ManageSales, Permission::ManageMessages],
        };
    }

    /**
     * Determine whether the role works in every branch, instead of the ones it is given.
     */
    public function seesEveryBranch(): bool
    {
        return $this === self::Admin;
    }
}
