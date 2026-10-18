<?php

namespace App\Http\Requests\Settings;

use App\Enums\ShowcaseText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowcaseTextsRequest extends FormRequest
{
    /**
     * The longest home title the account may write.
     */
    public const int HEADLINE_MAX_LENGTH = 120;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'showcase_headline' => ['nullable', 'string', 'max:'.self::HEADLINE_MAX_LENGTH],
            'showcase_texts' => ['array'],
            ...collect(ShowcaseText::cases())
                ->mapWithKeys(fn (ShowcaseText $text): array => ["showcase_texts.{$text->value}" => ['nullable', 'string', "max:{$text->maxLength()}"]])
                ->all(),
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
            'showcase_headline' => __('título'),
            'showcase_texts.*' => __('texto'),
        ];
    }
}
