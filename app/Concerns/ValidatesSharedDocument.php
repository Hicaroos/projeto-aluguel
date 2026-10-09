<?php

namespace App\Concerns;

use App\Models\Owner;
use App\Models\Tenant;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

/**
 * Tenants and owners are shared by the whole agency, so a document can only be registered once.
 * When the person was registered by a branch the user does not see, the request flags it with a
 * `registered_elsewhere` error, so the form can offer to bring that person to the user's branch.
 */
trait ValidatesSharedDocument
{
    /**
     * Whether the document belongs to someone of a branch the user does not see.
     */
    private bool $isRegisteredInAnotherBranch = false;

    /**
     * Get the rule refusing a document already registered for another person.
     *
     * @param  class-string<Tenant|Owner>  $model
     */
    protected function documentIsNotTaken(string $model, ?Model $current, string $takenMessage): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($model, $current, $takenMessage): void {
            $existingId = $model::withoutGlobalScope($model::BRANCH_SCOPE)
                ->withTrashed()
                ->where('cpf_cnpj', (string) $value)
                ->when($current, fn (Builder $query) => $query->whereKeyNot($current->getKey()))
                ->value('id');

            if ($existingId === null) {
                return;
            }

            $this->isRegisteredInAnotherBranch = $model::withoutGlobalScope($model::BRANCH_SCOPE)->whereKey($existingId)->exists()
                && ! $model::whereKey($existingId)->exists();

            $fail($this->isRegisteredInAnotherBranch
                ? __('Este CPF/CNPJ já está cadastrado em outra unidade.')
                : $takenMessage);
        };
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
}
