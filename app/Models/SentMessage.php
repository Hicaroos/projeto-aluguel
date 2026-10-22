<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\MessageTemplate;
use Database\Factories\SentMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A WhatsApp message someone of the team opened to send from their own phone. The app only knows the
 * message was opened, not whether it was really sent.
 *
 * @property int $id
 * @property int $account_id
 * @property int|null $user_id Who opened the message.
 * @property string $messageable_type
 * @property int $messageable_id The payment, payout or rent adjustment the message is about.
 * @property MessageTemplate $template
 * @property string $phone The number the message went to, as digits with the country code.
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Payment|OwnerPayout|LeaseAdjustment $messageable
 */
#[Fillable(['account_id', 'user_id', 'messageable_type', 'messageable_id', 'template', 'phone', 'body'])]
class SentMessage extends Model
{
    /** @use HasFactory<SentMessageFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template' => MessageTemplate::class,
        ];
    }
}
