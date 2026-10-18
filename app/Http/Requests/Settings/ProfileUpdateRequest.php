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
        $isSingleOwner = $this->user()->account?->isAgency() === false;

        return [
            ...$this->profileRules($this->user()->id),
            'name' => ['sometimes', ...$this->nameRules()],
            // An agency's name changes only in the agency settings, which take the permission to manage the agency.
            'account_name' => $isSingleOwner ? ['sometimes', 'required', 'string', 'max:255'] : ['exclude'],
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
