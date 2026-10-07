<?php

namespace App\Http\Requests\Settings;

use App\Concerns\NormalizesBrazilianNumbers;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use NormalizesBrazilianNumbers, ProfileValidationRules;

    /**
     * Store the owner phone as digits only, however it was typed.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('owner_phone')) {
            $this->merge(['owner_phone' => $this->digitsOnly($this->input('owner_phone'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            'name' => ['sometimes', ...$this->nameRules()],
            'account_name' => ['sometimes', 'required', 'string', 'max:255'],
            'owner_phone' => ['nullable', 'string', self::PHONE_RULE],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->brazilianNumberMessages(phones: ['owner_phone']);
    }
}
