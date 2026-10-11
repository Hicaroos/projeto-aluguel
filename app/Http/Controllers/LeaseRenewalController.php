<?php

namespace App\Http\Controllers;

use App\Actions\ContractTemplates\BuildDocumentPdf;
use App\Actions\Leases\RenderLeaseRenewalAmendment;
use App\Actions\Leases\RenewLease;
use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Requests\LeaseRenewalRequest;
use App\Models\Lease;
use App\Models\LeaseRenewal;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LeaseRenewalController extends Controller
{
    use ResolvesSelectedRecord;

    /**
     * Extend the term of the given active lease.
     */
    public function store(LeaseRenewalRequest $request, Lease $lease, RenewLease $renewLease): RedirectResponse
    {
        Gate::authorize('renew', $lease);

        $renewal = $renewLease->handle(
            $lease,
            CarbonImmutable::parse($request->validated('new_end_date')),
            $request->validated('notes'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Contrato renovado até :date.', ['date' => $renewal->new_end_date->format('d/m/Y')]),
        ]);

        return $this->backWithSelectedRecord($lease->id);
    }

    /**
     * Generate the amendment extending the lease term, to be signed by the parties.
     */
    public function amendment(Lease $lease, LeaseRenewal $renewal, RenderLeaseRenewalAmendment $renderAmendment, BuildDocumentPdf $buildDocumentPdf): Response
    {
        Gate::authorize('view', $lease);

        $lease->loadMissing('tenant');

        return $buildDocumentPdf
            ->handle("Termo aditivo de prorrogação — {$lease->tenant->name}", $renderAmendment->handle($renewal->setRelation('lease', $lease)))
            ->stream('aditivo-prorrogacao-'.Str::slug($lease->tenant->name).'-'.$renewal->new_end_date->format('Y-m-d').'.pdf');
    }
}
