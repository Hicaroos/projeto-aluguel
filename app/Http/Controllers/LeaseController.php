<?php

namespace App\Http\Controllers;

use App\Actions\Leases\CreateLease;
use App\Actions\Leases\DeleteLease;
use App\Actions\Leases\FinishLease;
use App\Actions\Leases\UpdateLease;
use App\Actions\Messages\ComposeWhatsAppMessage;
use App\Enums\ContractTemplateKind;
use App\Enums\LeaseStatus;
use App\Enums\PayoutRule;
use App\Enums\Permission;
use App\Enums\PropertyStatus;
use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Controllers\Concerns\SortsTable;
use App\Http\Requests\LeaseRequest;
use App\Models\ContractTemplate;
use App\Models\Deal;
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
    public function index(Request $request, ComposeWhatsAppMessage $composeWhatsAppMessage): Response
    {
        $accountId = $request->user()->account_id;
        $search = $request->string('search')->trim()->toString();
        $status = $request->enum('status', LeaseStatus::class);
        $leases = fn (): Builder => Lease::where('account_id', $accountId)->withDetails();
        $withAdjustmentInfo = function (?Lease $lease) use ($composeWhatsAppMessage): ?Lease {
            if ($lease !== null) {
                $composeWhatsAppMessage->attachToLatestAdjustment($lease);
            }

            return $lease?->withAdjustmentInfo();
        };
        $showsPayoutRules = ($request->user()->account?->isAgency() ?? false) && $request->user()->can(Permission::ManageFinance->value);

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
                ->where(fn (Builder $query) => $query->where('for_rent', true)->orWhere('status', PropertyStatus::Rented))
                ->orderBy('street')
                ->when($showsPayoutRules, fn (Builder $query) => $query->with('owner:id,'.implode(',', PayoutRule::columns())))
                ->get(['id', 'owner_id', 'street', 'number', 'complement', 'neighborhood', 'city', 'state', 'rent_amount', 'status']),
            'tenants' => Tenant::where('account_id', $accountId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'contractTemplates' => ContractTemplate::where('account_id', $accountId)
                ->where('kind', ContractTemplateKind::Lease)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'is_default']),
            'payoutDefaults' => $showsPayoutRules ? $request->user()->account->only(PayoutRule::columns()) : null,
            'fromDeal' => $this->fromDeal($request),
        ]);
    }

    /**
     * Get what a new lease starts with when it comes from a rent deal closed in the funnel: its property,
     * the agreed rent and the tenant the lead became.
     *
     * @return array{deal_id: int, property_id: int|null, tenant_id: int|null, amount: string|null}|null
     */
    private function fromDeal(Request $request): ?array
    {
        $deal = Deal::findForLease($request->query('negocio'));

        if ($deal === null) {
            return null;
        }

        $tenantId = Tenant::query()
            ->where('account_id', $deal->account_id)
            ->whereKey($request->integer('inquilino'))
            ->value('id') ?? $deal->leadTenant()?->id;

        return [
            'deal_id' => $deal->id,
            'property_id' => $deal->property_id,
            'tenant_id' => $tenantId,
            'amount' => $deal->value ?? $deal->property?->rent_amount,
        ];
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
