<?php

namespace App\Http\Controllers;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Controllers\Concerns\SortsTable;
use App\Http\Requests\PropertyRequest;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use SortDirection;

class PropertyController extends Controller
{
    use ResolvesSelectedRecord, SortsTable;

    /**
     * Display the authenticated account's properties.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $type = $request->enum('type', PropertyType::class);
        $status = $request->enum('status', PropertyStatus::class);
        $account = $request->user()->account;
        $properties = fn (): Builder => Property::where('account_id', $request->user()->account_id)
            ->with([
                'activeLease:id,property_id,tenant_id,start_date,end_date,amount,due_day,status',
                'activeLease.tenant:id,name,deleted_at',
                'photos:id,property_id,sort_order',
            ]);

        $list = $properties()
            ->search($search)
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status));

        $sorting = $this->applySort($list, $request, [
            'street' => fn (Builder $query, SortDirection $direction) => $query->orderBy('street', $direction)->orderBy('number', $direction),
            'city' => fn (Builder $query, SortDirection $direction) => $query->orderBy('city', $direction)->orderBy('state', $direction),
            'type' => fn (Builder $query, SortDirection $direction) => $query->orderBy(new Expression("CASE type WHEN 'apartment' THEN 0 WHEN 'house' THEN 1 WHEN 'commercial' THEN 2 ELSE 3 END"), $direction),
            'rent_amount' => fn (Builder $query, SortDirection $direction) => $query->orderBy('rent_amount', $direction),
            'status' => fn (Builder $query, SortDirection $direction) => $query->orderBy(new Expression("CASE status WHEN 'available' THEN 0 WHEN 'rented' THEN 1 WHEN 'maintenance' THEN 2 ELSE 3 END"), $direction),
        ], default: 'street');

        return Inertia::render('properties/Index', [
            'properties' => $list
                ->paginate(10)
                ->appends($this->queryWithoutSelection($request)),
            'selected' => $this->resolveSelectedRecord($request, $properties()),
            'filters' => [
                'search' => $search,
                'type' => $type?->value,
                'status' => $status?->value,
                ...$sorting,
            ],
            'accountType' => $account?->type->value,
            'owners' => $account?->isAgency() ? $account->owners()->get(['id', 'name']) : [],
        ]);
    }

    /**
     * Store a newly created property.
     */
    public function store(PropertyRequest $request): RedirectResponse
    {
        $ownerId = $request->ownerId();

        abort_if($ownerId === null, 422, 'Nenhum proprietário encontrado para esta conta.');

        Property::create([
            ...$request->validated(),
            'account_id' => $request->user()->account_id,
            'owner_id' => $ownerId,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Imóvel cadastrado com sucesso.')]);

        return to_route('properties.index');
    }

    /**
     * Update the given property.
     */
    public function update(PropertyRequest $request, Property $property): RedirectResponse
    {
        Gate::authorize('update', $property);

        $property->update([
            ...$request->validated(),
            'owner_id' => $request->ownerId(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Imóvel atualizado com sucesso.')]);

        return to_route('properties.index');
    }

    /**
     * Remove the given property, unless it is under an active lease.
     */
    public function destroy(Property $property): RedirectResponse
    {
        Gate::authorize('delete', $property);

        if ($property->hasActiveLease()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Este imóvel possui um contrato ativo e não pode ser removido.')]);

            return back();
        }

        $property->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Imóvel removido com sucesso.')]);

        return to_route('properties.index');
    }
}
