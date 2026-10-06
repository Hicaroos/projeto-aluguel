<?php

namespace App\Http\Requests;

use App\Actions\ContractTemplates\SanitizeContractTemplate;
use App\Enums\ContractVariable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ContractTemplateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'body' => [
                'required',
                'string',
                'max:'.SanitizeContractTemplate::MAX_LENGTH,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    preg_match_all('/data-variable="([^"]*)"/', $value, $matches);

                    foreach ($matches[1] as $key) {
                        if (ContractVariable::tryFrom($key) === null) {
                            $fail(__('O modelo usa uma variável desconhecida: :key.', ['key' => $key]));

                            return;
                        }
                    }

                    if (trim(strip_tags((new SanitizeContractTemplate)->handle($value))) === '') {
                        $fail(__('Escreva o texto do contrato.'));
                    }
                },
            ],
        ];
    }

    /**
     * Get the template attributes, with the body reduced to the formatting the editor allows.
     *
     * @return array{name: string, body: string}
     */
    public function templateAttributes(SanitizeContractTemplate $sanitizeContractTemplate): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'body' => $sanitizeContractTemplate->handle($this->string('body')->toString()),
        ];
    }
}
