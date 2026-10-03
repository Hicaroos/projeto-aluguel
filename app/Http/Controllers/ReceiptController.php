<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Http\Requests\ReceiptRequest;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReceiptController extends Controller
{
    /**
     * Register a received amount for the given open payment.
     */
    public function store(ReceiptRequest $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->account_id === $request->user()->account_id, 404);

        abort_unless(in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Partial], true), 403);

        DB::transaction(function () use ($request, $payment): void {
            $payment->receipts()->create([
                ...$request->validated(),
                'account_id' => $payment->account_id,
            ]);

            $payment->refreshStatus();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pagamento registrado com sucesso.')]);

        return back();
    }

    /**
     * Remove the given receipt and recalculate its payment status.
     */
    public function destroy(Request $request, Receipt $receipt): RedirectResponse
    {
        abort_unless($receipt->account_id === $request->user()->account_id, 404);

        DB::transaction(function () use ($receipt): void {
            $receipt->delete();

            $receipt->payment->refreshStatus();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pagamento removido com sucesso.')]);

        return back();
    }
}
