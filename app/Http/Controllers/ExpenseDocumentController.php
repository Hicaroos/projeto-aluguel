<?php

namespace App\Http\Controllers;

use App\Actions\ContractTemplates\EnsureDefaultContractTemplate;
use App\Actions\ContractTemplates\RenderExpenseDocument;
use App\Enums\ContractTemplateKind;
use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Requests\ExpenseDocumentRequest;
use App\Models\ContractTemplate;
use App\Models\Expense;
use App\Models\ExpenseDocument;
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

class ExpenseDocumentController extends Controller
{
    use ResolvesSelectedRecord;

    /**
     * Generate the receipt the service provider signs for the job, as a PDF, from the chosen template
     * or from the account default.
     */
    public function receipt(
        Request $request,
        Expense $expense,
        EnsureDefaultContractTemplate $ensureDefaultContractTemplate,
        RenderExpenseDocument $renderExpenseDocument,
    ): Response {
        abort_unless($expense->type->isService(), 404);

        $kind = ContractTemplateKind::ServiceReceipt;

        $template = $request->filled('template')
            ? ContractTemplate::where('kind', $kind)->whereKey($request->integer('template'))->firstOrFail()
            : $ensureDefaultContractTemplate->handle($expense->account, $kind);

        $provider = $expense->provider_name ?? $expense->type->label();

        return Pdf::loadView('pdf.lease-contract', [
            'title' => "{$kind->label()} — {$provider}",
            'body' => $renderExpenseDocument->handle($template, $expense),
        ])
            ->setPaper('a4')
            ->stream('recibo-de-servico-'.Str::slug($provider).'.pdf');
    }

    /**
     * Open the attached document in the browser, e.g. a PDF in a new tab.
     */
    public function show(ExpenseDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        abort_unless(Storage::disk(ExpenseDocument::DISK)->exists($document->path), 404);

        return Storage::disk(ExpenseDocument::DISK)->response($document->path, $document->name);
    }

    /**
     * Download the attached document with its original name.
     */
    public function download(ExpenseDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        abort_unless(Storage::disk(ExpenseDocument::DISK)->exists($document->path), 404);

        return Storage::disk(ExpenseDocument::DISK)->download($document->path, $document->name);
    }

    /**
     * Attach a document to the given expense, such as the receipt the provider signed or the invoice.
     */
    public function store(ExpenseDocumentRequest $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('update', $expense);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $expense->documents()->create([
            'account_id' => $expense->account_id,
            'type' => $request->validated('type'),
            'name' => $file->getClientOriginalName(),
            'path' => $file->store("expenses/{$expense->id}", ExpenseDocument::DISK),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);

        return $this->backWithSelectedRecord($expense->id);
    }

    /**
     * Remove the given document and its file.
     */
    public function destroy(ExpenseDocument $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documento removido.')]);

        return $this->backWithSelectedRecord($document->expense_id);
    }
}
