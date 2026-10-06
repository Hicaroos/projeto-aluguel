<?php

namespace App\Actions\Expenses;

use App\Actions\Payments\CreateExtraCharge;
use App\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\Lease;
use Illuminate\Support\Facades\DB;

class CreateExpense
{
    public function __construct(private CreateExtraCharge $createExtraCharge) {}

    /**
     * Create an expense and, when a lease is given, charge the same amount to its tenant.
     *
     * The expense is what the owner paid; the extra charge is what the tenant owes back.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $accountId, array $attributes, ?Lease $chargeLease = null): Expense
    {
        return DB::transaction(function () use ($accountId, $attributes, $chargeLease): Expense {
            $expense = Expense::create([...$attributes, 'account_id' => $accountId]);

            if ($chargeLease !== null) {
                $charge = $this->createExtraCharge->handle($chargeLease, [
                    'description' => $expense->description ?? $this->typeLabel($expense->type),
                    'amount' => $expense->amount,
                    'due_date' => $expense->due_date,
                ]);

                $expense->update(['payment_id' => $charge->id]);
            }

            return $expense;
        });
    }

    /**
     * Get the label used to describe an expense type when it has no description.
     */
    private function typeLabel(ExpenseType $type): string
    {
        return match ($type) {
            ExpenseType::PropertyTax => 'IPTU',
            ExpenseType::CondoFee => 'Condomínio',
            ExpenseType::Maintenance => 'Manutenção',
            ExpenseType::Other => 'Despesa',
        };
    }
}
