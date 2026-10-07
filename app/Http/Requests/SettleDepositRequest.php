<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use App\Models\Lease;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettleDepositRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_ids' => ['nullable', 'array'],
            'payment_ids.*' => [
                'integer',
                Rule::exists('payments', 'id')
                    ->where('lease_id', $this->lease()->id)
                    ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Partial->value]),
            ],
            'deductions' => [
                'nullable',
                'array',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $total = collect(is_array($value) ? $value : [])->sum(fn (mixed $deduction): float => (float) (is_array($deduction) ? ($deduction['amount'] ?? 0) : 0));

                    if ($total > (float) $this->lease()->deposit_amount) {
                        $fail(__('Os descontos não podem passar do valor da caução.'));
                    }
                },
            ],
            'deductions.*.description' => ['required', 'string', 'max:255'],
            'deductions.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'settled_on' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * Get the open payments chosen to be paid out of the deposit.
     *
     * @return list<int>
     */
    public function paymentIds(): array
    {
        return array_values(array_map('intval', (array) $this->validated('payment_ids', [])));
    }

    /**
     * Get the exit deductions to charge and pay out of the deposit.
     *
     * @return list<array{description: string, amount: float|string}>
     */
    public function deductions(): array
    {
        /** @var list<array{description: string, amount: float|string}> $deductions */
        $deductions = array_values((array) $this->validated('deductions', []));

        return $deductions;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payment_ids.*' => __('cobrança'),
            'deductions.*.description' => __('descrição do desconto'),
            'deductions.*.amount' => __('valor do desconto'),
            'settled_on' => __('data da devolução'),
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
            'payment_ids.*.exists' => __('Escolha apenas cobranças em aberto deste contrato.'),
            'settled_on.before_or_equal' => __('A data da devolução não pode estar no futuro.'),
        ];
    }

    private function lease(): Lease
    {
        /** @var Lease $lease */
        $lease = $this->route('lease');

        return $lease;
    }
}
