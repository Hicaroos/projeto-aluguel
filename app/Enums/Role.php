<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case General = 'general';
    case Agent = 'agent';
    case Finance = 'finance';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::General => 'Geral',
            self::Agent => 'Corretor',
            self::Finance => 'Financeiro',
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
            self::General => [Permission::ManageRentals, Permission::RegisterReceipts, Permission::ManageFinance],
            self::Agent => [Permission::ManageRentals, Permission::RegisterReceipts],
            self::Finance => [Permission::RegisterReceipts, Permission::ManageFinance],
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
