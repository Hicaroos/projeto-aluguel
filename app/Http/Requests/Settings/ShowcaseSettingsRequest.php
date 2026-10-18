<?php

namespace App\Http\Requests\Settings;

use App\Concerns\NormalizesBrazilianNumbers;
use App\Enums\ShowcaseColor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route as AppRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShowcaseSettingsRequest extends FormRequest
{
    use NormalizesBrazilianNumbers;

    /**
     * Addresses kept for the app itself or for later, besides those its routes already use.
     *
     * @var list<string>
     */
    private const array RESERVED_SLUGS = ['admin', 'api', 'app', 'build', 'imoveis', 'login', 'register', 'storage', 'vitrine', 'www'];

    /**
     * Read the address in lowercase and the WhatsApp as digits only, however they were typed.
     */
    protected function prepareForValidation(): void
    {
        $slug = $this->input('showcase_slug');

        $this->merge([
            'showcase_enabled' => $this->boolean('showcase_enabled'),
            'showcase_slug' => is_string($slug) && trim($slug) !== '' ? Str::lower(trim($slug)) : null,
            'showcase_whatsapp' => $this->digitsOnly($this->input('showcase_whatsapp')),
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
            'showcase_enabled' => ['boolean'],
            'showcase_slug' => [
                Rule::requiredIf($this->boolean('showcase_enabled')),
                'nullable',
                'string',
                'min:3',
                'max:40',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn($this->reservedSlugs()),
                Rule::unique('accounts', 'showcase_slug')->ignore($this->user()->account_id),
            ],
            'showcase_whatsapp' => [Rule::requiredIf($this->boolean('showcase_enabled')), 'nullable', 'string', self::PHONE_RULE],
            'showcase_color' => ['required', Rule::enum(ShowcaseColor::class)],
            'showcase_headline' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * Get the addresses a showcase can't take: the first part of every address of the app, plus a few kept aside.
     *
     * @return list<string>
     */
    private function reservedSlugs(): array
    {
        return array_values(collect(Route::getRoutes()->getRoutes())
            ->map(fn (AppRoute $route): string => Str::before(ltrim($route->uri(), '/'), '/'))
            ->reject(fn (string $segment): bool => $segment === '' || str_starts_with($segment, '{'))
            ->merge(self::RESERVED_SLUGS)
            ->unique()
            ->all());
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'showcase_slug' => __('endereço da vitrine'),
            'showcase_whatsapp' => __('WhatsApp'),
            'showcase_color' => __('cor'),
            'showcase_headline' => __('título'),
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
            ...$this->brazilianNumberMessages(phones: ['showcase_whatsapp']),
            'showcase_slug.regex' => __('Use só letras minúsculas, números e hífens, como "imobiliaria-silva".'),
            'showcase_slug.not_in' => __('Esse endereço é usado pelo sistema. Escolha outro.'),
            'showcase_slug.unique' => __('Esse endereço já é de outra vitrine. Escolha outro.'),
        ];
    }
}
