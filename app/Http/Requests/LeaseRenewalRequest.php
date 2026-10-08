<?php

namespace App\Http\Requests;

use App\Models\Lease;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LeaseRenewalRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currentEnd = $this->lease()->end_date;

        return [
            'new_end_date' => [
                'required',
                'date',
                'after:'.$currentEnd->toDateString(),
                'before_or_equal:'.$currentEnd->addYears(10)->toDateString(),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
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
            'new_end_date' => __('novo término'),
            'notes' => __('observação'),
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
            'new_end_date.after' => __('O novo término deve ser depois do término atual.'),
            'new_end_date.before_or_equal' => __('A renovação pode ser de no máximo 10 anos.'),
        ];
    }

    private function lease(): Lease
    {
        /** @var Lease $lease */
        $lease = $this->route('lease');

        return $lease;
    }
}
