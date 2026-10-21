<?php

namespace App\Http\Controllers;

use App\Actions\Payouts\AttachPayoutReceipt;
use App\Http\Requests\OwnerPayoutReceiptRequest;
use App\Models\OwnerPayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerPayoutReceiptController extends Controller
{
    /**
     * Open the proof of the transfer in the browser, e.g. a PDF in a new tab.
     */
    public function show(OwnerPayout $payout): StreamedResponse
    {
        Gate::authorize('view', $payout);

        abort_unless($payout->receipt_path !== null && Storage::disk(OwnerPayout::DISK)->exists($payout->receipt_path), 404);

        return Storage::disk(OwnerPayout::DISK)->response($payout->receipt_path, $payout->receipt_name);
    }

    /**
     * Attach the proof of the transfer to a payout registered without it, or replace it.
     */
    public function store(OwnerPayoutReceiptRequest $request, OwnerPayout $payout, AttachPayoutReceipt $attachPayoutReceipt): RedirectResponse
    {
        Gate::authorize('update', $payout);

        /** @var UploadedFile $file */
        $file = $request->file('receipt');

        $attachPayoutReceipt->handle($payout, $file);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comprovante anexado.')]);

        return back();
    }

    /**
     * Remove the proof of the transfer, keeping the payout.
     */
    public function destroy(OwnerPayout $payout): RedirectResponse
    {
        Gate::authorize('update', $payout);

        if ($payout->receipt_path !== null) {
            Storage::disk(OwnerPayout::DISK)->delete($payout->receipt_path);
        }

        $payout->update(['receipt_name' => null, 'receipt_path' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comprovante removido.')]);

        return back();
    }
}
