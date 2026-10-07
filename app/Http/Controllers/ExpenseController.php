<?php

namespace App\Http\Controllers;

use App\Actions\Expenses\SummarizeExpenses;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Http\Controllers\Concerns\ResolvesMonthFilter;
use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Controllers\Concerns\SortsTable;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use SortDirection;

class ExpenseController extends Controller
{
    use ResolvesMonthFilter, ResolvesSelectedRecord, SortsTable;

    /**
     * Display the authenticated account's expenses due in a month.
     */
    public function index(Request $request, SummarizeExpenses $summarizeExpenses): Response
    {
        $accountId = $request->user()->account_id;
        $month = $this->resolveMonth($request->string('month')->toString());
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $type = $request->enum('type', ExpenseType::class);

        $monthExpenses = fn (): Builder => Expense::where('account_id', $accountId)->dueInMonth($month);
        $withDetails = ['property:id,type,street,number,complement,neighborhood,city,state,deleted_at'];

        $list = $monthExpenses()
            ->with($withDetails)
            ->filterByStatus($status)
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type))
            ->search($search);

        $sorting = $this->applySort($list, $request, [
            'property' => fn (Builder $query, SortDirection $direction) => $query->orderBy(
                Property::withTrashed()->select('street')->whereColumn('properties.id', 'expenses.property_id'),
                $direction,
            ),
            'type' => fn (Builder $query, SortDirection $direction) => $query->orderBy(new Expression("CASE type WHEN 'condo_fee' THEN 0 WHEN 'property_tax' THEN 1 WHEN 'maintenance' THEN 2 ELSE 3 END"), $direction),
            'due_date' => fn (Builder $query, SortDirection $direction) => $query->orderBy('due_date', $direction),
            'amount' => fn (Builder $query, SortDirection $direction) => $query->orderBy('amount', $direction),
            'status' => function (Builder $query, SortDirection $direction): void {
                $query->orderBy(new Expression("CASE status WHEN 'pending' THEN 0 WHEN 'paid' THEN 1 ELSE 2 END"), $direction);
                $query->orderBy('due_date');
            },
        ], default: 'property');

        return Inertia::render('expenses/Index', [
            'expenses' => $list
                ->paginate(15)
                ->appends($this->queryWithoutSelection($request)),
            'selected' => $this->resolveSelectedRecord(
                $request,
                Expense::where('account_id', $accountId)->with($withDetails),
            ),
            'summary' => $summarizeExpenses->handle($monthExpenses()),
            'filters' => [
                'month' => $month->format('Y-m'),
                'search' => $search,
                'status' => $status !== '' ? $status : null,
                'type' => $type?->value,
                ...$sorting,
            ],
            'properties' => Property::where('account_id', $accountId)
                ->orderBy('street')
                ->get(['id', 'street', 'number', 'complement', 'neighborhood', 'city', 'state']),
        ]);
    }

    /**
     * Store a newly created expense.
     */
    public function store(ExpenseRequest $request): RedirectResponse
    {
        Expense::create([
            ...$request->expenseAttributes(),
            'account_id' => $request->user()->account_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Despesa cadastrada com sucesso.')]);

        return back();
    }

    /**
     * Update the given expense.
     */
    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('update', $expense);

        $expense->update($request->expenseAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Despesa atualizada com sucesso.')]);

        return back();
    }

    /**
     * Mark the given pending expense as paid.
     */
    public function pay(Request $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('pay', $expense);

        $validated = $request->validate([
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'payment_date.before_or_equal' => __('A data de pagamento não pode estar no futuro.'),
        ]);

        $expense->update([
            'payment_date' => $validated['payment_date'],
            'status' => ExpenseStatus::Paid,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Despesa marcada como paga.')]);

        return back();
    }

    /**
     * Remove the given expense.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        $expense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Despesa removida com sucesso.')]);

        return back();
    }
}
