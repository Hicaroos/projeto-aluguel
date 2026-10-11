<?php

namespace App\Actions\ContractTemplates;

use App\Enums\ContractTemplateKind;
use App\Models\Account;
use App\Models\ContractTemplate;
use Illuminate\Support\Facades\File;

class EnsureDefaultContractTemplate
{
    /**
     * Give the account the standard template of the given kind when it has none of that kind yet,
     * and return its default template of that kind.
     */
    public function handle(Account $account, ContractTemplateKind $kind = ContractTemplateKind::Lease): ContractTemplate
    {
        $default = $account->contractTemplates()
            ->where('kind', $kind)
            ->orderByDesc('is_default')
            ->oldest('id')
            ->first();

        if ($default !== null) {
            return $default;
        }

        return $account->contractTemplates()->create([
            'kind' => $kind,
            'name' => $kind->defaultTemplateName(),
            'body' => File::get($kind->defaultTemplatePath()),
            'is_default' => true,
        ]);
    }
}
