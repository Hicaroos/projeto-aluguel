<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiptRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Payment $payment */
        $payment = $this->route('payment');

        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.$payment->remainingAmount()],
            'late_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'interest_amount' => ['nullable', 'numeric', 'min:0'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->except([PaymentMethod::Deposit])],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the receipt attributes. Extra charges carry no late charges, so theirs are always zero.
     *
     * @return array<string, mixed>
     */
    public function receiptAttributes(): array
    {
        /** @var Payment $payment */
        $payment = $this->route('payment');
        $chargesLateFees = ! $payment->isExtra();

        return [
            ...$this->validated(),
            'late_fee_amount' => $chargesLateFees ? (float) ($this->validated('late_fee_amount') ?? 0) : 0,
            'interest_amount' => $chargesLateFees ? (float) ($this->validated('interest_amount') ?? 0) : 0,
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
            'amount.max' => __('O valor não pode ser maior que o saldo em aberto da cobrança.'),
            'date.before_or_equal' => __('A data do pagamento não pode estar no futuro.'),
        ];
    }
}
