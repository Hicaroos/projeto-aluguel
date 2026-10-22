<?php

namespace App\Concerns;

use App\Models\SentMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Keep the WhatsApp messages the team opened about the record, so they see who already sent one and when.
 *
 * @mixin Model
 */
trait HasSentMessages
{
    /**
     * Remove the messages together with the record.
     */
    public static function bootHasSentMessages(): void
    {
        static::deleted(fn (Model $model) => SentMessage::query()
            ->where('messageable_type', $model->getMorphClass())
            ->where('messageable_id', $model->getKey())
            ->delete());
    }

    /**
     * @return MorphMany<SentMessage, $this>
     */
    public function sentMessages(): MorphMany
    {
        return $this->morphMany(SentMessage::class, 'messageable');
    }

    /**
     * @return MorphOne<SentMessage, $this>
     */
    public function latestSentMessage(): MorphOne
    {
        return $this->morphOne(SentMessage::class, 'messageable')->latestOfMany();
    }
}
