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
     * Canceled expenses are left out of every figure. With the shares of an owner, keyed by property id,
     * each amount counts only for the part of the property the owner holds.
     *
     * @param  Builder<Expense>  $query
     * @param  array<int, float>|null  $propertyShares
     * @return array{total: float, paid: float, pending: float, overdue: float, overdue_count: int}
     */
    public function handle(Builder $query, ?array $propertyShares = null): array
    {
        $expenses = $query
            ->where('status', '!=', ExpenseStatus::Canceled)
            ->get(['id', 'property_id', 'amount', 'status', 'due_date']);

        $amountOf = fn (Expense $expense): float => (float) $expense->amount * ($propertyShares === null ? 1.0 : ($propertyShares[$expense->property_id] ?? 1.0));
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
