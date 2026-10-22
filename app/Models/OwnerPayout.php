<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Concerns\HasSentMessages;
use App\Enums\PaymentMethod;
use Database\Factories\OwnerPayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Money the agency paid an owner out of their running account (repasse).
 *
 * @property int $id
 * @property int $account_id
 * @property int $owner_id
 * @property int|null $user_id Who registered the payout.
 * @property string $amount
 * @property Carbon $paid_on
 * @property PaymentMethod|null $payment_method
 * @property string|null $notes
 * @property string|null $receipt_name The proof of transfer's file name, as uploaded.
 * @property string|null $receipt_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Owner $owner
 * @property-read User|null $user
 * @property-read SentMessage|null $latestSentMessage
 */
#[Fillable(['account_id', 'owner_id', 'user_id', 'amount', 'paid_on', 'payment_method', 'notes', 'receipt_name', 'receipt_path'])]
class OwnerPayout extends Model
{
    /** @use HasFactory<OwnerPayoutFactory> */
    use BelongsToAccount, HasFactory, HasSentMessages;

    /**
     * Proofs of transfer are private files, served only to their own account.
     */
    public const string DISK = 'local';

    /**
     * @var list<string>
     */
    protected $hidden = ['receipt_path'];

    /**
     * Remove the stored proof of transfer together with the payout.
     */
    protected static function booted(): void
    {
        static::deleted(function (OwnerPayout $payout): void {
            if ($payout->receipt_path !== null) {
                Storage::disk(self::DISK)->delete($payout->receipt_path);
            }
        });
    }

    /**
     * @return BelongsTo<Owner, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date:Y-m-d',
            'payment_method' => PaymentMethod::class,
        ];
    }
}
