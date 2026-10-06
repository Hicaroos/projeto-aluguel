<?php

namespace App\Models;

use App\Enums\MaritalStatus;
use Database\Factories\GuarantorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lease_id
 * @property string $name
 * @property string|null $cpf_cnpj
 * @property string|null $rg
 * @property string|null $nationality
 * @property MaritalStatus|null $marital_status
 * @property string|null $profession
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $zip_code
 * @property string|null $street
 * @property string|null $number
 * @property string|null $complement
 * @property string|null $neighborhood
 * @property string|null $city
 * @property string|null $state
 * @property string|null $spouse_name
 * @property string|null $spouse_cpf
 * @property string|null $property_registration
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lease $lease
 */
#[Fillable([
    'lease_id',
    'name',
    'cpf_cnpj',
    'rg',
    'nationality',
    'marital_status',
    'profession',
    'email',
    'phone',
    'zip_code',
    'street',
    'number',
    'complement',
    'neighborhood',
    'city',
    'state',
    'spouse_name',
    'spouse_cpf',
    'property_registration',
])]
class Guarantor extends Model
{
    /** @use HasFactory<GuarantorFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Lease, $this>
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'marital_status' => MaritalStatus::class,
        ];
    }
}
