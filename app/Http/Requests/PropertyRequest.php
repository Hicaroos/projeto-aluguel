<?php

namespace App\Http\Requests;

use App\Enums\PropertyAmenity;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\Owner;
use App\Models\Property;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PropertyRequest extends FormRequest
{
    /**
     * Read the purposes and the showcase choices as booleans. Properties are for rent unless told otherwise.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'for_rent' => $this->has('for_rent') ? $this->boolean('for_rent') : true,
            'for_sale' => $this->boolean('for_sale'),
            'is_published' => $this->boolean('is_published'),
            'is_featured' => $this->boolean('is_featured'),
            'amenities' => array_values(array_filter((array) $this->input('amenities', []))),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The status of rented and sold properties follows their lease or sale, so it is kept as it is.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isAgency = $this->user()->account?->isAgency() ?? false;
        $isStatusLocked = in_array($this->property()?->status, [PropertyStatus::Rented, PropertyStatus::Sold], true);

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
            'co_owners' => $isAgency ? ['array', 'max:20'] : ['exclude'],
            'co_owners.*.owner_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('owners', 'id')->where('account_id', $this->user()->account_id)->withoutTrashed()
                    ->where(fn (QueryBuilder $query) => $query->whereIn('id', Owner::query()->select('id'))),
            ],
            'co_owners.*.share_percent' => ['required', 'numeric', 'gt:0', 'lt:100'],
            'zip_code' => ['required', 'string', 'max:9'],
            'street' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2'],
            'for_rent' => ['boolean'],
            'for_sale' => ['boolean'],
            'rent_amount' => [Rule::requiredIf($this->boolean('for_rent')), 'nullable', 'numeric', 'min:0', 'max:9999999'],
            'sale_price' => [Rule::requiredIf($this->boolean('for_sale')), 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:99'],
            'suites' => ['nullable', 'integer', 'min:0', 'max:99'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:99'],
            'parking_spaces' => ['nullable', 'integer', 'min:0', 'max:99'],
            'built_area' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'land_area' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'land_front' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'land_depth' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amenities' => ['array'],
            'amenities.*' => ['distinct', Rule::enum(PropertyAmenity::class)],
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'status' => $isStatusLocked
                ? ['exclude']
                : ['required', Rule::enum(PropertyStatus::class)->except([PropertyStatus::Rented, PropertyStatus::Sold])],
        ];
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
                if (! $this->boolean('for_rent') && ! $this->boolean('for_sale')) {
                    $validator->errors()->add('for_rent', __('Marque se o imóvel é para alugar, para vender ou os dois.'));
                }

                /** @var array<int, mixed> $input */
                $input = (array) $this->input('co_owners', []);
                $coOwners = array_filter($input, is_array(...));

                if (in_array((int) $this->input('owner_id'), array_map(fn (array $coOwner): int => (int) ($coOwner['owner_id'] ?? 0), $coOwners), true)) {
                    $validator->errors()->add('co_owners', __('O proprietário principal já faz parte do imóvel. Escolha outra pessoa como coproprietária.'));
                }

                if (array_sum(array_map(fn (array $coOwner): float => (float) ($coOwner['share_percent'] ?? 0), $coOwners)) >= 100) {
                    $validator->errors()->add('co_owners', __('A soma das participações dos coproprietários precisa ficar abaixo de 100%, deixando uma parte para o proprietário principal.'));
                }
            },
        ];
    }

    /**
     * Get the share each co-owner holds, as percentages keyed by owner id. The main owner keeps the rest.
     *
     * @return array<int, float>
     */
    public function coOwnerShares(): array
    {
        /** @var list<array{owner_id: int|string, share_percent: float|string}> $coOwners */
        $coOwners = $this->validated('co_owners', []);

        return collect($coOwners)
            ->mapWithKeys(fn (array $coOwner): array => [(int) $coOwner['owner_id'] => round((float) $coOwner['share_percent'], 2)])
            ->all();
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
            'rent_amount.required' => __('Informe o valor do aluguel.'),
            'sale_price.required' => __('Informe o preço de venda.'),
            'branch_id.required' => __('Selecione a unidade do imóvel.'),
            'branch_id.in' => __('Selecione uma unidade ativa.'),
            'co_owners.*.owner_id.required' => __('Escolha o coproprietário.'),
            'co_owners.*.owner_id.distinct' => __('Este coproprietário aparece mais de uma vez.'),
            'co_owners.*.share_percent.required' => __('Informe a participação.'),
            'co_owners.*.share_percent.gt' => __('A participação precisa ser maior que zero.'),
            'co_owners.*.share_percent.lt' => __('A participação precisa ser menor que 100%.'),
        ];
    }

    /**
     * Get the property attributes to save: the validated ones, with the showcase publishing kept as the
     * moment the property first went on it.
     *
     * @return array<string, mixed>
     */
    public function propertyAttributes(): array
    {
        return [
            ...$this->safe()->except(['co_owners', 'is_published']),
            'published_at' => $this->boolean('is_published') ? ($this->property()->published_at ?? now()) : null,
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
