<?php

namespace App\Http\Requests;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'property_id' => [
                'required',
                'integer',
                Rule::exists('properties', 'id')->where('account_id', $this->user()->account_id)->withoutTrashed(),
            ],
            'type' => ['required', Rule::enum(ExpenseType::class)],
            'description' => [
                Rule::requiredIf($this->input('type') === ExpenseType::Other->value),
                'nullable',
                'string',
                'max:255',
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'due_date' => ['required', 'date'],
            'payment_date' => ['nullable', 'date', 'before_or_equal:today'],
            ...($this->isCreating() ? $this->chargeTenantRules() : []),
        ];
    }

    /**
     * Get the rules to charge the expense to the tenant of a lease of the same property.
     *
     * @return array<string, array<mixed>>
     */
    private function chargeTenantRules(): array
    {
        return [
            'charge_tenant' => ['sometimes', 'boolean'],
            'charge_lease_id' => [
                'exclude_unless:charge_tenant,1',
                'required',
                'integer',
                Rule::exists('leases', 'id')
                    ->where('account_id', $this->user()->account_id)
                    ->where('property_id', $this->integer('property_id'))
                    ->withoutTrashed(),
            ],
        ];
    }

    /**
     * Get the lease whose tenant should be charged for the expense, if requested.
     */
    public function chargeLeaseId(): ?int
    {
        return $this->isCreating() && $this->boolean('charge_tenant')
            ? (int) $this->validated('charge_lease_id')
            : null;
    }

    /**
     * Determine whether the request creates a new expense (charging the tenant is only offered then).
     */
    private function isCreating(): bool
    {
        return $this->route('expense') === null;
    }

    /**
     * Get the validated attributes, with the status following the payment date.
     *
     * @return array<string, mixed>
     */
    public function expenseAttributes(): array
    {
        $validated = $this->safe()->except(['charge_tenant', 'charge_lease_id']);

        return [
            ...$validated,
            'description' => $validated['description'] ?? null,
            'payment_date' => $validated['payment_date'] ?? null,
            'status' => filled($validated['payment_date'] ?? null) ? ExpenseStatus::Paid : ExpenseStatus::Pending,
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
            'description.required' => __('Descreva a despesa quando o tipo for "Outra".'),
            'payment_date.before_or_equal' => __('A data de pagamento não pode estar no futuro.'),
            'charge_lease_id.required' => __('Escolha o contrato do inquilino que vai ser cobrado.'),
            'charge_lease_id.exists' => __('Escolha um contrato deste imóvel.'),
        ];
    }
}
