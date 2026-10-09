<?php

namespace App\Actions\Fortify;

use App\Concerns\NormalizesBrazilianNumbers;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use NormalizesBrazilianNumbers, PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * Single owners are both the user and the owner of the properties, so their account is
     * named after them. Agencies are companies: they name the account and give their CNPJ.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $isSingleOwner = ($input['account_type'] ?? null) === AccountType::SingleOwner->value;
        $isAgency = ($input['account_type'] ?? null) === AccountType::Agency->value;

        $input = [
            ...$input,
            'owner_cpf_cnpj' => $this->digitsOnly($input['owner_cpf_cnpj'] ?? null),
            'owner_phone' => $this->digitsOnly($input['owner_phone'] ?? null),
            'agency_document' => $this->digitsOnly($input['agency_document'] ?? null),
            'agency_phone' => $this->digitsOnly($input['agency_phone'] ?? null),
        ];

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'account_name' => [Rule::requiredIf($isAgency), 'nullable', 'string', 'max:255'],
            'owner_cpf_cnpj' => [Rule::requiredIf($isSingleOwner), 'nullable', 'string', self::DOCUMENT_RULE],
            'owner_phone' => [Rule::requiredIf($isSingleOwner), 'nullable', 'string', self::PHONE_RULE],
            'agency_document' => [Rule::requiredIf($isAgency), 'nullable', 'string', self::CNPJ_RULE],
            'agency_creci' => ['nullable', 'string', 'max:20'],
            'agency_phone' => [Rule::requiredIf($isAgency), 'nullable', 'string', self::PHONE_RULE],
        ], $this->brazilianNumberMessages(
            documents: ['owner_cpf_cnpj'],
            phones: ['owner_phone', 'agency_phone'],
            cnpjs: ['agency_document'],
        ), [
            'account_name' => __('nome da imobiliária'),
            'owner_cpf_cnpj' => __('CPF ou CNPJ'),
            'owner_phone' => __('celular'),
            'agency_document' => __('CNPJ'),
            'agency_creci' => __('CRECI'),
            'agency_phone' => __('telefone'),
        ])->validate();

        return DB::transaction(function () use ($input, $isSingleOwner): User {
            $account = Account::create($isSingleOwner
                ? ['name' => $input['name'], 'type' => AccountType::SingleOwner]
                : [
                    'name' => $input['account_name'],
                    'type' => AccountType::Agency,
                    'document' => $input['agency_document'],
                    'creci' => $input['agency_creci'] ?? null,
                    'phone' => $input['agency_phone'],
                ]);

            if ($isSingleOwner) {
                $account->owners()->create([
                    'name' => $input['name'],
                    'cpf_cnpj' => $input['owner_cpf_cnpj'],
                    'email' => $input['email'],
                    'phone' => $input['owner_phone'],
                ]);
            } else {
                $account->branches()->create([
                    'name' => Branch::MAIN_BRANCH_NAME,
                    'phone' => $input['agency_phone'],
                ]);
            }

            return $account->users()->create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
        });
    }
}
