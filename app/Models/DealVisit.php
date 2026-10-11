<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Concerns\BelongsToVisibleBranches;
use App\Enums\VisitStatus;
use Database\Factories\DealVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A visit to the property of a deal, and how it went.
 *
 * @property int $id
 * @property int $account_id
 * @property int $deal_id
 * @property int|null $user_id The team member showing the property.
 * @property Carbon $scheduled_at
 * @property VisitStatus $status
 * @property string|null $feedback
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Deal $deal
 * @property-read User|null $user
 */
#[Fillable(['account_id', 'deal_id', 'user_id', 'scheduled_at', 'status', 'feedback'])]
class DealVisit extends Model
{
    /** @use HasFactory<DealVisitFactory> */
    use BelongsToAccount, BelongsToVisibleBranches, HasFactory;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'scheduled',
    ];

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit the query to the visits of the deals of the given branches.
     *
     * @param  Builder<static>  $query
     * @param  array<int, int>  $branchIds
     */
    public static function restrictToBranches(Builder $query, array $branchIds): void
    {
        $query->whereIn($query->qualifyColumn('deal_id'), Deal::idsInBranches($branchIds));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime:Y-m-d\TH:i',
            'status' => VisitStatus::class,
        ];
    }
}
