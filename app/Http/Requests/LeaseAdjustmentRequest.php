<?php

namespace App\Http\Requests;

use App\Models\Lease;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaseAdjustmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The rent is adjusted either by an index percent or to a new amount agreed between the parties.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['percent', 'amount'])],
            'percent' => ['exclude_unless:mode,percent', 'required', 'numeric', 'between:-50,100', 'decimal:0,2'],
            'new_amount' => [
                'exclude_unless:mode,amount',
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $current = (float) $this->lease()->amount;

                    if (is_numeric($value) && ((float) $value < $current * 0.5 || (float) $value > $current * 2)) {
                        $fail(__('O novo valor deve ficar entre a metade e o dobro do aluguel atual.'));
                    }
                },
            ],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the change to apply: the percent, or the new amount.
     *
     * @return array{percent?: float, new_amount?: float}
     */
    public function change(): array
    {
        return $this->validated('mode') === 'amount'
            ? ['new_amount' => (float) $this->validated('new_amount')]
            : ['percent' => (float) $this->validated('percent')];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'percent' => __('percentual'),
            'new_amount' => __('novo valor'),
        ];
    }

    private function lease(): Lease
    {
        /** @var Lease $lease */
        $lease = $this->route('lease');

        return $lease;
    }
}
