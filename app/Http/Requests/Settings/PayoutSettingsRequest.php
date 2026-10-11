<?php

namespace App\Http\Requests\Settings;

use App\Enums\PayoutRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PayoutSettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request: every payout rule needs its default.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return PayoutRule::rulesFor(required: true);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return PayoutRule::labels();
    }
}
