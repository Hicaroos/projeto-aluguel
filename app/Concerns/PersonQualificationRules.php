<?php

namespace App\Concerns;

use App\Enums\MaritalStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait PersonQualificationRules
{
    /**
     * Get the validation rules for the personal details a lease contract needs to identify a person:
     * documents, marital status, profession and address.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function qualificationRules(string $prefix = ''): array
    {
        $rules = [
            'rg' => ['nullable', 'string', 'max:30'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'profession' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:9'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
        ];

        return collect($rules)
            ->mapWithKeys(fn (array $fieldRules, string $field) => [$prefix.$field => $fieldRules])
            ->all();
    }
}
