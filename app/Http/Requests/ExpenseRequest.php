<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesBrazilianNumbers;
use App\Enums\ExpensePayer;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    use NormalizesBrazilianNumbers;

    /**
     * Store the provider's document as digits only, however it was typed.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider_document' => $this->digitsOnly($this->input('provider_document')),
        ]);
    }

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
                Rule::exists('properties', 'id')->where('account_id', $this->user()->account_id)->withoutTrashed()
                    ->where(fn (QueryBuilder $query) => $query->whereIn('id', Property::query()->select('id'))),
            ],
            'type' => ['required', Rule::enum(ExpenseType::class)],
            'description' => [
                Rule::requiredIf($this->input('type') === ExpenseType::Other->value),
                'nullable',
                'string',
                'max:255',
            ],
            'provider_name' => ['nullable', 'string', 'max:255'],
            'provider_document' => ['nullable', 'string', self::DOCUMENT_RULE],
            'amount' => ['required', 'numeric', 'gt:0'],
            'due_date' => ['required', 'date'],
            'payment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'charged_to' => ($this->user()->account?->isAgency() ?? false)
                ? ['sometimes', 'required', Rule::enum(ExpensePayer::class)]
                : ['exclude'],
        ];
    }

    /**
     * Get the validated attributes, with the status following the payment date. Only services name a provider.
     *
     * @return array<string, mixed>
     */
    public function expenseAttributes(): array
    {
        $validated = $this->validated();
        $isService = ExpenseType::from($validated['type'])->isService();

        return [
            ...$validated,
            'description' => $validated['description'] ?? null,
            'provider_name' => $isService ? ($validated['provider_name'] ?? null) : null,
            'provider_document' => $isService ? ($validated['provider_document'] ?? null) : null,
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
            ...$this->brazilianNumberMessages(documents: ['provider_document']),
        ];
    }
}
