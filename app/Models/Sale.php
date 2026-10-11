<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Concerns\BelongsToVisibleBranches;
use App\Enums\MaritalStatus;
use App\Enums\PaymentPlan;
use App\Enums\RentalOutcome;
use Carbon\CarbonInterface;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A property sold: who bought it, who sold it, for how much and the commission earned.
 *
 * @property int $id
 * @property int $account_id
 * @property int $property_id
 * @property int|null $deal_id
 * @property int|null $lead_id
 * @property int|null $seller_owner_id The owner who sold the property.
 * @property int|null $user_id The team member who sold the property.
 * @property string $buyer_name
 * @property string|null $buyer_document
 * @property string|null $buyer_phone
 * @property string|null $buyer_email
 * @property string|null $buyer_rg
 * @property string|null $buyer_nationality
 * @property MaritalStatus|null $buyer_marital_status
 * @property string|null $buyer_profession
 * @property string|null $buyer_zip_code
 * @property string|null $buyer_street
 * @property string|null $buyer_number
 * @property string|null $buyer_complement
 * @property string|null $buyer_neighborhood
 * @property string|null $buyer_city
 * @property string|null $buyer_state
 * @property string|null $down_payment The amount the buyer paid upfront to secure the deal (sinal).
 * @property Carbon|null $deed_deadline The date by which the deed must be signed.
 * @property string $price
 * @property Carbon $sold_on
 * @property PaymentPlan|null $payment_plan
 * @property string|null $commission_percent
 * @property string|null $commission_amount
 * @property string|null $notes
 * @property RentalOutcome|null $rental_outcome What happened to the lease of a rented property.
 * @property bool $property_was_for_rent Whether the property was also for rent, to restore it if the sale is undone.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Property $property
 * @property-read Deal|null $deal
 * @property-read Owner|null $seller
 * @property-read User|null $user
 */
#[Fillable([
    'account_id',
    'property_id',
    'deal_id',
    'lead_id',
    'seller_owner_id',
    'user_id',
    'buyer_name',
    'buyer_document',
    'buyer_phone',
    'buyer_email',
    'buyer_rg',
    'buyer_nationality',
    'buyer_marital_status',
    'buyer_profession',
    'buyer_zip_code',
    'buyer_street',
    'buyer_number',
    'buyer_complement',
    'buyer_neighborhood',
    'buyer_city',
    'buyer_state',
    'down_payment',
    'deed_deadline',
    'price',
    'sold_on',
    'payment_plan',
    'commission_percent',
    'commission_amount',
    'notes',
    'property_was_for_rent',
    'rental_outcome',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use BelongsToAccount, BelongsToVisibleBranches, HasFactory;

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * @return BelongsTo<Owner, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'seller_owner_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope the query to the sales closed within the given month.
     *
     * @param  Builder<Sale>  $query
     */
    public function scopeSoldInMonth(Builder $query, CarbonInterface $month): void
    {
        $query->whereBetween('sold_on', [
            $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
        ]);
    }

    /**
     * Limit the query to the sales of the properties of the given branches.
     *
     * @param  Builder<static>  $query
     * @param  array<int, int>  $branchIds
     */
    public static function restrictToBranches(Builder $query, array $branchIds): void
    {
        $query->whereIn($query->qualifyColumn('property_id'), Property::idsInBranches($branchIds));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sold_on' => 'date:Y-m-d',
            'payment_plan' => PaymentPlan::class,
            'commission_percent' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'buyer_marital_status' => MaritalStatus::class,
            'down_payment' => 'decimal:2',
            'deed_deadline' => 'date:Y-m-d',
            'property_was_for_rent' => 'boolean',
            'rental_outcome' => RentalOutcome::class,
        ];
    }
}
