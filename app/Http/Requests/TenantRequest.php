<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesBrazilianNumbers;
use App\Concerns\PersonQualificationRules;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantRequest extends FormRequest
{
    use NormalizesBrazilianNumbers, PersonQualificationRules;

    /**
     * Store the document and phone as digits only, however they were typed.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf_cnpj' => $this->digitsOnly($this->input('cpf_cnpj')),
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
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');

        return [
            'name' => ['required', 'string', 'max:255'],
            'cpf_cnpj' => [
                'required',
                'string',
                self::DOCUMENT_RULE,
                Rule::unique('tenants', 'cpf_cnpj')
                    ->where('account_id', $this->user()->account_id)
                    ->ignore($tenant?->id),
            ],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', self::PHONE_RULE],
            ...$this->qualificationRules(),
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
            'cpf_cnpj.unique' => __('Já existe um inquilino com este CPF/CNPJ.'),
            ...$this->brazilianNumberMessages(documents: ['cpf_cnpj'], phones: ['phone']),
        ];
    }
}
