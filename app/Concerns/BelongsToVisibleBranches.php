<?php

namespace App\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;

/**
 * Limit every query to the records of the branches the signed in user is looking at, so a
 * branch selection or a user limited to some branches applies to the whole app at once.
 */
trait BelongsToVisibleBranches
{
    public const string BRANCH_SCOPE = 'branch';

    public static function bootBelongsToVisibleBranches(): void
    {
        static::addGlobalScope(self::BRANCH_SCOPE, function (Builder $query): void {
            $branchIds = Branch::visibleIds();

            if ($branchIds !== null) {
                static::restrictToBranches($query, $branchIds);
            }
        });
    }

    /**
     * Limit the query to the records of the given branches.
     *
     * @param  Builder<static>  $query
     * @param  list<int>  $branchIds
     */
    abstract public static function restrictToBranches(Builder $query, array $branchIds): void;
}
