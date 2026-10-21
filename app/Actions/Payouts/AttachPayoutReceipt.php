<?php

namespace App\Actions\Payouts;

use App\Models\OwnerPayout;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AttachPayoutReceipt
{
    /**
     * Store the proof of the transfer to the owner, replacing the one attached before.
     */
    public function handle(OwnerPayout $payout, UploadedFile $file): void
    {
        $previousPath = $payout->receipt_path;

        $payout->update([
            'receipt_name' => $file->getClientOriginalName(),
            'receipt_path' => $file->store("payouts/{$payout->id}", OwnerPayout::DISK),
        ]);

        if ($previousPath !== null) {
            Storage::disk(OwnerPayout::DISK)->delete($previousPath);
        }
    }
}
