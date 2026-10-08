<?php

namespace App\Http\Controllers;

use App\Actions\Leases\CreateLease;
use App\Actions\Leases\DeleteLease;
use App\Actions\Leases\FinishLease;
use App\Actions\Leases\UpdateLease;
use App\Enums\LeaseStatus;
use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Controllers\Concerns\SortsTable;
use App\Http\Requests\LeaseRequest;
use App\Models\ContractTemplate;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use SortDirection;

class LeaseController extends Controller
{
    use ResolvesSelectedRecord, SortsTable;

    /**
     * Display the authenticated account's leases, sorted by tenant name by default.
     */
    public function index(Request $request): Response
    {
        $accountId = $request->user()->account_id;
        $search = $request->string('search')->trim()->toString();
        $status = $request->enum('status', LeaseStatus::class);
        $leases = fn (): Builder => Lease::where('account_id', $accountId)
            ->with([
                'property:id,type,street,number,complement,neighborhood,city,state,rent_amount,status,deleted_at',
                'tenant:id,name,email,phone,deleted_at',
                'guarantor',
                'adjustments',
                'documents',
                'openPayments' => fn ($query) => $query
                    ->select(['id', 'lease_id', 'type', 'description', 'reference_month', 'due_date', 'amount', 'status'])
                    ->withSum('receipts as received_amount', 'amount'),
            ]);
        $withAdjustmentInfo = fn (?Lease $lease): ?Lease => $lease?->append(['next_adjustment_date', 'adjustment_status']);

        $list = $leases()
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->search($search);

        $sorting = $this->applySort($list, $request, [
            'tenant' => fn (Builder $query, SortDirection $direction) => $query->orderBy(
                Tenant::withTrashed()->select('name')->whereColumn('tenants.id', 'leases.tenant_id'),
                $direction,
            ),
            'start_date' => fn (Builder $query, SortDirection $direction) => $query->orderBy('start_date', $direction),
            'amount' => fn (Builder $query, SortDirection $direction) => $query->orderBy('amount', $direction),
            'status' => fn (Builder $query, SortDirection $direction) => $query->orderBy(new Expression("CASE status WHEN 'active' THEN 0 WHEN 'ended' THEN 1 ELSE 2 END"), $direction),
        ], default: 'tenant');

        return Inertia::render('leases/Index', [
            'selected' => $withAdjustmentInfo($this->resolveSelectedRecord($request, $leases())),
            'leases' => $list
                ->paginate(10)
                ->appends($this->queryWithoutSelection($request))
                ->through($withAdjustmentInfo),
            'filters' => [
                'search' => $search,
                'status' => $status?->value,
                ...$sorting,
            ],
            'properties' => Property::where('account_id', $accountId)
                ->orderBy('street')
                ->get(['id', 'street', 'number', 'complement', 'neighborhood', 'city', 'state', 'rent_amount', 'status']),
            'tenants' => Tenant::where('account_id', $accountId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'contractTemplates' => ContractTemplate::where('account_id', $accountId)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'is_default']),
        ]);
    }

    /**
     * Store a newly created lease.
     */
    public function store(LeaseRequest $request, CreateLease $createLease): RedirectResponse
    {
        $createLease->handle($request->user()->account_id, $request->leaseAttributes(), $request->guarantorAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contrato cadastrado com sucesso.')]);

        return to_route('leases.index');
    }

    /**
     * Update the given active lease.
     */
    public function update(LeaseRequest $request, Lease $lease, UpdateLease $updateLease): RedirectResponse
    {
        Gate::authorize('update', $lease);

        $updateLease->handle($lease, $request->leaseAttributes(), $request->guarantorAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contrato atualizado com sucesso.')]);

        return to_route('leases.index');
    }

    /**
     * End or terminate the given active lease.
     */
    public function finish(Request $request, Lease $lease, FinishLease $finishLease): RedirectResponse
    {
        Gate::authorize('finish', $lease);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(LeaseStatus::class)->except([LeaseStatus::Active])],
        ]);

        $finishLease->handle($lease, LeaseStatus::from($validated['status']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contrato finalizado com sucesso.')]);

        return to_route('leases.index');
    }

    /**
     * Remove the given lease.
     */
    public function destroy(Lease $lease, DeleteLease $deleteLease): RedirectResponse
    {
        Gate::authorize('delete', $lease);

        $deleteLease->handle($lease);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contrato removido com sucesso.')]);

        return to_route('leases.index');
    }
}
