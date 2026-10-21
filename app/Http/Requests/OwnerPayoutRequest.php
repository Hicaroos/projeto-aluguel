<?php

namespace App\Http\Requests;

use App\Actions\Payouts\SummarizeOwnerBalances;
use App\Enums\PaymentMethod;
use App\Models\Owner;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OwnerPayoutRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request. Owners are paid at most what is already
     * theirs to receive; anything else goes through an adjustment.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.max(0, $this->availableBalance())],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)->except([PaymentMethod::Deposit])],
            'notes' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', ...OwnerPayoutReceiptRequest::RECEIPT_RULES],
        ];
    }

    /**
     * Get what the owner may be paid today.
     */
    public function availableBalance(): float
    {
        /** @var Owner $owner */
        $owner = $this->route('owner');

        return app(SummarizeOwnerBalances::class)->balances([$owner->id])[$owner->id]['available'] ?? 0.0;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => __('valor'),
            'paid_on' => __('data do repasse'),
            'payment_method' => __('forma de pagamento'),
            ...OwnerPayoutReceiptRequest::receiptAttributes(),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.max' => __('O repasse não pode passar do saldo disponível de :max.', ['max' => 'R$ '.number_format(max(0, $this->availableBalance()), 2, ',', '.')]),
            'paid_on.before_or_equal' => __('A data do repasse não pode estar no futuro.'),
            ...OwnerPayoutReceiptRequest::receiptMessages(),
        ];
    }
}
