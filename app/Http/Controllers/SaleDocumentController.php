<?php

namespace App\Http\Controllers;

use App\Actions\ContractTemplates\EnsureDefaultContractTemplate;
use App\Actions\ContractTemplates\RenderSaleDocument;
use App\Enums\ContractTemplateKind;
use App\Http\Requests\SaleDocumentRequest;
use App\Models\ContractTemplate;
use App\Models\Sale;
use App\Models\SaleDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Open the attached document in the browser, e.g. a PDF in a new tab.
     */
    public function show(SaleDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        abort_unless(Storage::disk(SaleDocument::DISK)->exists($document->path), 404);

        return Storage::disk(SaleDocument::DISK)->response($document->path, $document->name);
    }

    /**
     * Download the attached document with its original name.
     */
    public function download(SaleDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        abort_unless(Storage::disk(SaleDocument::DISK)->exists($document->path), 404);

        return Storage::disk(SaleDocument::DISK)->download($document->path, $document->name);
    }

    /**
     * Attach a document to the given sale, such as the signed contract or the deed.
     */
    public function store(SaleDocumentRequest $request, Sale $sale): RedirectResponse
    {
        Gate::authorize('update', $sale);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $sale->documents()->create([
            'account_id' => $sale->account_id,
            'type' => $request->validated('type'),
            'name' => $file->getClientOriginalName(),
            'path' => $file->store("sales/{$sale->id}", SaleDocument::DISK),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);

        return to_route('sales.index', ['month' => $sale->sold_on->format('Y-m'), 'show' => $sale->id]);
    }

    /**
     * Remove the given document and its file.
     */
    public function destroy(SaleDocument $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documento removido.')]);

        return to_route('sales.index', ['month' => $document->sale->sold_on->format('Y-m'), 'show' => $document->sale_id]);
    }
}
