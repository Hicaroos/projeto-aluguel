<?php

namespace App\Http\Controllers;

use App\Actions\ContractTemplates\EnsureDefaultContractTemplate;
use App\Actions\ContractTemplates\RenderLeaseContract;
use App\Models\ContractTemplate;
use App\Models\Lease;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LeaseContractController extends Controller
{
    /**
     * Generate the lease contract as a PDF from the chosen template, or from the account default.
     */
    public function show(
        Request $request,
        Lease $lease,
        EnsureDefaultContractTemplate $ensureDefaultContractTemplate,
        RenderLeaseContract $renderLeaseContract,
    ): Response {
        Gate::authorize('view', $lease);

        $template = $request->filled('template')
            ? ContractTemplate::whereKey($request->integer('template'))->firstOrFail()
            : $ensureDefaultContractTemplate->handle($lease->account);

        Gate::authorize('view', $template);

        $lease->loadMissing('tenant');

        return Pdf::loadView('pdf.lease-contract', [
            'title' => "Contrato de locação — {$lease->tenant->name}",
            'body' => $renderLeaseContract->handle($template, $lease),
        ])
            ->setPaper('a4')
            ->stream('contrato-'.Str::slug($lease->tenant->name).'.pdf');
    }
}
