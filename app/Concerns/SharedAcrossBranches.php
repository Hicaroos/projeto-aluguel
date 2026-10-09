<?php

namespace App\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * People shared by the whole agency, such as tenants and owners: one record per person, linked
 * to the branches that deal with them. Each branch lists the people linked to it, plus the ones
 * not linked to any branch yet.
 */
trait SharedAcrossBranches
{
    use BelongsToVisibleBranches;

    /**
     * Get the branches that deal with this person. People linked to none are seen by every branch.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /**
     * Limit the query to the people linked to the given branches, plus the ones not linked to any branch yet.
     *
     * @param  Builder<static>  $query
     * @param  array<int, int>  $branchIds
     */
    public static function restrictToBranches(Builder $query, array $branchIds): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereDoesntHave('branches')
            ->orWhereHas('branches', fn (Builder $query) => $query->whereIn('branches.id', $branchIds)));
    }

    /**
     * Link the given people to a branch, so the branch lists them.
     *
     * @param  array<int, int>  $ids
     */
    public static function linkToBranch(array $ids, ?int $branchId): void
    {
        if ($branchId === null || $ids === []) {
            return;
        }

        $relation = static::query()->getModel()->branches();

        DB::table($relation->getTable())->insertOrIgnore(array_map(
            fn (int $id): array => [
                $relation->getRelatedPivotKeyName() => $branchId,
                $relation->getForeignPivotKeyName() => $id,
            ],
            $ids,
        ));
    }
}
