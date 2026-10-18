<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesBrazilianNumbers;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowcaseListingRequest extends FormRequest
{
    use NormalizesBrazilianNumbers;

    /**
     * What the owner wants the agency to do with their property, as the agency reads it.
     *
     * @var array<string, string>
     */
    public const array GOALS = [
        'alugar' => 'alugar o imóvel',
        'vender' => 'vender o imóvel',
        'avaliar' => 'avaliar o imóvel',
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
            'goal' => ['required', Rule::in(array_keys(self::GOALS))],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:1000'],
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
     * Get the listing request as the funnel records it.
     *
     * @return array{name: string, phone: string, goal: string, neighborhood: string|null, message: string|null}
     */
    public function listing(): array
    {
        $neighborhood = $this->string('neighborhood')->squish()->toString();
        $message = $this->string('message')->trim()->toString();

        return [
            'name' => $this->string('name')->squish()->toString(),
            'phone' => (string) $this->validated('phone'),
            'goal' => self::GOALS[$this->validated('goal')],
            'neighborhood' => $neighborhood === '' ? null : $neighborhood,
            'message' => $message === '' ? null : $message,
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
            'goal' => __('o que você quer'),
            'neighborhood' => __('bairro'),
            'message' => __('sobre o imóvel'),
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
            'phone.required' => __('Informe seu WhatsApp com DDD para a equipe entrar em contato.'),
        ];
    }
}
