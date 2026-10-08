<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\MaritalStatus;
use Database\Factories\OwnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $account_id
 * @property string $name
 * @property string|null $cpf_cnpj
 * @property string|null $email
 * @property string|null $rg
 * @property string|null $nationality
 * @property MaritalStatus|null $marital_status
 * @property string|null $profession
 * @property string|null $phone
 * @property string|null $zip_code
 * @property string|null $street
 * @property string|null $number
 * @property string|null $complement
 * @property string|null $neighborhood
 * @property string|null $city
 * @property string|null $state
 * @property string|null $pix_key
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Collection<int, Property> $properties
 */
#[Fillable([
    'account_id',
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
    'pix_key',
])]
class Owner extends Model
{
    /** @use HasFactory<OwnerFactory> */
    use BelongsToAccount, HasFactory, SoftDeletes;

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
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
