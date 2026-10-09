<?php

namespace App\Http\Controllers;

use App\Enums\LeaseStatus;
use App\Http\Controllers\Concerns\LinksToUserBranches;
use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Controllers\Concerns\SortsTable;
use App\Http\Requests\TenantRequest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use SortDirection;

class TenantController extends Controller
{
    use LinksToUserBranches, ResolvesSelectedRecord, SortsTable;

    /**
     * Display the authenticated account's tenants.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $tenants = fn (): Builder => Tenant::where('account_id', $request->user()->account_id)
            ->with([
                'leases' => fn ($query) => $query
                    ->select(['id', 'tenant_id', 'property_id', 'start_date', 'end_date', 'amount', 'status'])
                    ->orderByRaw('status = ? desc', [LeaseStatus::Active->value])
                    ->latest('start_date'),
                'leases.property:id,type,street,number,neighborhood,deleted_at',
            ]);

        $list = $tenants()->search($search);

        $sorting = $this->applySort($list, $request, [
            'name' => fn (Builder $query, SortDirection $direction) => $query->orderBy('name', $direction),
            'cpf_cnpj' => fn (Builder $query, SortDirection $direction) => $query->orderBy('cpf_cnpj', $direction),
            'created_at' => fn (Builder $query, SortDirection $direction) => $query->orderBy('created_at', $direction),
        ], default: 'name');

        return Inertia::render('tenants/Index', [
            'tenants' => $list
                ->paginate(10)
                ->appends($this->queryWithoutSelection($request)),
            'selected' => $this->resolveSelectedRecord($request, $tenants()),
            'filters' => ['search' => $search, ...$sorting],
        ]);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(TenantRequest $request): RedirectResponse
    {
        $tenant = Tenant::create([
            ...$request->validated(),
            'account_id' => $request->user()->account_id,
        ]);

        $this->linkToUserBranches($request->user(), $tenant);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inquilino cadastrado com sucesso.')]);

        return to_route('tenants.index');
    }

    /**
     * Bring a tenant registered by another branch to the branches of the user, found by their document.
     */
    public function link(Request $request): RedirectResponse
    {
        $document = preg_replace('/\D/', '', $request->string('cpf_cnpj')->toString());

        $tenant = Tenant::withoutGlobalScope(Tenant::BRANCH_SCOPE)
            ->where('cpf_cnpj', $document)
            ->firstOrFail();

        $this->linkToUserBranches($request->user(), $tenant);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inquilino adicionado à sua unidade.')]);

        return to_route('tenants.index', ['show' => $tenant->id]);
    }

    /**
     * Update the given tenant.
     */
    public function update(TenantRequest $request, Tenant $tenant): RedirectResponse
    {
        Gate::authorize('update', $tenant);

        $tenant->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inquilino atualizado com sucesso.')]);

        return to_route('tenants.index');
    }

    /**
     * Remove the given tenant, unless they have an active lease or still owe payments.
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
        Gate::authorize('delete', $tenant);

        if ($tenant->hasActiveLease()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Este inquilino possui um contrato ativo e não pode ser removido.')]);

            return back();
        }

        if ($tenant->hasOpenPayments()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Este inquilino possui cobranças em aberto e não pode ser removido.')]);

            return back();
        }

        $tenant->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inquilino removido com sucesso.')]);

        return to_route('tenants.index');
    }
}
