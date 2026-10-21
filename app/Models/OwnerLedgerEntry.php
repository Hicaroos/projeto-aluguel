<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\LedgerEntryType;
use Database\Factories\OwnerLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An entry of an owner's running account with the agency (conta-corrente).
 *
 * @property int $id
 * @property int $account_id
 * @property int $owner_id
 * @property int|null $property_id
 * @property int|null $lease_id
 * @property int|null $payment_id
 * @property int|null $receipt_id
 * @property int|null $expense_id
 * @property int|null $sale_id
 * @property int|null $owner_payout_id
 * @property int|null $user_id Who entered an adjustment or payout by hand.
 * @property LedgerEntryType $type
 * @property string $description
 * @property string $amount Positive when it is the owner's, negative when it is taken from them.
 * @property string|null $base_amount What a fee or share was worked out from.
 * @property string|null $rate_percent The fee rate applied, as it was then.
 * @property string|null $share_percent The share of the property the owner held, as it was then.
 * @property bool $is_guaranteed Rent credited by the agency before, or without, the tenant paying.
 * @property Carbon $occurred_on
 * @property Carbon $available_on When the owner may be paid it.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Owner $owner
 * @property-read Property|null $property
 * @property-read Payment|null $payment
 * @property-read OwnerPayout|null $ownerPayout
 * @property-read User|null $user
 */
#[Fillable([
    'account_id',
    'owner_id',
    'property_id',
    'lease_id',
    'payment_id',
    'receipt_id',
    'expense_id',
    'sale_id',
    'owner_payout_id',
    'user_id',
    'type',
    'description',
    'amount',
    'base_amount',
    'rate_percent',
    'share_percent',
    'is_guaranteed',
    'occurred_on',
    'available_on',
])]
class OwnerLedgerEntry extends Model
{
    /** @use HasFactory<OwnerLedgerEntryFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * @return BelongsTo<Owner, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<OwnerPayout, $this>
     */
    public function ownerPayout(): BelongsTo
    {
        return $this->belongsTo(OwnerPayout::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope the query to the entries the owner may already be paid.
     *
     * @param  Builder<OwnerLedgerEntry>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->whereDate($query->qualifyColumn('available_on'), '<=', today());
    }

    /**
     * Scope the query to the entries still waiting for their payout date.
     *
     * @param  Builder<OwnerLedgerEntry>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereDate($query->qualifyColumn('available_on'), '>', today());
    }

    /**
     * Determine whether the entry was entered by hand and may be removed.
     */
    public function isAdjustment(): bool
    {
        return $this->type === LedgerEntryType::Adjustment;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'rate_percent' => 'decimal:2',
            'share_percent' => 'decimal:2',
            'is_guaranteed' => 'boolean',
            'occurred_on' => 'date:Y-m-d',
            'available_on' => 'date:Y-m-d',
        ];
    }
}
