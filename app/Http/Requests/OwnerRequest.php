<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesBrazilianNumbers;
use App\Concerns\PersonQualificationRules;
use App\Concerns\ValidatesSharedDocument;
use App\Models\Owner;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OwnerRequest extends FormRequest
{
    use NormalizesBrazilianNumbers, PersonQualificationRules, ValidatesSharedDocument;

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
        /** @var Owner|null $owner */
        $owner = $this->route('owner');

        return [
            'name' => ['required', 'string', 'max:255'],
            'cpf_cnpj' => [
                'required',
                'string',
                self::DOCUMENT_RULE,
                $this->documentIsNotTaken(Owner::class, $owner, __('Já existe um proprietário com este CPF/CNPJ.')),
            ],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', self::PHONE_RULE],
            ...$this->qualificationRules(),
            'pix_key' => ['nullable', 'string', 'max:255'],
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
            'pix_key' => __('chave Pix'),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->brazilianNumberMessages(documents: ['cpf_cnpj'], phones: ['phone']);
    }
}
