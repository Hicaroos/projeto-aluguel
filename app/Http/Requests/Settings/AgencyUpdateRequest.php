<?php

namespace App\Http\Requests\Settings;

use App\Concerns\NormalizesBrazilianNumbers;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AgencyUpdateRequest extends FormRequest
{
    use NormalizesBrazilianNumbers;

    /**
     * Store the document and phone as digits only, however they were typed.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'document' => $this->digitsOnly($this->input('document')),
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
            'legal_name' => ['nullable', 'string', 'max:255'],
            'document' => ['required', 'string', self::CNPJ_RULE],
            'creci' => ['nullable', 'string', 'max:20'],
            'phone' => ['required', 'string', self::PHONE_RULE],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:9'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
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
            'name' => __('nome fantasia'),
            'legal_name' => __('razão social'),
            'document' => __('CNPJ'),
            'creci' => __('CRECI'),
            'phone' => __('telefone'),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->brazilianNumberMessages(phones: ['phone'], cnpjs: ['document']);
    }
}
