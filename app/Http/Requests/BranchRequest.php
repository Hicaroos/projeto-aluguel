<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesBrazilianNumbers;
use App\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'name')
                    ->where('account_id', $this->user()->account_id)
                    ->ignore($this->branch()?->id),
            ],
            'document' => ['nullable', 'string', self::CNPJ_RULE],
            'creci' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', self::PHONE_RULE],
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
            'name' => __('nome'),
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
        return [
            'name.unique' => __('Já existe uma unidade com este nome.'),
            ...$this->brazilianNumberMessages(phones: ['phone'], cnpjs: ['document']),
        ];
    }

    /**
     * Get the branch being updated, if any.
     */
    private function branch(): ?Branch
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch ? $branch : null;
    }
}
