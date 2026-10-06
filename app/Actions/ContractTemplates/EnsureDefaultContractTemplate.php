<?php

namespace App\Actions\ContractTemplates;

use App\Models\Account;
use App\Models\ContractTemplate;
use Illuminate\Support\Facades\File;

class EnsureDefaultContractTemplate
{
    /**
     * Give the account the standard residential template when it has no templates yet,
     * and return its default template.
     */
    public function handle(Account $account): ContractTemplate
    {
        $default = $account->contractTemplates()->orderByDesc('is_default')->oldest('id')->first();

        if ($default !== null) {
            return $default;
        }

        return $account->contractTemplates()->create([
            'name' => 'Contrato residencial padrão',
            'body' => File::get(resource_path('views/contracts/default-template.html')),
            'is_default' => true,
        ]);
    }
}
