<?php

namespace App\Http\Controllers;

use App\Actions\Expenses\CreateExpense;
use App\Actions\Expenses\SummarizeExpenses;
use App\Enums\ExpenseStatus;
use App\Enums\LeaseStatus;
use App\Http\Controllers\Concerns\ResolvesMonthFilter;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    use ResolvesMonthFilter;

    /**
     * Display the authenticated account's expenses due in a month.
     */
    public function index(Request $request, SummarizeExpenses $summarizeExpenses): Response
    {
        $accountId = $request->user()->account_id;
        $month = $this->resolveMonth($request->string('month')->toString());
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        $monthExpenses = fn (): Builder => Expense::where('account_id', $accountId)->dueInMonth($month);

        return Inertia::render('expenses/Index', [
            'expenses' => $monthExpenses()
                ->with('property:id,type,street,number,complement,neighborhood,city,state,deleted_at')
                ->filterByStatus($status)
                ->search($search)
                ->orderBy('due_date')
                ->orderBy('id')
                ->paginate(15)
                ->withQueryString(),
            'summary' => $summarizeExpenses->handle($monthExpenses()),
            'filters' => [
                'month' => $month->format('Y-m'),
                'search' => $search,
                'status' => $status !== '' ? $status : null,
            ],
            'properties' => Property::where('account_id', $accountId)
                ->orderBy('street')
                ->get(['id', 'street', 'number', 'complement', 'neighborhood', 'city', 'state']),
            'leaseOptions' => Lease::where('account_id', $accountId)
                ->with('tenant:id,name,deleted_at')
                ->orderByRaw('status = ? desc', [LeaseStatus::Active->value])
                ->latest('start_date')
                ->get(['id', 'tenant_id', 'property_id', 'status', 'start_date', 'end_date']),
        ]);
    }

    /**
     * Store a newly created expense, optionally charging it to a tenant.
     */
    public function store(ExpenseRequest $request, CreateExpense $createExpense): RedirectResponse
    {
        $chargeLeaseId = $request->chargeLeaseId();

        $createExpense->handle(
            $request->user()->account_id,
            $request->expenseAttributes(),
            $chargeLeaseId !== null ? Lease::findOrFail($chargeLeaseId) : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $chargeLeaseId !== null
                ? __('Despesa cadastrada e cobrança gerada para o inquilino.')
                : __('Despesa cadastrada com sucesso.'),
        ]);

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
