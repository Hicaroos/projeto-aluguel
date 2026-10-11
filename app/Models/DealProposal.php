<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Concerns\BelongsToVisibleBranches;
use App\Enums\PaymentPlan;
use App\Enums\ProposalStatus;
use Database\Factories\DealProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An offer made by the lead of a deal, and the answer it got.
 *
 * @property int $id
 * @property int $account_id
 * @property int $deal_id
 * @property string $amount
 * @property PaymentPlan|null $payment_plan
 * @property string|null $conditions
 * @property ProposalStatus $status
 * @property Carbon $proposed_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Deal $deal
 */
#[Fillable(['account_id', 'deal_id', 'amount', 'payment_plan', 'conditions', 'status', 'proposed_on'])]
class DealProposal extends Model
{
    /** @use HasFactory<DealProposalFactory> */
    use BelongsToAccount, BelongsToVisibleBranches, HasFactory;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * Limit the query to the proposals of the deals of the given branches.
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
            'amount' => 'decimal:2',
            'payment_plan' => PaymentPlan::class,
            'status' => ProposalStatus::class,
            'proposed_on' => 'date:Y-m-d',
        ];
    }
}
