<?php

namespace App\Concerns;

use App\Enums\PayoutRule;
use App\Enums\Permission;

/**
 * Validation of the payout rules an owner or a lease sets otherwise than the agency defaults. Only
 * agency members who handle the money see and change them; for everyone else they are left as they are.
 */
trait PayoutRuleOverrideRules
{
    /**
     * Get the validation rules of the payout rules, a blank value meaning "same as above".
     *
     * @return array<string, array<int, mixed>>
     */
    protected function payoutRuleOverrideRules(): array
    {
        $user = $this->user();

        if (! ($user->account?->isAgency() ?? false) || ! $user->can(Permission::ManageFinance->value)) {
            return array_fill_keys(PayoutRule::columns(), ['exclude']);
        }

        return array_map(fn (array $rules): array => ['sometimes', ...$rules], PayoutRule::rulesFor(required: false));
    }
}
