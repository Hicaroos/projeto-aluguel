<?php

namespace App\Http\Requests;

use App\Enums\GuaranteeType;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use App\Models\Property;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Lease|null $lease */
        $lease = $this->route('lease');
        $accountId = $this->user()->account_id;

        return [
            'property_id' => [
                'required',
                'integer',
                Rule::exists('properties', 'id')->where('account_id', $accountId)->withoutTrashed(),
                function (string $attribute, mixed $value, Closure $fail) use ($lease): void {
                    if ($lease?->property_id === (int) $value) {
                        return;
                    }

                    $property = Property::whereKey($value)->first();

                    if ($property !== null && $property->status !== PropertyStatus::Available) {
                        $fail(__('Este imóvel não está disponível para locação.'));
                    }
                },
            ],
            'tenant_id' => [
                'required',
                'integer',
                Rule::exists('tenants', 'id')->where('account_id', $accountId)->withoutTrashed(),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'guarantee_type' => ['required', Rule::enum(GuaranteeType::class)],
            'deposit_amount' => [
                'exclude_unless:guarantee_type,'.GuaranteeType::Deposit->value,
                'required',
                'numeric',
                'min:0',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
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
            'end_date.after' => __('O término deve ser depois do início do contrato.'),
            'deposit_amount.required' => __('Informe o valor da caução.'),
        ];
    }
}
