<?php

namespace App\Models;

use App\Enums\AdjustmentIndex;
use Database\Factories\LeaseAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An annual rent adjustment applied to a lease on its anniversary.
 *
 * @property int $id
 * @property int $lease_id
 * @property Carbon $effective_on The lease anniversary the adjustment applies from.
 * @property AdjustmentIndex $adjustment_index The index the percent came from, as set on the lease then.
 * @property string $percent
 * @property string $previous_amount
 * @property string $new_amount
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lease $lease
 */
#[Fillable(['lease_id', 'effective_on', 'adjustment_index', 'percent', 'previous_amount', 'new_amount', 'notes'])]
class LeaseAdjustment extends Model
{
    /** @use HasFactory<LeaseAdjustmentFactory> */
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
            'effective_on' => 'date:Y-m-d',
            'adjustment_index' => AdjustmentIndex::class,
            'percent' => 'decimal:2',
            'previous_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
        ];
    }
}
