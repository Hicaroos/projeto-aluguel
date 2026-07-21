<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $account_id
 * @property int $property_id
 * @property ExpenseType $type
 * @property string $amount
 * @property Carbon $due_date
 * @property Carbon|null $payment_date
 * @property ExpenseStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Property $property
 */
#[Fillable(['account_id', 'property_id', 'type', 'amount', 'due_date', 'payment_date', 'status'])]
class Expense extends Model
{
    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExpenseType::class,
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'payment_date' => 'date',
            'status' => ExpenseStatus::class,
        ];
    }
}
