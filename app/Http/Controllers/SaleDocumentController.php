<?php

namespace App\Http\Controllers;

use App\Actions\ContractTemplates\EnsureDefaultContractTemplate;
use App\Actions\ContractTemplates\RenderSaleDocument;
use App\Enums\ContractTemplateKind;
use App\Models\ContractTemplate;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SaleDocumentController extends Controller
{
    /**
     * Generate a sale document as a PDF, the sale contract or the down payment receipt, from the
     * chosen template or from the account default of that kind.
     */
    public function generate(
        Request $request,
        Sale $sale,
        string $kind,
        EnsureDefaultContractTemplate $ensureDefaultContractTemplate,
        RenderSaleDocument $renderSaleDocument,
    ): Response {
        $kind = ContractTemplateKind::tryFrom($kind);

        abort_if($kind === null || ! $kind->isForSales(), 404);

        $template = $request->filled('template')
            ? ContractTemplate::where('kind', $kind)->whereKey($request->integer('template'))->firstOrFail()
            : $ensureDefaultContractTemplate->handle($sale->account, $kind);

        return Pdf::loadView('pdf.lease-contract', [
            'title' => "{$kind->label()} — {$sale->buyer_name}",
            'body' => $renderSaleDocument->handle($template, $sale),
        ])
            ->setPaper('a4')
            ->stream(Str::slug($kind->label()).'-'.Str::slug($sale->buyer_name).'.pdf');
    }
}
