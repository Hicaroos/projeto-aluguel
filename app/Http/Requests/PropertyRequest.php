<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
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
        $isAgency = $this->user()->account?->type === AccountType::Agency;

        /** @var Property|null $property */
        $property = $this->route('property');
        $isRented = $property?->status === PropertyStatus::Rented;

        return [
            'type' => ['required', Rule::enum(PropertyType::class)],
            'owner_id' => [
                Rule::requiredIf($isAgency),
                'nullable',
                'integer',
                Rule::exists('owners', 'id')->where('account_id', $this->user()->account_id),
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
}
