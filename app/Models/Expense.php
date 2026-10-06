<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use Carbon\CarbonInterface;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $account_id
 * @property int $property_id
 * @property int|null $payment_id Extra charge billing this expense to a tenant.
 * @property ExpenseType $type
 * @property string|null $description
 * @property string $amount
 * @property Carbon $due_date
 * @property Carbon|null $payment_date
 * @property ExpenseStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Property $property
 * @property-read Payment|null $payment
 */
#[Fillable(['account_id', 'property_id', 'payment_id', 'type', 'description', 'amount', 'due_date', 'payment_date', 'status'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

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
        return $this->belongsTo(Property::class)->withTrashed();
    }

    /**
     * Get the extra charge that bills this expense to a tenant, if any.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Scope the query to expenses due within the given month.
     *
     * @param  Builder<Expense>  $query
     */
    public function scopeDueInMonth(Builder $query, CarbonInterface $month): void
    {
        $query->whereBetween('due_date', [
            $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
        ]);
    }

    /**
     * Scope the query to pending expenses whose due date has already passed.
     *
     * @param  Builder<Expense>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', ExpenseStatus::Pending)->whereDate('due_date', '<', today());
    }

    /**
     * Scope the query by a display status: any stored status, or "overdue".
     *
     * @param  Builder<Expense>  $query
     */
    public function scopeFilterByStatus(Builder $query, string $status): void
    {
        $query
            ->when($status === 'overdue', fn (Builder $query) => $query->overdue())
            ->when(ExpenseStatus::tryFrom($status) !== null, fn (Builder $query) => $query->where('status', $status));
    }

    /**
     * Scope the query to expenses whose description or property address matches the given term.
     *
     * @param  Builder<Expense>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->when($term !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
            $query->where('description', 'like', "%{$term}%")
                ->orWhereHas('property', fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
                    $query->where('street', 'like', "%{$term}%")
                        ->orWhere('neighborhood', 'like', "%{$term}%");
                }));
        }));
    }

    /**
     * Determine whether the expense is still waiting to be paid.
     */
    public function isPending(): bool
    {
        return $this->status === ExpenseStatus::Pending;
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
            'due_date' => 'date:Y-m-d',
            'payment_date' => 'date:Y-m-d',
            'status' => ExpenseStatus::class,
        ];
    }
}
