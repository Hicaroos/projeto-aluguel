<?php

namespace App\Http\Requests;

use App\Models\Owner;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LedgerAdjustmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request: an amount in the owner's favor (credit)
     * or taken from them (debit), with what it is about, optionally tied to one of their properties.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Owner $owner */
        $owner = $this->route('owner');

        return [
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'description' => ['required', 'string', 'max:255'],
            'occurred_on' => ['required', 'date'],
            'property_id' => [
                'nullable',
                'integer',
                Rule::in($owner->properties()->withoutGlobalScopes()->pluck('properties.id')->all()),
            ],
        ];
    }

    /**
     * Get the adjustment as a signed amount.
     */
    public function signedAmount(): float
    {
        $amount = round((float) $this->validated('amount'), 2);

        return $this->validated('direction') === 'debit' ? -$amount : $amount;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'direction' => __('tipo do ajuste'),
            'amount' => __('valor'),
            'description' => __('descrição'),
            'occurred_on' => __('data'),
            'property_id' => __('imóvel'),
        ];
    }
}
