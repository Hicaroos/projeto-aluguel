<?php

namespace App\Http\Requests;

use App\Enums\VisitStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealVisitRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('account_id', $this->user()->account_id)->whereNull('deactivated_at'),
            ],
            'status' => ['sometimes', 'required', Rule::enum(VisitStatus::class)],
            'feedback' => ['nullable', 'string', 'max:2000'],
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
            'scheduled_at' => __('data e hora'),
            'user_id' => __('responsável'),
            'feedback' => __('como foi'),
        ];
    }
}
