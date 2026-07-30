<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\PropertyRequest;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $properties = Property::where('account_id', $request->user()->account_id)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('street', 'like', "%{$search}%")
                    ->orWhere('neighborhood', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('zip_code', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('properties/Index', [
            'properties' => $properties,
            'filters' => ['search' => $search],
            ...$this->formProps($request),
        ]);
    }

    /**
     * Store a newly created property.
     */
    public function store(PropertyRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $ownerId = $user->account->type === AccountType::Agency
            ? $validated['owner_id']
            : $user->account->owners()->first()?->id;

        abort_if($ownerId === null, 422, 'Nenhum proprietário encontrado para esta conta.');

        Property::create([
            ...$validated,
            'account_id' => $user->account_id,
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
        $this->ensureSameAccount($property, $request);

        $user = $request->user();
        $validated = $request->validated();

        $ownerId = $user->account->type === AccountType::Agency
            ? $validated['owner_id']
            : $property->owner_id;

        $property->update([
            ...$validated,
            'owner_id' => $ownerId,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Imóvel atualizado com sucesso.')]);

        return to_route('properties.index');
    }

    /**
     * Remove the given property.
     */
    public function destroy(Request $request, Property $property): RedirectResponse
    {
        $this->ensureSameAccount($property, $request);

        $property->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Imóvel removido com sucesso.')]);

        return to_route('properties.index');
    }

    /**
     * Get the shared props for the property form modal.
     *
     * @return array<string, mixed>
     */
    private function formProps(Request $request): array
    {
        $account = $request->user()->account;

        return [
            'accountType' => $account?->type->value,
            'owners' => $account?->type === AccountType::Agency
                ? $account->owners()->get(['id', 'name'])
                : [],
        ];
    }

    /**
     * Ensure the given property belongs to the authenticated user's account.
     */
    private function ensureSameAccount(Property $property, Request $request): void
    {
        abort_unless($property->account_id === $request->user()->account_id, 404);
    }
}
