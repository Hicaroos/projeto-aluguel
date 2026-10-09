<?php

namespace App\Http\Requests;

use App\Models\Lease;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExtraChargeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lease_id' => [
                'required',
                'integer',
                Rule::exists('leases', 'id')->where('account_id', $this->user()->account_id)->withoutTrashed()
                    ->where(fn (QueryBuilder $query) => $query->whereIn('id', Lease::query()->select('id'))),
            ],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'due_date' => ['required', 'date'],
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
            'description.required' => __('Descreva o motivo da cobrança.'),
        ];
    }
}
