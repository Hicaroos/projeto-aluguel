<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * The share of a property one of its owners holds.
 *
 * @property int $owner_id
 * @property int $property_id
 * @property string $share_percent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OwnerProperty extends Pivot
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'share_percent' => 'decimal:2',
        ];
    }
}
