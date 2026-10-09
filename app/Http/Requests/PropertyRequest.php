<?php

namespace App\Http\Requests;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\Owner;
use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isAgency = $this->user()->account?->isAgency() ?? false;
        $isRented = $this->property()?->status === PropertyStatus::Rented;

        return [
            'type' => ['required', Rule::enum(PropertyType::class)],
            'branch_id' => $isAgency
                ? ['required', 'integer', Rule::in($this->selectableBranchIds())]
                : ['exclude'],
            'owner_id' => [
                Rule::requiredIf($isAgency),
                'nullable',
                'integer',
                Rule::exists('owners', 'id')->where('account_id', $this->user()->account_id)->withoutTrashed()
                    ->where(fn (QueryBuilder $query) => $query->whereIn('id', Owner::query()->select('id'))),
            ],
            'zip_code' => ['required', 'string', 'max:9'],
            'street' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'status' => $isRented
                ? ['exclude']
                : ['required', Rule::enum(PropertyStatus::class)->except([PropertyStatus::Rented])],
        ];
    }

    /**
     * Get the owner the property belongs to.
     *
     * Agencies choose the owner in the form; single owner accounts always use their own owner.
     */
    public function ownerId(): ?int
    {
        $account = $this->user()->account;

        if ($account?->isAgency()) {
            return (int) $this->validated('owner_id');
        }

        return $this->property()->owner_id ?? $account?->primaryOwner()?->id;
    }

    /**
     * Get the branches the property may be placed in: the active branches the user may access,
     * plus the branch it is already in.
     *
     * @return array<int, int>
     */
    public function selectableBranchIds(): array
    {
        $currentBranchId = $this->property()?->branch_id;

        return Branch::query()
            ->accessibleBy($this->user())
            ->where(fn (Builder $query) => $query->active()->when($currentBranchId, fn (Builder $query, int $id) => $query->orWhere('id', $id)))
            ->get(['id'])
            ->map(fn (Branch $branch): int => $branch->id)
            ->all();
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => __('Selecione a unidade do imóvel.'),
            'branch_id.in' => __('Selecione uma unidade ativa.'),
        ];
    }

    /**
     * Get the property being updated, if any.
     */
    private function property(): ?Property
    {
        $property = $this->route('property');

        return $property instanceof Property ? $property : null;
    }
}
