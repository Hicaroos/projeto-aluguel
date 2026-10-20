<?php

namespace App\Http\Controllers;

use App\Enums\LeadInterest;
use App\Enums\LeadSource;
use App\Http\Controllers\Concerns\ListsSalesOptions;
use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Controllers\Concerns\SortsTable;
use App\Http\Requests\LeadRequest;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use SortDirection;

class LeadController extends Controller
{
    use ListsSalesOptions, ResolvesSelectedRecord, SortsTable;

    /**
     * Display the people looking to buy or rent a property.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $interest = $request->enum('interest', LeadInterest::class);
        $source = $request->enum('source', LeadSource::class);

        $leads = fn (): Builder => Lead::query()
            ->withCount('deals')
            ->with([
                'user:id,name',
                'deals' => fn ($query) => $query->latest('id'),
                'deals.stage:id,name,outcome',
                'deals.property:id,type,street,number,neighborhood,deleted_at',
                'showcaseRequests' => fn ($query) => $query->latest('id'),
            ]);

        $list = $leads()
            ->search($search)
            ->when($interest !== null, fn (Builder $query) => $query->where('interest', $interest))
            ->when($source !== null, fn (Builder $query) => $query->where('source', $source));

        $sorting = $this->applySort($list, $request, [
            'name' => fn (Builder $query, SortDirection $direction) => $query->orderBy('name', $direction),
            'created_at' => fn (Builder $query, SortDirection $direction) => $query->orderBy('created_at', $direction),
        ], default: 'created_at', defaultDirection: SortDirection::Descending);

        return Inertia::render('leads/Index', [
            'leads' => $list
                ->paginate(10)
                ->appends($this->queryWithoutSelection($request)),
            'selected' => $this->resolveSelectedRecord($request, $leads()),
            'filters' => [
                'search' => $search,
                'interest' => $interest?->value,
                'source' => $source?->value,
                ...$sorting,
            ],
            'users' => $this->teamMemberOptions($request),
            'properties' => $this->dealPropertyOptions(),
        ]);
    }

    /**
     * Store a newly created lead in the branch the user is looking at, in charge of the user
     * who registered it unless someone else was chosen.
     */
    public function store(LeadRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $accessibleBranchIds = $user->accessibleBranchIds();

        Lead::create([
            ...$request->validated(),
            'account_id' => $user->account_id,
            'branch_id' => $user->selectedBranchId() ?? (count($accessibleBranchIds ?? []) === 1 ? $accessibleBranchIds[0] : null),
            'user_id' => $request->validated('user_id') ?? ($user->account?->isAgency() ? $user->id : null),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Interessado cadastrado com sucesso.')]);

        return to_route('leads.index');
    }

    /**
     * Update the given lead.
     */
    public function update(LeadRequest $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $lead->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Interessado atualizado com sucesso.')]);

        return to_route('leads.index');
    }

    /**
     * Remove the given lead together with their deals, visits and proposals.
     */
    public function destroy(Lead $lead): RedirectResponse
    {
        Gate::authorize('delete', $lead);

        $lead->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Interessado removido com sucesso.')]);

        return to_route('leads.index');
    }
}
