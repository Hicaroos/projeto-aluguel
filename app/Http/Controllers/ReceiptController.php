<?php

namespace App\Http\Controllers;

use App\Actions\Payments\DeleteReceipt;
use App\Actions\Payments\RegisterReceipt;
use App\Http\Requests\ReceiptRequest;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    /**
     * Display the printable rent receipt of the given received amount.
     */
    public function show(Receipt $receipt): Response
    {
        Gate::authorize('view', $receipt);

        $receipt->load([
            'payment:id,lease_id,type,description,reference_month,due_date,amount,status',
            'payment.lease:id,tenant_id,property_id',
            'payment.lease.tenant:id,name,cpf_cnpj,deleted_at',
            'payment.lease.property:id,owner_id,street,number,complement,neighborhood,city,state,zip_code,deleted_at',
            'payment.lease.property.owner:id,name,cpf_cnpj,deleted_at',
        ]);

        return Inertia::render('receipts/Show', [
            'receipt' => $receipt,
            'amountInWords' => $receipt->amountInWords(),
        ]);
    }

    /**
     * Register a received amount for the given open payment.
     */
    public function store(ReceiptRequest $request, Payment $payment, RegisterReceipt $registerReceipt): RedirectResponse
    {
        Gate::authorize('registerReceipt', $payment);

        $registerReceipt->handle($payment, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pagamento registrado com sucesso.')]);

        return back();
    }

    /**
     * Remove the given receipt.
     */
    public function destroy(Receipt $receipt, DeleteReceipt $deleteReceipt): RedirectResponse
    {
        Gate::authorize('delete', $receipt);

        $deleteReceipt->handle($receipt);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pagamento removido com sucesso.')]);

        return back();
    }
}
