<?php

namespace App\Http\Requests;

use App\Enums\DealOutcome;
use App\Enums\DealType;
use App\Models\Deal;
use App\Models\DealStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MoveDealRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Lost deals need the reason; sales are closed by registering the sale instead.
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
     * Get the "after" validation callables for the request.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $deal = $this->route('deal');

                if ($deal instanceof Deal && $deal->type === DealType::Sale && $this->stage()?->outcome === DealOutcome::Won) {
                    $validator->errors()->add('deal_stage_id', __('Registre a venda para fechar esta negociação.'));
                }
            },
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
