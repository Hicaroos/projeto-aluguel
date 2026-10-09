<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can change the role, branches or access of a team member.
     * Nobody changes their own access nor the account owner's, so the account always keeps
     * an active administrator.
     */
    public function update(User $user, User $member): Response
    {
        $ownership = $this->belongsToUserAccount($user, $member);

        if ($ownership->denied()) {
            return $ownership;
        }

        if ($member->is($user)) {
            return Response::deny(__('Você não pode alterar o seu próprio acesso.'));
        }

        return $member->isAccountOwner()
            ? Response::deny(__('O acesso de quem criou a conta não pode ser alterado.'))
            : Response::allow();
    }

    /**
     * Determine whether the user can delete a team member: only invitations nobody accepted yet.
     */
    public function delete(User $user, User $member): Response
    {
        $response = $this->update($user, $member);

        if ($response->denied()) {
            return $response;
        }

        return $member->hasPendingInvitation()
            ? Response::allow()
            : Response::deny(__('Quem já entrou no sistema não pode ser excluído. Desative o acesso para manter o histórico.'));
    }
}
