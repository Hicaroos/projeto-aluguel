<?php

namespace App\Http\Controllers;

use App\Http\Requests\PropertyRequest;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    /**
     * Display the authenticated account's properties.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $account = $request->user()->account;

        return Inertia::render('properties/Index', [
            'properties' => Property::where('account_id', $request->user()->account_id)
                ->search($search)
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'filters' => ['search' => $search],
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
