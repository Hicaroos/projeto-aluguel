<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

trait ChecksAccountOwnership
{
    /**
     * Allow access only to records of the user's own account, hiding the others as not found.
     */
    protected function belongsToUserAccount(User $user, Model $model): Response
    {
        return $model->getAttribute('account_id') === $user->account_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
