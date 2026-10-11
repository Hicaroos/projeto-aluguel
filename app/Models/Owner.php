<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Concerns\SharedAcrossBranches;
use App\Enums\MaritalStatus;
use App\Enums\PayoutMode;
use App\Enums\PayoutRule;
use App\Enums\PayoutSchedule;
use Database\Factories\OwnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property string|null $admin_fee_percent Payout rules set otherwise for the owner, see PayoutRule.
 * @property string|null $intermediation_fee_percent
 * @property string|null $renewal_fee_percent
 * @property bool|null $admin_fee_with_intermediation
 * @property bool|null $admin_fee_on_extra_charges
 * @property bool|null $extra_charges_to_owner
 * @property string|null $late_charges_owner_percent
 * @property PayoutMode|null $payout_mode
 * @property PayoutSchedule|null $payout_schedule
 * @property int|null $payout_days
 * @property int|null $payout_day
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Collection<int, Property> $properties
 * @property-read Collection<int, Branch> $branches
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
])]
class Owner extends Model
{
    /** @use HasFactory<OwnerFactory> */
    use BelongsToAccount, HasFactory, SharedAcrossBranches, SoftDeletes;

    /**
     * Get the properties the owner owns, wholly or in part, with the share they hold.
     *
     * @return BelongsToMany<Property, $this, OwnerProperty, 'pivot'>
     */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class)->using(OwnerProperty::class)->withPivot('share_percent')->withTimestamps();
    }

    /**
     * Scope the query to owners whose name, document, e-mail or phone matches the given term.
     *
     * @param  Builder<Owner>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->when($term !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('cpf_cnpj', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        }));
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
            ...PayoutRule::casts(),
        ];
    }
}
