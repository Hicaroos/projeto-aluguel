<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\ShowcaseRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request sent through the public showcase, kept apart from the lead's notes: anyone can send
 * one with any phone, so neither the name nor the phone are confirmed.
 *
 * @property int $id
 * @property int $account_id
 * @property int $lead_id The lead with the phone given in the request.
 * @property int|null $property_id The property asked about, for a visit.
 * @property string $name The name as given in the request.
 * @property string $summary What was asked, written by the app.
 * @property string|null $message What the person wrote, as written.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lead $lead
 * @property-read Property|null $property
 */
#[Fillable(['account_id', 'lead_id', 'property_id', 'name', 'summary', 'message'])]
class ShowcaseRequest extends Model
{
    /** @use HasFactory<ShowcaseRequestFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
