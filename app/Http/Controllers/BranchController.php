<?php

namespace App\Http\Controllers;

use App\Enums\LeaseStatus;
use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Models\Lease;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    /**
     * Display the agency branches, with how much each one manages whatever branch is selected.
     */
    public function index(): Response
    {
        return Inertia::render('branches/Index', [
            'branches' => Branch::query()
                ->withCount([
                    'properties' => fn (Builder $query) => $query->withoutGlobalScope(Property::BRANCH_SCOPE),
                    'leases as active_leases_count' => fn (Builder $query) => $query->withoutGlobalScope(Lease::BRANCH_SCOPE)->where('leases.status', LeaseStatus::Active),
                ])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Store a new branch.
     */
    public function store(BranchRequest $request): RedirectResponse
    {
        Branch::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unidade cadastrada com sucesso.')]);

        return to_route('branches.index');
    }

    /**
     * Update the given branch.
     */
    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        Gate::authorize('update', $branch);

        $branch->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unidade atualizada com sucesso.')]);

        return to_route('branches.index');
    }

    /**
     * Stop using the given branch, or use it again. Its properties and history stay where they are,
     * but no new property can be placed in an inactive branch.
     */
    public function toggleStatus(Branch $branch): RedirectResponse
    {
        Gate::authorize('update', $branch);

        if ($branch->is_active && ! Branch::active()->whereKeyNot($branch->id)->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('A imobiliária precisa de pelo menos uma unidade ativa.')]);

            return back();
        }

        $branch->update(['is_active' => ! $branch->is_active]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $branch->is_active
            ? __('Unidade reativada.')
            : __('Unidade desativada. Os imóveis e o histórico dela continuam disponíveis.')]);

        return to_route('branches.index');
    }

    /**
     * Remove the given branch, as long as it never had properties.
     */
    public function destroy(Branch $branch): RedirectResponse
    {
        $response = Gate::inspect('delete', $branch);

        if ($response->denied() && $response->status() === 404) {
            abort(404);
        }

        if ($response->denied()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $response->message()]);

            return back();
        }

        if (! Branch::whereKeyNot($branch->id)->active()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('A imobiliária precisa de pelo menos uma unidade ativa.')]);

            return back();
        }

        $branch->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unidade excluída com sucesso.')]);

        return to_route('branches.index');
    }
}
