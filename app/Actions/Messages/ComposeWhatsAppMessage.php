<?php

namespace App\Actions\Messages;

use App\Actions\Payments\CalculateLateCharges;
use App\Enums\MessageTemplate;
use App\Models\Account;
use App\Models\Lease;
use App\Models\LeaseAdjustment;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\Property;
use App\Models\SentMessage;
use Carbon\CarbonInterface;
use Illuminate\Support\Number;

/**
 * Write the WhatsApp message the team sends about a payment, payout or rent adjustment, from the account's
 * own text, and the link that opens it on their phone.
 *
 * @phpstan-type WhatsAppMessage array{
 *     template: string,
 *     action_label: string,
 *     recipient: string,
 *     phone: string|null,
 *     text: string,
 *     url: string|null,
 *     last_sent: array{sent_at: string, user: string|null, label: string}|null,
 * }
 */
class ComposeWhatsAppMessage
{
    /**
     * The accounts already loaded, with their Pix key, by id.
     *
     * @var array<int, array{account: Account, pix_key: string|null}>
     */
    private array $accounts = [];

    public function __construct(private CalculateLateCharges $calculateLateCharges) {}

    /**
     * Write the reminder of an open payment, or the collection of an overdue one, to the tenant. Payments
     * already paid or canceled have no message.
     *
     * @return WhatsAppMessage|null
     */
    public function forPayment(Payment $payment): ?array
    {
        if (! $payment->isOpen()) {
            return null;
        }

        $lease = $payment->lease;
        $remaining = array_key_exists('received_amount', $payment->getAttributes())
            ? max(0, round((float) $payment->amount - (float) $payment->received_amount, 2))
            : $payment->remainingAmount();
        $isOverdue = $payment->due_date->lt(today());
        $lateCharges = $this->calculateLateCharges->handle($payment, $remaining, today());

        return $this->compose(
            $payment->account_id,
            $isOverdue ? MessageTemplate::PaymentOverdue : MessageTemplate::PaymentReminder,
            'inquilino',
            $lease->tenant->phone,
            [
                'inquilino' => $lease->tenant->name,
                'imovel' => $this->address($lease->property),
                'referencia' => $payment->isExtra()
                    ? ($payment->description ?? 'cobrança avulsa')
                    : 'aluguel de '.$this->monthName($payment->reference_month ?? $payment->due_date),
                'valor' => $this->money($remaining),
                'valor_atualizado' => $this->money($remaining + $lateCharges['late_fee'] + $lateCharges['interest']),
                'vencimento' => $this->date($payment->due_date),
                'dias_atraso' => $lateCharges['days_late'] === 1 ? '1 dia' : "{$lateCharges['days_late']} dias",
            ],
            $payment->latestSentMessage,
        );
    }

    /**
     * Add to each payment its message, as `whatsapp`, for the pages that list payments.
     *
     * @param  iterable<Payment>  $payments
     */
    public function attachToPayments(iterable $payments): void
    {
        foreach ($payments as $payment) {
            $payment->setAttribute('whatsapp', $this->forPayment($payment));
        }
    }

    /**
     * Write the notice of a payout to the owner.
     *
     * @return WhatsAppMessage
     */
    public function forPayout(OwnerPayout $payout): array
    {
        return $this->compose(
            $payout->account_id,
            MessageTemplate::PayoutNotice,
            'proprietário',
            $payout->owner->phone,
            [
                'proprietario' => $payout->owner->name,
                'valor' => $this->money((float) $payout->amount),
                'data' => $this->date($payout->paid_on),
            ],
            $payout->latestSentMessage,
        );
    }

    /**
     * Add to the last rent adjustment of the lease its notice, as `whatsapp`, for the lease details.
     */
    public function attachToLatestAdjustment(Lease $lease): void
    {
        $lease->adjustments->last()?->setAttribute('whatsapp', $this->forAdjustment($lease->adjustments->last(), $lease));
    }

    /**
     * Write the notice of a rent adjustment to the tenant.
     *
     * @param  Lease|null  $lease  The lease of the adjustment, when already loaded.
     * @return WhatsAppMessage
     */
    public function forAdjustment(LeaseAdjustment $adjustment, ?Lease $lease = null): array
    {
        $lease ??= $adjustment->lease;

        return $this->compose(
            $lease->account_id,
            MessageTemplate::AdjustmentNotice,
            'inquilino',
            $lease->tenant->phone,
            [
                'inquilino' => $lease->tenant->name,
                'imovel' => $this->address($lease->property),
                'percentual' => (string) Number::percentage((float) $adjustment->percent, 2, locale: 'pt_BR'),
                'indice' => $adjustment->adjustment_index->label(),
                'valor_anterior' => $this->money((float) $adjustment->previous_amount),
                'valor_novo' => $this->money((float) $adjustment->new_amount),
                'vigencia' => $this->date($adjustment->effective_on),
            ],
            $adjustment->latestSentMessage,
        );
    }

    /**
     * Fill the account's text of the message, adding the account's name and Pix key to the given values, and
     * build the link that opens it on WhatsApp. A line whose variable comes out blank is left out.
     *
     * @param  array<string, string>  $values
     * @return WhatsAppMessage
     */
    private function compose(int $accountId, MessageTemplate $template, string $recipient, ?string $phone, array $values, ?SentMessage $lastSent): array
    {
        $this->accounts[$accountId] ??= $this->loadAccount($accountId);
        ['account' => $account, 'pix_key' => $pixKey] = $this->accounts[$accountId];

        $text = $this->render($account->messageTemplate($template), [
            ...$values,
            'chave_pix' => $pixKey ?? '',
            'imobiliaria' => $account->name,
        ]);
        $phone = $this->whatsAppNumber($phone);

        return [
            'template' => $template->value,
            'action_label' => $template->actionLabel(),
            'recipient' => $recipient,
            'phone' => $phone,
            'text' => $text,
            'url' => $phone === null ? null : "https://wa.me/{$phone}?text=".rawurlencode($text),
            'last_sent' => $lastSent === null ? null : [
                'sent_at' => $lastSent->created_at?->toIso8601String() ?? '',
                'user' => $lastSent->user?->name,
                'label' => $lastSent->template->label(),
            ],
        ];
    }

    /**
     * Replace the {variables} of the text, leaving out the lines where one of them is blank.
     *
     * @param  array<string, string>  $values
     */
    private function render(string $text, array $values): string
    {
        $replacements = collect($values)->mapWithKeys(fn (string $value, string $key): array => ["{{$key}}" => $value])->all();

        return trim(collect(explode("\n", $text))
            ->reject(function (string $line) use ($values): bool {
                preg_match_all('/\{(\w+)\}/', $line, $matches);

                return collect($matches[1])->contains(fn (string $key): bool => ($values[$key] ?? null) === '');
            })
            ->map(fn (string $line): string => strtr($line, $replacements))
            ->implode("\n"));
    }

    /**
     * @return array{account: Account, pix_key: string|null}
     */
    private function loadAccount(int $accountId): array
    {
        $account = Account::findOrFail($accountId);

        return ['account' => $account, 'pix_key' => $account->paymentPixKey()];
    }

    /**
     * Get the number WhatsApp links take, with the country code, or null for a phone it cannot call.
     */
    private function whatsAppNumber(?string $phone): ?string
    {
        $digits = (string) preg_replace('/\D/', '', (string) $phone);

        return match (true) {
            in_array(strlen($digits), [10, 11], true) => "55{$digits}",
            in_array(strlen($digits), [12, 13], true) && str_starts_with($digits, '55') => $digits,
            default => null,
        };
    }

    private function address(Property $property): string
    {
        return collect([$property->street, $property->number, $property->complement])->filter()->implode(', ');
    }

    private function money(float $amount): string
    {
        return (string) Number::currency($amount, 'BRL', 'pt_BR');
    }

    private function date(CarbonInterface $date): string
    {
        return $date->format('d/m/Y');
    }

    /**
     * Format a month in Brazilian Portuguese, e.g. "outubro/2026".
     */
    private function monthName(CarbonInterface $month): string
    {
        return $month->locale('pt_BR')->translatedFormat('F/Y');
    }
}
