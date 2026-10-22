<?php

namespace App\Http\Controllers;

use App\Actions\Messages\ComposeWhatsAppMessage;
use App\Models\Lease;
use App\Models\LeaseAdjustment;
use App\Models\OwnerPayout;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Record the WhatsApp messages the team opens on their phone, so the others see who already sent one and when.
 * The message is written again here rather than taken from the page.
 *
 * @phpstan-import-type WhatsAppMessage from ComposeWhatsAppMessage
 */
class WhatsAppMessageController extends Controller
{
    public function __construct(private ComposeWhatsAppMessage $composeWhatsAppMessage) {}

    /**
     * Record the reminder or the collection of a payment sent to the tenant.
     */
    public function payment(Request $request, Payment $payment): RedirectResponse
    {
        return $this->record($request, $payment, $this->composeWhatsAppMessage->forPayment($payment));
    }

    /**
     * Record the notice of a payout sent to the owner.
     */
    public function payout(Request $request, OwnerPayout $payout): RedirectResponse
    {
        return $this->record($request, $payout, $this->composeWhatsAppMessage->forPayout($payout));
    }

    /**
     * Record the notice of a rent adjustment sent to the tenant.
     */
    public function adjustment(Request $request, LeaseAdjustment $adjustment): RedirectResponse
    {
        Lease::findOrFail($adjustment->lease_id);

        return $this->record($request, $adjustment, $this->composeWhatsAppMessage->forAdjustment($adjustment));
    }

    /**
     * Keep the message, unless there was none to send: a payment paid meanwhile, or a phone WhatsApp cannot call.
     *
     * @param  WhatsAppMessage|null  $message
     */
    private function record(Request $request, Payment|OwnerPayout|LeaseAdjustment $subject, ?array $message): RedirectResponse
    {
        if ($message !== null && $message['phone'] !== null) {
            $subject->sentMessages()->create([
                'user_id' => $request->user()->id,
                'template' => $message['template'],
                'phone' => $message['phone'],
                'body' => $message['text'],
            ]);
        }

        return back();
    }
}
