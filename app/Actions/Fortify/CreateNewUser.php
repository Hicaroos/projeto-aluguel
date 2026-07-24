<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $isSingleOwner = fn (): bool => ($input['account_type'] ?? null) === AccountType::SingleOwner->value;

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'account_name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'owner_cpf_cnpj' => [Rule::requiredIf($isSingleOwner), 'nullable', 'string', 'max:20'],
            'owner_phone' => ['nullable', 'string', 'max:20'],
        ])->validate();

        return DB::transaction(function () use ($input, $isSingleOwner): User {
            $account = Account::create([
                'name' => $input['account_name'],
                'type' => AccountType::from($input['account_type']),
            ]);

            if ($isSingleOwner()) {
                $account->owners()->create([
                    'name' => $input['name'],
                    'cpf_cnpj' => $input['owner_cpf_cnpj'],
                    'email' => $input['email'],
                    'phone' => $input['owner_phone'] ?? null,
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
