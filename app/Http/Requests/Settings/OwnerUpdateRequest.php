<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PersonQualificationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OwnerUpdateRequest extends FormRequest
{
    use PersonQualificationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            ...$this->qualificationRules(),
            'pix_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
