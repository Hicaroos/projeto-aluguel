<?php

namespace App\Http\Controllers;

use App\Actions\ContractTemplates\EnsureDefaultContractTemplate;
use App\Actions\ContractTemplates\RenderLeaseContract;
use App\Actions\ContractTemplates\SanitizeContractTemplate;
use App\Enums\ContractVariable;
use App\Enums\LeaseStatus;
use App\Http\Requests\ContractTemplateRequest;
use App\Models\ContractTemplate;
use App\Models\Lease;
use Barryvdh\DomPDF\Facade\Pdf;
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
     * Display the account's contract templates, creating the standard one on the first visit.
     */
    public function index(Request $request, EnsureDefaultContractTemplate $ensureDefaultContractTemplate): Response
    {
        $account = $request->user()->account;

        abort_if($account === null, 404);

        $ensureDefaultContractTemplate->handle($account);

        return Inertia::render('contract-templates/Index', [
            'templates' => $account->contractTemplates()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'is_default', 'updated_at']),
        ]);
    }

    /**
     * Show the editor for a new template.
     */
    public function create(): Response
    {
        return Inertia::render('contract-templates/Edit', [
            'template' => null,
            'variables' => ContractVariable::catalog(),
        ]);
    }

    /**
     * Store a new template. The first template of the account becomes its default.
     */
    public function store(ContractTemplateRequest $request, SanitizeContractTemplate $sanitizeContractTemplate): RedirectResponse
    {
        $accountId = $request->user()->account_id;

        ContractTemplate::create([
            ...$request->templateAttributes($sanitizeContractTemplate),
            'account_id' => $accountId,
            'is_default' => ! ContractTemplate::where('account_id', $accountId)->exists(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Modelo de contrato cadastrado com sucesso.')]);

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
            'variables' => ContractVariable::catalog(),
        ]);
    }

    /**
     * Update the given template.
     */
    public function update(ContractTemplateRequest $request, ContractTemplate $contractTemplate, SanitizeContractTemplate $sanitizeContractTemplate): RedirectResponse
    {
        Gate::authorize('update', $contractTemplate);

        $contractTemplate->update($request->templateAttributes($sanitizeContractTemplate));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Modelo de contrato atualizado com sucesso.')]);

        return to_route('contract-templates.index');
    }

    /**
     * Preview a template body as PDF before saving it, filled with the account's most recent lease,
     * or with the variable labels when there is no lease yet.
     */
    public function preview(
        Request $request,
        SanitizeContractTemplate $sanitizeContractTemplate,
        RenderLeaseContract $renderLeaseContract,
    ): SymfonyResponse {
        $validated = $request->validate(['body' => ContractTemplateRequest::bodyRules()]);

        $lease = Lease::where('account_id', $request->user()->account_id)
            ->orderByRaw('status = ? desc', [LeaseStatus::Active->value])
            ->latest('start_date')
            ->first();

        return Pdf::loadView('pdf.lease-contract', [
            'title' => 'Pré-visualização do contrato',
            'body' => $renderLeaseContract->render($sanitizeContractTemplate->handle($validated['body']), $lease),
        ])
            ->setPaper('a4')
            ->stream('pre-visualizacao-contrato.pdf');
    }

    /**
     * Make the given template the one used by default to generate contracts.
     */
    public function makeDefault(ContractTemplate $contractTemplate): RedirectResponse
    {
        Gate::authorize('update', $contractTemplate);

        DB::transaction(function () use ($contractTemplate): void {
            ContractTemplate::where('account_id', $contractTemplate->account_id)->update(['is_default' => false]);
            $contractTemplate->update(['is_default' => true]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Modelo padrão atualizado.')]);

        return back();
    }

    /**
     * Remove the given template, keeping at least one per account and promoting another one to default.
     */
    public function destroy(ContractTemplate $contractTemplate): RedirectResponse
    {
        Gate::authorize('delete', $contractTemplate);

        $others = ContractTemplate::where('account_id', $contractTemplate->account_id)->whereKeyNot($contractTemplate->id);

        if (! $others->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Mantenha pelo menos um modelo de contrato.')]);

            return back();
        }

        DB::transaction(function () use ($contractTemplate, $others): void {
            $contractTemplate->delete();

            if ($contractTemplate->is_default) {
                $others->oldest('id')->first()?->update(['is_default' => true]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Modelo de contrato removido com sucesso.')]);

        return to_route('contract-templates.index');
    }
}
