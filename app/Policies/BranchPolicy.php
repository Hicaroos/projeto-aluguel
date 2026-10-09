<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class BranchPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can update the branch.
     */
    public function update(User $user, Branch $branch): Response
    {
        return $this->belongsToUserAccount($user, $branch);
    }

    /**
     * Determine whether the user can delete the branch: only when it never had properties,
     * since their history stays with the branch.
     */
    public function delete(User $user, Branch $branch): Response
    {
        $ownership = $this->belongsToUserAccount($user, $branch);

        if ($ownership->denied()) {
            return $ownership;
        }

        return $branch->properties()->withoutGlobalScopes()->exists()
            ? Response::deny(__('Esta unidade possui imóveis e não pode ser excluída. Desative-a para que deixe de ser usada.'))
            : Response::allow();
    }
}
