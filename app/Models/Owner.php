<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Concerns\SharedAcrossBranches;
use App\Enums\MaritalStatus;
use App\Enums\PayoutMode;
use App\Enums\PayoutRule;
use App\Enums\PayoutSchedule;
use Closure;
use Database\Factories\OwnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
     * The global scope that limits properties, leases, payments, receipts, expenses and tenants
     * to the owner picked in the owner selector.
     */
    public const string SELECTION_SCOPE = 'owner';

    /**
     * The request attribute that turns the owner selection off, for the screens that always show everything.
     */
    public const string IGNORES_SELECTION_ATTRIBUTE = 'ignores_owner_selection';

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
     * Get the owner picked in the owner selector, whose records the whole app shows. Null shows every
     * owner, as on the screens that ignore the selection.
     */
    public static function selectedId(): ?int
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $attributes = request()->attributes;

        if ($attributes->get(self::IGNORES_SELECTION_ATTRIBUTE) === true) {
            return null;
        }

        $key = 'selected_owner_id.'.$user->id;

        if (! $attributes->has($key)) {
            $attributes->set($key, $user->selectedOwnerId());
        }

        return $attributes->get($key);
    }

    /**
     * Run the callback showing every owner, whatever the owner selector says, e.g. for the sales
     * figures, which don't belong to an owner.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function ignoringSelection(Closure $callback): mixed
    {
        $attributes = request()->attributes;
        $previous = $attributes->get(self::IGNORES_SELECTION_ATTRIBUTE);

        $attributes->set(self::IGNORES_SELECTION_ATTRIBUTE, true);

        try {
            return $callback();
        } finally {
            $attributes->set(self::IGNORES_SELECTION_ATTRIBUTE, $previous);
        }
    }

    /**
     * Forget the selected owner looked up for this request, after the selection changes.
     */
    public static function forgetSelectedId(): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            request()->attributes->remove('selected_owner_id.'.$user->id);
        }
    }

    /**
     * Get the share of each of the owner's properties they hold, as fractions keyed by property id,
     * e.g. [12 => 0.6] for a property they own 60% of.
     *
     * @return array<int, float>
     */
    public static function sharesOf(int $ownerId): array
    {
        $shares = DB::table('owner_property')
            ->where('owner_id', $ownerId)
            ->pluck('share_percent', 'property_id')
            ->map(fn (mixed $share): float => (float) $share / 100)
            ->all();

        $wholly = DB::table('properties')
            ->where('owner_id', $ownerId)
            ->whereNotIn('id', array_keys($shares))
            ->pluck('id')
            ->mapWithKeys(fn (int $id): array => [$id => 1.0])
            ->all();

        return $shares + $wholly;
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
