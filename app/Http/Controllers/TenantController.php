<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    /**
     * Display the authenticated account's tenants.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('tenants/Index', [
            'tenants' => Tenant::where('account_id', $request->user()->account_id)
                ->search($search)
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(TenantRequest $request): RedirectResponse
    {
        Tenant::create([
            ...$request->validated(),
            'account_id' => $request->user()->account_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inquilino cadastrado com sucesso.')]);

        return to_route('tenants.index');
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
     * Remove the given tenant, unless they have an active lease.
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
        Gate::authorize('delete', $tenant);

        if ($tenant->hasActiveLease()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Este inquilino possui um contrato ativo e não pode ser removido.')]);

            return back();
        }

        $tenant->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inquilino removido com sucesso.')]);

        return to_route('tenants.index');
    }
}
