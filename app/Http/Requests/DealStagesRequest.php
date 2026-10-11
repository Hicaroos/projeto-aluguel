<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealStagesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stages' => ['required', 'array', 'min:1', 'max:12'],
            'stages.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('deal_stages', 'id')->where('account_id', $this->user()->account_id)->whereNull('outcome'),
            ],
            'stages.*.name' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'won_name' => ['required', 'string', 'max:40'],
            'lost_name' => ['required', 'string', 'max:40'],
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
            'stages.required' => __('O funil precisa de pelo menos uma etapa.'),
            'stages.min' => __('O funil precisa de pelo menos uma etapa.'),
            'stages.*.name.required' => __('Dê um nome para cada etapa.'),
            'stages.*.name.distinct' => __('Cada etapa precisa de um nome diferente.'),
            'won_name.required' => __('Dê um nome para a etapa de fechamento.'),
            'lost_name.required' => __('Dê um nome para a etapa de perdidos.'),
        ];
    }
}
