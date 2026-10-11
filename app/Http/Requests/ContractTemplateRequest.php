<?php

namespace App\Http\Requests;

use App\Actions\ContractTemplates\SanitizeContractTemplate;
use App\Enums\ContractTemplateKind;
use App\Enums\ContractVariable;
use App\Models\ContractTemplate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractTemplateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The kind is chosen when the template is created and never changes.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => $this->template() === null ? ['nullable', Rule::enum(ContractTemplateKind::class)] : ['exclude'],
            'name' => ['required', 'string', 'max:255'],
            'body' => self::bodyRules($this->kind()),
        ];
    }

    /**
     * Get the rules for a template body: variables of its kind only and some text left after sanitizing.
     *
     * @return array<int, Closure|string>
     */
    public static function bodyRules(ContractTemplateKind $kind = ContractTemplateKind::Lease): array
    {
        return [
            'required',
            'string',
            'max:'.SanitizeContractTemplate::MAX_LENGTH,
            function (string $attribute, mixed $value, Closure $fail) use ($kind): void {
                if (! is_string($value)) {
                    return;
                }

                preg_match_all('/data-variable="([^"]*)"/', $value, $matches);

                foreach ($matches[1] as $key) {
                    $variable = ContractVariable::tryFrom($key);

                    if ($variable === null) {
                        $fail(__('O modelo usa uma variável desconhecida: :key.', ['key' => $key]));

                        return;
                    }

                    if (! in_array($kind, $variable->kinds(), true)) {
                        $fail(__('A variável ":label" não pode ser usada neste tipo de modelo.', ['label' => $variable->label()]));

                        return;
                    }
                }

                if (trim(strip_tags((new SanitizeContractTemplate)->handle($value))) === '') {
                    $fail(__('Escreva o texto do modelo.'));
                }
            },
        ];
    }

    /**
     * Get the kind of the template: the one being edited, or the one chosen for a new template.
     */
    public function kind(): ContractTemplateKind
    {
        return $this->template()->kind
            ?? $this->enum('kind', ContractTemplateKind::class)
            ?? ContractTemplateKind::Lease;
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

    /**
     * Get the template being updated, if any.
     */
    private function template(): ?ContractTemplate
    {
        $template = $this->route('contract_template');

        return $template instanceof ContractTemplate ? $template : null;
    }
}
