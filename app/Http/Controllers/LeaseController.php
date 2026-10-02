<?php

namespace App\Http\Controllers;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Http\Requests\LeaseRequest;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaseController extends Controller
{
    /**
     * Display the authenticated account's leases.
     */
    public function index(Request $request): Response
    {
        $accountId = $request->user()->account_id;
        $search = $request->string('search')->trim()->toString();
        $status = $request->enum('status', LeaseStatus::class);

        $leases = Lease::where('account_id', $accountId)
            ->with([
                'property:id,type,street,number,complement,neighborhood,city,state,rent_amount,status,deleted_at',
                'tenant:id,name,email,phone,deleted_at',
            ])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->whereHas('tenant', fn ($query) => $query->withTrashed()->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('property', fn ($query) => $query->withTrashed()->where(function ($query) use ($search) {
                        $query->where('street', 'like', "%{$search}%")
                            ->orWhere('neighborhood', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%");
                    }));
            }))
            ->orderByRaw('status = ? desc', [LeaseStatus::Active->value])
            ->latest('start_date')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('leases/Index', [
            'leases' => $leases,
            'filters' => ['search' => $search, 'status' => $status?->value],
            'properties' => Property::where('account_id', $accountId)
                ->orderBy('street')
                ->get(['id', 'street', 'number', 'complement', 'neighborhood', 'city', 'state', 'rent_amount', 'status']),
            'tenants' => Tenant::where('account_id', $accountId)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created lease and mark its property as rented.
     */
    public function store(LeaseRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $lease = Lease::create([
                ...$request->validated(),
                'account_id' => $request->user()->account_id,
                'status' => LeaseStatus::Active,
            ]);

            $lease->property->update(['status' => PropertyStatus::Rented]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contrato cadastrado com sucesso.')]);

        return to_route('leases.index');
    }

    /**
     * Update the given active lease, moving the rented status if the property changed.
     */
    public function update(LeaseRequest $request, Lease $lease): RedirectResponse
    {
        $this->ensureSameAccount($lease, $request);

        abort_unless($lease->isActive(), 403);

        DB::transaction(function () use ($request, $lease): void {
            $previousProperty = $lease->property;

            $lease->update([
                ...$request->validated(),
                'deposit_amount' => $request->validated('deposit_amount'),
            ]);

            if ($previousProperty->id !== $lease->property_id) {
                $previousProperty->update(['status' => PropertyStatus::Available]);
                $lease->load('property')->property->update(['status' => PropertyStatus::Rented]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contrato atualizado com sucesso.')]);

        return to_route('leases.index');
    }

    /**
     * End or terminate the given active lease and free its property.
     */
    public function finish(Request $request, Lease $lease): RedirectResponse
    {
        $this->ensureSameAccount($lease, $request);

        abort_unless($lease->isActive(), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(LeaseStatus::class)->except([LeaseStatus::Active])],
        ]);

        DB::transaction(function () use ($lease, $validated): void {
            $lease->update(['status' => $validated['status']]);
            $lease->property->update(['status' => PropertyStatus::Available]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contrato finalizado com sucesso.')]);

        return to_route('leases.index');
    }

    /**
     * Remove the given lease, freeing its property when it was active.
     */
    public function destroy(Request $request, Lease $lease): RedirectResponse
    {
        $this->ensureSameAccount($lease, $request);

        DB::transaction(function () use ($lease): void {
            if ($lease->isActive()) {
                $lease->property->update(['status' => PropertyStatus::Available]);
            }

            $lease->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contrato removido com sucesso.')]);

        return to_route('leases.index');
    }

    /**
     * Ensure the given lease belongs to the authenticated user's account.
     */
    private function ensureSameAccount(Lease $lease, Request $request): void
    {
        abort_unless($lease->account_id === $request->user()->account_id, 404);
    }
}
