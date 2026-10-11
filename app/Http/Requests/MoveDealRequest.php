<?php

namespace App\Http\Requests;

use App\Enums\DealOutcome;
use App\Models\DealStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveDealRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Lost deals need the reason.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'deal_stage_id' => [
                'required',
                'integer',
                Rule::exists('deal_stages', 'id')->where('account_id', $this->user()->account_id),
            ],
            'position' => ['nullable', 'integer', 'min:0'],
            'lost_reason' => [Rule::requiredIf(fn (): bool => $this->stage()?->outcome === DealOutcome::Lost), 'nullable', 'string', 'max:255'],
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
            'lost_reason.required' => __('Conte por que a negociação foi perdida.'),
        ];
    }

    /**
     * Get the stage the deal moves to.
     */
    public function stage(): ?DealStage
    {
        return DealStage::find($this->integer('deal_stage_id'));
    }
}
