<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name The trade name, as people know the agency.
 * @property string|null $legal_name The registered company name (razão social).
 * @property AccountType $type
 * @property string|null $document The agency CNPJ, as digits.
 * @property string|null $creci The agency CRECI registration.
 * @property string|null $phone The agency phone, as digits.
 * @property string|null $email
 * @property string|null $zip_code
 * @property string|null $street
 * @property string|null $number
 * @property string|null $complement
 * @property string|null $neighborhood
 * @property string|null $city
 * @property string|null $state
 * @property string|null $logo_path
 * @property string|null $plan
 * @property AccountStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Branch> $branches
 * @property-read Collection<int, Owner> $owners
 * @property-read Collection<int, Tenant> $tenants
 */
#[Fillable([
    'name',
    'legal_name',
    'type',
    'document',
    'creci',
    'phone',
    'email',
    'zip_code',
    'street',
    'number',
    'complement',
    'neighborhood',
    'city',
    'state',
    'logo_path',
    'plan',
    'status',
])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /**
     * The agency logo is a private file, served only to the account.
     */
    public const string LOGO_DISK = 'local';

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'trial',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['logo_path'];

    /**
     * @var list<string>
     */
    protected $appends = ['logo_url'];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasMany<Owner, $this>
     */
    public function owners(): HasMany
    {
        return $this->hasMany(Owner::class);
    }

    /**
     * @return HasMany<Tenant, $this>
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * @return HasMany<ContractTemplate, $this>
     */
    public function contractTemplates(): HasMany
    {
        return $this->hasMany(ContractTemplate::class);
    }

    /**
     * Determine whether the account is a real estate agency managing many owners.
     */
    public function isAgency(): bool
    {
        return $this->type === AccountType::Agency;
    }

    /**
     * Get the owner that represents a single owner account.
     */
    public function primaryOwner(): ?Owner
    {
        return $this->owners()->oldest('id')->first();
    }

    /**
     * Get the address of the agency logo, changing whenever the account is saved so browsers fetch a new logo.
     *
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->logo_path === null
            ? null
            : route('agency.logo', ['v' => $this->updated_at?->timestamp]));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'status' => AccountStatus::class,
        ];
    }
}
