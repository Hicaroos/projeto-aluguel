<?php

namespace App\Policies;

use App\Models\ContractTemplate;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class ContractTemplatePolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can use the template to generate a contract.
     */
    public function view(User $user, ContractTemplate $contractTemplate): Response
    {
        return $this->belongsToUserAccount($user, $contractTemplate);
    }

    /**
     * Determine whether the user can update the template.
     */
    public function update(User $user, ContractTemplate $contractTemplate): Response
    {
        return $this->belongsToUserAccount($user, $contractTemplate);
    }

    /**
     * Determine whether the user can delete the template.
     */
    public function delete(User $user, ContractTemplate $contractTemplate): Response
    {
        return $this->belongsToUserAccount($user, $contractTemplate);
    }
}
