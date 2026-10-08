<?php

namespace App\Http\Controllers;

use App\Actions\Payments\DeleteReceipt;
use App\Actions\Payments\RegisterReceipt;
use App\Concerns\FormatsBrazilianNumbers;
use App\Enums\PaymentType;
use App\Http\Requests\ReceiptRequest;
use App\Models\Payment;
use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReceiptController extends Controller
{
    use FormatsBrazilianNumbers;

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
     * Generate the rent receipt as a PDF, e.g. to share it with the tenant on WhatsApp.
     */
    public function pdf(Receipt $receipt): SymfonyResponse
    {
        Gate::authorize('view', $receipt);

        $receipt->load(['payment.lease.tenant', 'payment.lease.property.owner']);

        $payment = $receipt->payment;
        $tenant = $payment->lease->tenant;
        $property = $payment->lease->property;
        $isExtraCharge = $payment->type === PaymentType::Extra || $payment->reference_month === null;
        $isPartial = (float) $receipt->amount < (float) $payment->amount;
        $reference = $isExtraCharge
            ? ($payment->description ?? 'Cobrança avulsa')
            : $this->translatedDate($payment->reference_month, 'F \d\e Y');
        $complement = $property->complement ? ", {$property->complement}" : '';

        return Pdf::loadView('pdf.receipt', [
            'receipt' => $receipt,
            'number' => str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT),
            'appName' => config('app.name'),
            'tenant' => $tenant,
            'tenantDocument' => $this->formatDocument($tenant->cpf_cnpj),
            'owner' => $property->owner,
            'ownerDocument' => $this->formatDocument($property->owner->cpf_cnpj),
            'city' => $property->city,
            'total' => $receipt->totalAmount(),
            'amountInWords' => $receipt->amountInWords(),
            'hasLateCharges' => $receipt->totalAmount() > (float) $receipt->amount,
            'isPartial' => $isPartial,
            'paymentDescription' => match (true) {
                $isExtraCharge && $isPartial => 'ao pagamento parcial de',
                $isExtraCharge => 'a',
                $isPartial => 'ao pagamento parcial do aluguel do mês de',
                default => 'ao aluguel do mês de',
            },
            'reference' => $reference,
            'referenceLabel' => Str::ucfirst($reference),
            'address' => "{$property->street}, {$property->number}{$complement} – {$property->neighborhood}, {$property->city}/{$property->state}, CEP {$property->zip_code}",
            'issuedOn' => $this->translatedDate($receipt->date, 'j \d\e F \d\e Y'),
        ])
            ->setPaper('a4')
            ->stream("recibo-{$receipt->id}-".Str::slug($tenant->name).'.pdf');
    }

    /**
     * Format a date in Brazilian Portuguese, e.g. "outubro de 2026".
     */
    private function translatedDate(CarbonInterface $date, string $format): string
    {
        return $date->locale('pt_BR')->translatedFormat($format);
    }

    /**
     * Register a received amount for the given open payment.
     */
    public function store(ReceiptRequest $request, Payment $payment, RegisterReceipt $registerReceipt): RedirectResponse
    {
        Gate::authorize('registerReceipt', $payment);

        $registerReceipt->handle($payment, $request->receiptAttributes());

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
