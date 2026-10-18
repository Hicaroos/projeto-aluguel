<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\PayoutMode;
use App\Enums\PayoutRule;
use App\Enums\PayoutSchedule;
use App\Enums\ShowcaseColor;
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
 * @property bool $showcase_enabled Whether the public showcase of the account's properties is online.
 * @property string|null $showcase_slug The address of the showcase, after the site's domain.
 * @property string|null $showcase_whatsapp The WhatsApp number visitors talk to, as digits.
 * @property ShowcaseColor|null $showcase_color
 * @property string|null $showcase_headline The showcase title, in place of the one written from the account's city.
 * @property string $sale_commission_percent The commission the agency usually charges on sales.
 * @property string $admin_fee_percent Payout rule defaults, see PayoutRule.
 * @property string $intermediation_fee_percent
 * @property string $renewal_fee_percent
 * @property bool $admin_fee_with_intermediation
 * @property bool $admin_fee_on_extra_charges
 * @property bool $extra_charges_to_owner
 * @property string $late_charges_owner_percent
 * @property PayoutMode $payout_mode
 * @property PayoutSchedule $payout_schedule
 * @property int $payout_days
 * @property int $payout_day
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
    'showcase_enabled',
    'showcase_slug',
    'showcase_whatsapp',
    'showcase_color',
    'showcase_headline',
    'sale_commission_percent',
    'admin_fee_percent',
    'intermediation_fee_percent',
    'renewal_fee_percent',
    'admin_fee_with_intermediation',
    'admin_fee_on_extra_charges',
    'extra_charges_to_owner',
    'late_charges_owner_percent',
    'payout_mode',
    'payout_schedule',
    'payout_days',
    'payout_day',
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
     * @return HasMany<DealStage, $this>
     */
    public function dealStages(): HasMany
    {
        return $this->hasMany(DealStage::class);
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
     * Find the account of a showcase address only while its showcase is online.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->newQuery()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->when($field === 'showcase_slug', fn ($query) => $query->where('showcase_enabled', true))
            ->first();
    }

    /**
     * Determine whether the account's public showcase is online.
     */
    public function hasShowcase(): bool
    {
        return $this->showcase_enabled && $this->showcase_slug !== null;
    }

    /**
     * Get the accent color of the showcase.
     */
    public function showcaseColor(): ShowcaseColor
    {
        return $this->showcase_color ?? ShowcaseColor::Forest;
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
            'showcase_enabled' => 'boolean',
            'showcase_color' => ShowcaseColor::class,
            'sale_commission_percent' => 'decimal:2',
            ...PayoutRule::casts(),
        ];
    }
}
