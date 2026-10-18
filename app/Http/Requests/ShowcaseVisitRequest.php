<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesBrazilianNumbers;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowcaseVisitRequest extends FormRequest
{
    use NormalizesBrazilianNumbers;

    /**
     * When the visitor would rather be called, as the agency reads it.
     *
     * @var array<string, string>
     */
    public const array PERIODS = [
        'manha' => 'pela manhã',
        'tarde' => 'pela tarde',
        'sabado' => 'no sábado',
    ];

    /**
     * Store the phone as digits only, however it was typed.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->digitsOnly($this->input('phone')),
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
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', self::PHONE_RULE],
            'period' => ['required', Rule::in(array_keys(self::PERIODS))],
            'purpose' => ['required', Rule::in(['sale', 'rent'])],
            // Left empty by people; bots filling every field give themselves away.
            'website' => ['nullable', 'string'],
        ];
    }

    /**
     * Determine whether the request came from a bot filling the hidden field.
     */
    public function isFromBot(): bool
    {
        return $this->filled('website');
    }

    /**
     * Get the visit request as the funnel records it.
     *
     * @return array{name: string, phone: string, period: string, purpose: 'sale'|'rent'}
     */
    public function visit(): array
    {
        /** @var 'sale'|'rent' $purpose */
        $purpose = $this->validated('purpose');

        return [
            'name' => $this->string('name')->squish()->toString(),
            'phone' => (string) $this->validated('phone'),
            'period' => self::PERIODS[$this->validated('period')],
            'purpose' => $purpose,
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
            'name' => __('nome'),
            'phone' => __('WhatsApp'),
            'period' => __('melhor horário'),
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
            ...$this->brazilianNumberMessages(phones: ['phone']),
            'name.required' => __('Informe seu nome para a equipe saber com quem falar.'),
            'phone.required' => __('Informe seu WhatsApp com DDD para combinarmos a visita.'),
        ];
    }
}
