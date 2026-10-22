<?php

namespace App\Actions\Payouts;

use App\Enums\ExpensePayer;
use App\Enums\ExpenseStatus;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\Expense;
use App\Models\OwnerLedgerEntry;
use App\Models\Property;

class PostExpenseToLedger
{
    /**
     * Keep the owners' running accounts in line with the expense: as soon as an expense the owners
     * bear is registered, paid or not, each owner of the property is charged their share on its due
     * date, so the agency keeps it from the payouts to pay the bill. An expense the agency paid ahead
     * of its due date is charged on the payment date. Changing or cancelling the expense redoes or
     * removes those charges.
     */
    public function handle(Expense $expense): void
    {
        OwnerLedgerEntry::withoutGlobalScopes()->where('expense_id', $expense->id)->delete();

        $account = Account::find($expense->account_id);

        if (
            ! ($account?->isAgency() ?? false)
            || $expense->charged_to !== ExpensePayer::Owner
            || $expense->status === ExpenseStatus::Canceled
        ) {
            return;
        }

        $property = Property::withoutGlobalScopes()->withTrashed()->findOrFail($expense->property_id);
        $amount = (float) $expense->amount;
        $description = $expense->type->label().($expense->description ? ": {$expense->description}" : '');
        $chargedOn = $expense->payment_date !== null && $expense->payment_date->lt($expense->due_date)
            ? $expense->payment_date
            : $expense->due_date;

        foreach ($property->ownerShares() as $ownerId => $share) {
            OwnerLedgerEntry::create([
                'account_id' => $expense->account_id,
                'owner_id' => $ownerId,
                'property_id' => $property->id,
                'expense_id' => $expense->id,
                'type' => LedgerEntryType::Expense,
                'description' => $description,
                'amount' => -round($amount * $share / 100, 2),
                'base_amount' => $amount,
                'share_percent' => $share,
                'occurred_on' => $chargedOn->toDateString(),
                'available_on' => $chargedOn->toDateString(),
            ]);
        }
    }
}
