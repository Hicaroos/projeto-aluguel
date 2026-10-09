<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesBrazilianNumbers;
use App\Concerns\PersonQualificationRules;
use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TenantRequest extends FormRequest
{
    use NormalizesBrazilianNumbers, PersonQualificationRules;

    /**
     * Whether the document belongs to a tenant of a branch the user does not see.
     */
    private bool $isRegisteredInAnotherBranch = false;

    /**
     * Store the document and phone as digits only, however they were typed.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf_cnpj' => $this->digitsOnly($this->input('cpf_cnpj')),
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
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');

        return [
            'name' => ['required', 'string', 'max:255'],
            'cpf_cnpj' => [
                'required',
                'string',
                self::DOCUMENT_RULE,
                function (string $attribute, mixed $value, Closure $fail) use ($tenant): void {
                    $this->ensureDocumentIsNotTaken((string) $value, $tenant, $fail);
                },
            ],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', self::PHONE_RULE],
            ...$this->qualificationRules(),
        ];
    }

    /**
     * Tenants are shared by the whole agency, so a document can only be registered once. When the
     * tenant was registered by a branch the user does not see, flag it so the form can offer to
     * bring that tenant to the user's branch instead.
     */
    private function ensureDocumentIsNotTaken(string $document, ?Tenant $tenant, Closure $fail): void
    {
        $existingId = Tenant::withoutGlobalScope(Tenant::BRANCH_SCOPE)
            ->withTrashed()
            ->where('cpf_cnpj', $document)
            ->when($tenant, fn (Builder $query) => $query->whereKeyNot($tenant->id))
            ->value('id');

        if ($existingId === null) {
            return;
        }

        $this->isRegisteredInAnotherBranch = Tenant::withoutGlobalScope(Tenant::BRANCH_SCOPE)->whereKey($existingId)->exists()
            && ! Tenant::whereKey($existingId)->exists();

        $fail($this->isRegisteredInAnotherBranch
            ? __('Este CPF/CNPJ já está cadastrado em outra unidade.')
            : __('Já existe um inquilino com este CPF/CNPJ.'));
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->isRegisteredInAnotherBranch) {
                    $validator->errors()->add('registered_elsewhere', 'true');
                }
            },
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
            'phone' => __('celular'),
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
            ...$this->brazilianNumberMessages(documents: ['cpf_cnpj'], phones: ['phone']),
        ];
    }
}
