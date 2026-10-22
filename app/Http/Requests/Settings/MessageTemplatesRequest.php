<?php

namespace App\Http\Requests\Settings;

use App\Enums\MessageTemplate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MessageTemplatesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $account = $this->user()->account;

        return [
            'message_templates' => ['array'],
            ...collect(MessageTemplate::cases())
                ->mapWithKeys(fn (MessageTemplate $template): array => ["message_templates.{$template->value}" => [
                    'nullable',
                    'string',
                    'max:'.MessageTemplate::MAX_LENGTH,
                    function (string $attribute, mixed $value, Closure $fail) use ($template, $account): void {
                        preg_match_all('/\{(\w+)\}/', (string) $value, $matches);

                        $unknown = array_diff(array_unique($matches[1]), array_keys($template->variables($account)));

                        if ($unknown !== []) {
                            $fail(__('Esta mensagem não tem a variável :variables.', [
                                'variables' => collect($unknown)->map(fn (string $key): string => "{{$key}}")->implode(', '),
                            ]));
                        }
                    },
                ]])
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
            'message_templates.*' => __('mensagem'),
        ];
    }
}
