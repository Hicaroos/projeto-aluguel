<?php

namespace App\Http\Requests;

use App\Enums\PaymentPlan;
use App\Enums\ProposalStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealProposalRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'payment_plan' => ['nullable', Rule::enum(PaymentPlan::class)],
            'conditions' => ['nullable', 'string', 'max:2000'],
            'proposed_on' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['sometimes', 'required', Rule::enum(ProposalStatus::class)],
        ];
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
            'payment_plan' => __('forma de pagamento'),
            'conditions' => __('condições'),
            'proposed_on' => __('data'),
        ];
    }
}
