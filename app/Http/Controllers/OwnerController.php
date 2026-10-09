<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LinksToUserBranches;
use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Controllers\Concerns\SortsTable;
use App\Http\Requests\OwnerRequest;
use App\Models\Owner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use SortDirection;

class OwnerController extends Controller
{
    use LinksToUserBranches, ResolvesSelectedRecord, SortsTable;

    /**
     * Display the owners whose properties the agency manages. Single owners keep their own
     * details in the settings instead.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->account?->isAgency() ?? false, 404);

        $search = $request->string('search')->trim()->toString();
        $owners = fn (): Builder => Owner::query()
            ->withCount('properties')
            ->with([
                'properties' => fn ($query) => $query
                    ->select(['id', 'owner_id', 'branch_id', 'type', 'street', 'number', 'neighborhood', 'city', 'state', 'rent_amount', 'status'])
                    ->orderBy('street'),
                'properties.branch:id,name',
            ]);

        $list = $owners()->search($search);

        $sorting = $this->applySort($list, $request, [
            'name' => fn (Builder $query, SortDirection $direction) => $query->orderBy('name', $direction),
            'cpf_cnpj' => fn (Builder $query, SortDirection $direction) => $query->orderBy('cpf_cnpj', $direction),
            'properties_count' => fn (Builder $query, SortDirection $direction) => $query->orderBy('properties_count', $direction),
        ], default: 'name');

        return Inertia::render('owners/Index', [
            'owners' => $list
                ->paginate(10)
                ->appends($this->queryWithoutSelection($request)),
            'selected' => $this->resolveSelectedRecord($request, $owners()),
            'filters' => ['search' => $search, ...$sorting],
        ]);
    }

    /**
     * Store a newly created owner.
     */
    public function store(OwnerRequest $request): RedirectResponse
    {
        $owner = Owner::create([
            ...$request->validated(),
            'account_id' => $request->user()->account_id,
        ]);

        $this->linkToUserBranches($request->user(), $owner);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Proprietário cadastrado com sucesso.')]);

        return to_route('owners.index');
    }

    /**
     * Bring an owner registered by another branch to the branches of the user, found by their document.
     */
    public function link(Request $request): RedirectResponse
    {
        $document = preg_replace('/\D/', '', $request->string('cpf_cnpj')->toString());

        $owner = Owner::withoutGlobalScope(Owner::BRANCH_SCOPE)
            ->where('cpf_cnpj', $document)
            ->firstOrFail();

        $this->linkToUserBranches($request->user(), $owner);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Proprietário adicionado à sua unidade.')]);

        return to_route('owners.index', ['show' => $owner->id]);
    }

    /**
     * Update the given owner.
     */
    public function update(OwnerRequest $request, Owner $owner): RedirectResponse
    {
        Gate::authorize('update', $owner);

        $owner->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Proprietário atualizado com sucesso.')]);

        return to_route('owners.index');
    }

    /**
     * Remove the given owner, unless the agency still manages properties of theirs.
     */
    public function destroy(Owner $owner): RedirectResponse
    {
        Gate::authorize('delete', $owner);

        if ($owner->properties()->withoutGlobalScopes()->whereNull('deleted_at')->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Este proprietário possui imóveis cadastrados e não pode ser removido.')]);

            return back();
        }

        $owner->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Proprietário removido com sucesso.')]);

        return to_route('owners.index');
    }
}
