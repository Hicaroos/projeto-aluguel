<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $tenants = Tenant::where('account_id', $request->user()->account_id)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('cpf_cnpj', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('tenants/Index', [
            'tenants' => $tenants,
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
        $this->ensureSameAccount($tenant, $request);

        $tenant->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inquilino atualizado com sucesso.')]);

        return to_route('tenants.index');
    }

    /**
     * Remove the given tenant.
     */
    public function destroy(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->ensureSameAccount($tenant, $request);

        if ($tenant->leases()->active()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Este inquilino possui um contrato ativo e não pode ser removido.')]);

            return back();
        }

        $tenant->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inquilino removido com sucesso.')]);

        return to_route('tenants.index');
    }

    /**
     * Ensure the given tenant belongs to the authenticated user's account.
     */
    private function ensureSameAccount(Tenant $tenant, Request $request): void
    {
        abort_unless($tenant->account_id === $request->user()->account_id, 404);
    }
}
