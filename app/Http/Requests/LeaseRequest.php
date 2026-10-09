<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesBrazilianNumbers;
use App\Concerns\PersonQualificationRules;
use App\Enums\AdjustmentIndex;
use App\Enums\GuaranteeType;
use App\Enums\LeasePurpose;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaseRequest extends FormRequest
{
    use NormalizesBrazilianNumbers, PersonQualificationRules;

    /**
     * Store the guarantor's documents and phone as digits only, however they were typed.
     */
    protected function prepareForValidation(): void
    {
        $guarantor = $this->input('guarantor');

        if (! is_array($guarantor)) {
            return;
        }

        foreach (['cpf_cnpj', 'phone', 'spouse_cpf'] as $field) {
            if (array_key_exists($field, $guarantor)) {
                $guarantor[$field] = $this->digitsOnly($guarantor[$field]);
            }
        }

        $this->merge(['guarantor' => $guarantor]);
    }

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
                Rule::exists('properties', 'id')->where('account_id', $accountId)->withoutTrashed()
                    ->where(fn (QueryBuilder $query) => $query->whereIn('id', Property::query()->select('id'))),
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
                Rule::exists('tenants', 'id')->where('account_id', $accountId)->withoutTrashed()
                    ->where(fn (QueryBuilder $query) => $query->whereIn('id', Tenant::query()->select('id'))),
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
            'surety_insurer' => ['exclude_unless:guarantee_type,'.GuaranteeType::SuretyBond->value, 'nullable', 'string', 'max:255'],
            'surety_policy_number' => ['exclude_unless:guarantee_type,'.GuaranteeType::SuretyBond->value, 'nullable', 'string', 'max:100'],
            ...$this->guarantorRules(),
            'purpose' => ['sometimes', 'required', Rule::enum(LeasePurpose::class)],
            'adjustment_index' => ['sometimes', 'required', Rule::enum(AdjustmentIndex::class)],
            'late_fee_percent' => ['sometimes', 'required', 'numeric', 'between:0,100'],
            'monthly_interest_percent' => ['sometimes', 'required', 'numeric', 'between:0,100'],
            'termination_fee_months' => ['sometimes', 'required', 'integer', 'between:0,12'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get the validation rules for the guarantor, only kept when the lease is guaranteed by one.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    private function guarantorRules(): array
    {
        $rules = [
            'guarantor' => ['required', 'array'],
            'guarantor.name' => ['required', 'string', 'max:255'],
            'guarantor.cpf_cnpj' => ['nullable', 'string', self::DOCUMENT_RULE],
            'guarantor.email' => ['nullable', 'string', 'email', 'max:255'],
            'guarantor.phone' => ['nullable', 'string', self::PHONE_RULE],
            ...$this->qualificationRules('guarantor.'),
            'guarantor.spouse_name' => ['nullable', 'string', 'max:255'],
            'guarantor.spouse_cpf' => ['nullable', 'string', self::CPF_RULE],
            'guarantor.property_registration' => ['nullable', 'string', 'max:255'],
        ];

        return array_map(
            fn (array $fieldRules): array => ['exclude_unless:guarantee_type,'.GuaranteeType::Guarantor->value, ...$fieldRules],
            $rules,
        );
    }

    /**
     * Get the validated lease attributes, without the guarantor.
     *
     * @return array<string, mixed>
     */
    public function leaseAttributes(): array
    {
        return $this->safe()->except('guarantor');
    }

    /**
     * Get the validated guarantor details, or null when the lease is not guaranteed by one.
     *
     * @return array<string, mixed>|null
     */
    public function guarantorAttributes(): ?array
    {
        /** @var array<string, mixed>|null $guarantor */
        $guarantor = $this->validated('guarantor');

        return $guarantor;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->brazilianNumberMessages(
                documents: ['guarantor.cpf_cnpj'],
                cpfs: ['guarantor.spouse_cpf'],
                phones: ['guarantor.phone'],
            ),
            'end_date.after' => __('O término deve ser depois do início do contrato.'),
            'deposit_amount.required' => __('Informe o valor da caução.'),
        ];
    }
}
