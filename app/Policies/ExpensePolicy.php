<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class ExpensePolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can update the expense.
     */
    public function update(User $user, Expense $expense): Response
    {
        return $this->belongsToUserAccount($user, $expense);
    }

    /**
     * Determine whether the user can mark the expense as paid: only pending expenses can be paid.
     */
    public function pay(User $user, Expense $expense): Response
    {
        $ownership = $this->belongsToUserAccount($user, $expense);

        if ($ownership->denied()) {
            return $ownership;
        }

        return $expense->isPending()
            ? Response::allow()
            : Response::deny(__('Esta despesa não está pendente.'));
    }

    /**
     * Determine whether the user can delete the expense.
     */
    public function delete(User $user, Expense $expense): Response
    {
        return $this->belongsToUserAccount($user, $expense);
    }
}
