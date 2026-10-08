<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Requests\LeaseDocumentRequest;
use App\Models\Lease;
use App\Models\LeaseDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaseDocumentController extends Controller
{
    use ResolvesSelectedRecord;

    /**
     * Open the document in the browser, e.g. a PDF in a new tab.
     */
    public function show(LeaseDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        abort_unless(Storage::disk(LeaseDocument::DISK)->exists($document->path), 404);

        return Storage::disk(LeaseDocument::DISK)->response($document->path, $document->name);
    }

    /**
     * Download the document with its original name.
     */
    public function download(LeaseDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        abort_unless(Storage::disk(LeaseDocument::DISK)->exists($document->path), 404);

        return Storage::disk(LeaseDocument::DISK)->download($document->path, $document->name);
    }

    /**
     * Attach a document to the given lease, whatever its status.
     */
    public function store(LeaseDocumentRequest $request, Lease $lease): RedirectResponse
    {
        Gate::authorize('view', $lease);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $lease->documents()->create([
            'account_id' => $lease->account_id,
            'type' => $request->validated('type'),
            'name' => $file->getClientOriginalName(),
            'path' => $file->store("leases/{$lease->id}", LeaseDocument::DISK),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);

        return $this->backWithSelectedRecord($lease->id);
    }

    /**
     * Remove the given document and its file.
     */
    public function destroy(LeaseDocument $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documento removido.')]);

        return $this->backWithSelectedRecord($document->lease_id);
    }
}
