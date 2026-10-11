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
     * Keep the owners' running accounts in line with the expense: once an expense the owners bear
     * is paid by the agency, each owner of the property is charged their share on the payment date.
     * Changing or unpaying the expense redoes or removes those charges.
     */
    public function handle(Expense $expense): void
    {
        OwnerLedgerEntry::withoutGlobalScopes()->where('expense_id', $expense->id)->delete();

        $account = Account::find($expense->account_id);

        if (
            ! ($account?->isAgency() ?? false)
            || $expense->charged_to !== ExpensePayer::Owner
            || $expense->status !== ExpenseStatus::Paid
            || $expense->payment_date === null
        ) {
            return;
        }

        $property = Property::withoutGlobalScopes()->withTrashed()->findOrFail($expense->property_id);
        $amount = (float) $expense->amount;
        $description = $expense->type->label().($expense->description ? ": {$expense->description}" : '');

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
                'occurred_on' => $expense->payment_date->toDateString(),
                'available_on' => $expense->payment_date->toDateString(),
            ]);
        }
    }
}
