<?php

namespace App\Models;

use Database\Factories\LeaseRenewalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An extension of a lease term, signed as an amendment to the contract.
 *
 * @property int $id
 * @property int $lease_id
 * @property Carbon $previous_end_date
 * @property Carbon $new_end_date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lease $lease
 */
#[Fillable(['lease_id', 'previous_end_date', 'new_end_date', 'notes'])]
class LeaseRenewal extends Model
{
    /** @use HasFactory<LeaseRenewalFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Lease, $this>
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_end_date' => 'date:Y-m-d',
            'new_end_date' => 'date:Y-m-d',
        ];
    }
}
