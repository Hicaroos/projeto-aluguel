<?php

namespace App\Models;

use App\Actions\Payouts\PostExpenseToLedger;
use App\Concerns\BelongsToAccount;
use App\Concerns\BelongsToVisibleBranches;
use App\Enums\ExpensePayer;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use Carbon\CarbonInterface;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $account_id
 * @property int $property_id
 * @property ExpenseType $type
 * @property string|null $description
 * @property string|null $provider_name Who did the job, for the receipt they sign.
 * @property string|null $provider_document The provider's CPF or CNPJ, as digits.
 * @property ExpensePayer $charged_to Who bears the expense: the owner, out of their payouts, or the agency.
 * @property string $amount
 * @property Carbon $due_date
 * @property Carbon|null $payment_date
 * @property ExpenseStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Property $property
 * @property-read Collection<int, ExpenseDocument> $documents
 */
#[Fillable(['account_id', 'property_id', 'type', 'description', 'provider_name', 'provider_document', 'charged_to', 'amount', 'due_date', 'payment_date', 'status'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToAccount, BelongsToVisibleBranches, HasFactory;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'pending',
        'charged_to' => 'owner',
    ];

    /**
     * Keep the owners' running accounts in line with the expense when it is paid, changed or removed,
     * and remove its attached files with it.
     */
    protected static function booted(): void
    {
        static::saved(fn (Expense $expense) => app(PostExpenseToLedger::class)->handle($expense));

        static::deleted(fn (Expense $expense) => Storage::disk(ExpenseDocument::DISK)->deleteDirectory("expenses/{$expense->id}"));
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    /**
     * Get the attached documents, the latest first.
     *
     * @return HasMany<ExpenseDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ExpenseDocument::class)->latest('id');
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
     * Limit the query to the expenses of the properties of the given branches.
     *
     * @param  Builder<static>  $query
     * @param  array<int, int>  $branchIds
     */
    public static function restrictToBranches(Builder $query, array $branchIds): void
    {
        $query->whereIn($query->qualifyColumn('property_id'), Property::idsInBranches($branchIds));
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
            'charged_to' => ExpensePayer::class,
            'amount' => 'decimal:2',
            'due_date' => 'date:Y-m-d',
            'payment_date' => 'date:Y-m-d',
            'status' => ExpenseStatus::class,
        ];
    }
}
