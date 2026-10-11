<?php

namespace App\Http\Controllers;

use App\Actions\ContractTemplates\BuildDocumentPdf;
use App\Actions\ContractTemplates\EnsureDefaultContractTemplate;
use App\Actions\ContractTemplates\RenderExpenseDocument;
use App\Actions\ContractTemplates\RenderLeaseContract;
use App\Actions\ContractTemplates\RenderSaleDocument;
use App\Actions\ContractTemplates\SanitizeContractTemplate;
use App\Enums\ContractTemplateKind;
use App\Enums\ContractVariable;
use App\Enums\ExpenseType;
use App\Enums\LeaseStatus;
use App\Http\Requests\ContractTemplateRequest;
use App\Models\ContractTemplate;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ContractTemplateController extends Controller
{
    /**
     * Display the account's templates of every kind, creating the standard ones on the first visit.
     */
    public function index(Request $request, EnsureDefaultContractTemplate $ensureDefaultContractTemplate): Response
    {
        $account = $request->user()->account;

        abort_if($account === null, 404);

        foreach (ContractTemplateKind::cases() as $kind) {
            $ensureDefaultContractTemplate->handle($account, $kind);
        }

        return Inertia::render('contract-templates/Index', [
            'templates' => $account->contractTemplates()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'kind', 'name', 'is_default', 'updated_at']),
            'kinds' => collect(ContractTemplateKind::cases())->map(fn (ContractTemplateKind $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ]),
        ]);
    }

    /**
     * Show the editor for a new template of the requested kind.
     */
    public function create(Request $request): Response
    {
        $kind = $request->enum('kind', ContractTemplateKind::class) ?? ContractTemplateKind::Lease;

        return Inertia::render('contract-templates/Edit', [
            'template' => null,
            'kind' => ['value' => $kind->value, 'label' => $kind->label()],
            'variables' => ContractVariable::catalog($kind),
        ]);
    }

    /**
     * Store a new template. The first template of its kind becomes the default of that kind.
     */
    public function store(ContractTemplateRequest $request, SanitizeContractTemplate $sanitizeContractTemplate): RedirectResponse
    {
        $accountId = $request->user()->account_id;
        $kind = $request->kind();

        ContractTemplate::create([
            ...$request->templateAttributes($sanitizeContractTemplate),
            'account_id' => $accountId,
            'kind' => $kind,
            'is_default' => ! ContractTemplate::where('account_id', $accountId)->where('kind', $kind)->exists(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Modelo cadastrado com sucesso.')]);

        return to_route('contract-templates.index');
    }

    /**
     * Show the editor for the given template.
     */
    public function edit(ContractTemplate $contractTemplate): Response
    {
        Gate::authorize('update', $contractTemplate);

        return Inertia::render('contract-templates/Edit', [
            'template' => $contractTemplate->only(['id', 'name', 'body', 'is_default']),
            'kind' => ['value' => $contractTemplate->kind->value, 'label' => $contractTemplate->kind->label()],
            'variables' => ContractVariable::catalog($contractTemplate->kind),
        ]);
    }

    /**
     * Update the given template.
     */
    public function update(ContractTemplateRequest $request, ContractTemplate $contractTemplate, SanitizeContractTemplate $sanitizeContractTemplate): RedirectResponse
    {
        Gate::authorize('update', $contractTemplate);

        $contractTemplate->update($request->templateAttributes($sanitizeContractTemplate));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Modelo atualizado com sucesso.')]);

        return to_route('contract-templates.index');
    }

    /**
     * Preview a template body as PDF before saving it, filled with the account's most recent lease,
     * sale or service expense, or with the variable labels when there is none yet.
     */
    public function preview(
        Request $request,
        SanitizeContractTemplate $sanitizeContractTemplate,
        RenderLeaseContract $renderLeaseContract,
        RenderSaleDocument $renderSaleDocument,
        RenderExpenseDocument $renderExpenseDocument,
        BuildDocumentPdf $buildDocumentPdf,
    ): SymfonyResponse {
        $kind = $request->enum('kind', ContractTemplateKind::class) ?? ContractTemplateKind::Lease;
        $validated = $request->validate(['body' => ContractTemplateRequest::bodyRules($kind)]);
        $body = $sanitizeContractTemplate->handle($validated['body']);

        if ($kind->isForSales()) {
            $html = $renderSaleDocument->render($body, Sale::query()->latest('sold_on')->latest('id')->first(), $kind);
        } elseif ($kind->isForExpenses()) {
            $expense = Expense::where('account_id', $request->user()->account_id)
                ->whereIn('type', [ExpenseType::Maintenance, ExpenseType::Other])
                ->latest('due_date')
                ->latest('id')
                ->first();

            $html = $renderExpenseDocument->render($body, $expense);
        } else {
            $lease = Lease::where('account_id', $request->user()->account_id)
                ->orderByRaw('status = ? desc', [LeaseStatus::Active->value])
                ->latest('start_date')
                ->first();

            $html = $renderLeaseContract->render($body, $lease);
        }

        return $buildDocumentPdf->handle('Pré-visualização', $html)->stream('pre-visualizacao.pdf');
    }

    /**
     * Make the given template the one used by default to generate documents of its kind.
     */
    public function makeDefault(ContractTemplate $contractTemplate): RedirectResponse
    {
        Gate::authorize('update', $contractTemplate);

        DB::transaction(function () use ($contractTemplate): void {
            ContractTemplate::where('account_id', $contractTemplate->account_id)
                ->where('kind', $contractTemplate->kind)
                ->update(['is_default' => false]);

            $contractTemplate->update(['is_default' => true]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Modelo padrão atualizado.')]);

        return back();
    }

    /**
     * Remove the given template, keeping at least one of each kind and promoting another one to default.
     */
    public function destroy(ContractTemplate $contractTemplate): RedirectResponse
    {
        Gate::authorize('delete', $contractTemplate);

        $others = ContractTemplate::where('account_id', $contractTemplate->account_id)
            ->where('kind', $contractTemplate->kind)
            ->whereKeyNot($contractTemplate->id);

        if (! $others->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Mantenha pelo menos um modelo de cada tipo.')]);

            return back();
        }

        DB::transaction(function () use ($contractTemplate, $others): void {
            $contractTemplate->delete();

            if ($contractTemplate->is_default) {
                $others->oldest('id')->first()?->update(['is_default' => true]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Modelo removido com sucesso.')]);

        return to_route('contract-templates.index');
    }
}
