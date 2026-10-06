<?php

namespace App\Http\Requests;

use App\Concerns\PersonQualificationRules;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantRequest extends FormRequest
{
    use PersonQualificationRules;

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
                'nullable',
                'string',
                'max:20',
                Rule::unique('tenants', 'cpf_cnpj')
                    ->where('account_id', $this->user()->account_id)
                    ->ignore($tenant?->id),
            ],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            ...$this->qualificationRules(),
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
        ];
    }
}
