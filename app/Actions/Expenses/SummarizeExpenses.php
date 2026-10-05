<?php

namespace App\Actions\Expenses;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;

class SummarizeExpenses
{
    /**
     * Summarize the total, paid, pending and overdue amounts of the given expenses.
     *
     * Canceled expenses are left out of every figure.
     *
     * @param  Builder<Expense>  $query
     * @return array{total: float, paid: float, pending: float, overdue: float, overdue_count: int}
     */
    public function handle(Builder $query): array
    {
        $expenses = $query
            ->where('status', '!=', ExpenseStatus::Canceled)
            ->get(['id', 'amount', 'status', 'due_date']);

        $amountOf = fn (Expense $expense): float => (float) $expense->amount;
        $pending = $expenses->filter(fn (Expense $expense): bool => $expense->isPending());
        $overdue = $pending->filter(fn (Expense $expense): bool => $expense->due_date->lt(today()));

        return [
            'total' => round($expenses->sum($amountOf), 2),
            'paid' => round($expenses->reject(fn (Expense $expense): bool => $expense->isPending())->sum($amountOf), 2),
            'pending' => round($pending->sum($amountOf), 2),
            'overdue' => round($overdue->sum($amountOf), 2),
            'overdue_count' => $overdue->count(),
        ];
    }
}
