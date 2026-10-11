<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesBrazilianNumbers;
use App\Enums\LeadInterest;
use App\Enums\LeadSource;
use App\Enums\PaymentPlan;
use App\Enums\PropertyType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadRequest extends FormRequest
{
    use NormalizesBrazilianNumbers;

    /**
     * Store the phone as digits only, however it was typed.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->digitsOnly($this->input('phone')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', self::PHONE_RULE],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'interest' => ['required', Rule::enum(LeadInterest::class)],
            'property_type' => ['nullable', Rule::enum(PropertyType::class)],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'neighborhoods' => ['nullable', 'string', 'max:255'],
            'payment_plan' => ['nullable', Rule::enum(PaymentPlan::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('account_id', $this->user()->account_id)->whereNull('deactivated_at'),
            ],
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
            'phone' => __('celular'),
            'interest' => __('interesse'),
            'max_price' => __('valor máximo'),
            'user_id' => __('responsável'),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->brazilianNumberMessages(phones: ['phone']);
    }
}
