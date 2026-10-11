<?php

namespace App\Http\Requests;

use App\Enums\DealType;
use App\Enums\PropertyStatus;
use App\Models\Lead;
use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The property must be offered for what the deal is about and not sold yet.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accountId = $this->user()->account_id;
        $isRent = $this->input('type') === DealType::Rent->value;

        return [
            'lead_id' => [
                'required',
                'integer',
                Rule::exists('leads', 'id')->where('account_id', $accountId)
                    ->where(fn (QueryBuilder $query) => $query->whereIn('id', Lead::query()->select('id'))),
            ],
            'type' => ['required', Rule::enum(DealType::class)],
            'property_id' => [
                'nullable',
                'integer',
                Rule::exists('properties', 'id')->where('account_id', $accountId)->withoutTrashed()
                    ->where($isRent ? 'for_rent' : 'for_sale', true)
                    ->whereNot('status', PropertyStatus::Sold->value)
                    ->where(fn (QueryBuilder $query) => $query->whereIn('id', Property::query()->select('id'))),
            ],
            'value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('account_id', $accountId)->whereNull('deactivated_at'),
            ],
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
            'lead_id' => __('interessado'),
            'property_id' => __('imóvel'),
            'value' => __('valor'),
            'user_id' => __('responsável'),
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
            'property_id.exists' => __('Escolha um imóvel disponível para esta negociação.'),
        ];
    }
}
