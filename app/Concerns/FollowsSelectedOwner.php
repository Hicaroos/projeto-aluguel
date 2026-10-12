<?php

namespace App\Concerns;

use App\Models\Owner;
use Illuminate\Database\Eloquent\Builder;

/**
 * Limit every query to the records of the owner picked in the owner selector, the way the branch
 * selector does for branches, so the whole app shows only what is theirs.
 */
trait FollowsSelectedOwner
{
    public static function bootFollowsSelectedOwner(): void
    {
        static::addGlobalScope(Owner::SELECTION_SCOPE, function (Builder $query): void {
            $ownerId = Owner::selectedId();

            if ($ownerId !== null) {
                static::restrictToOwner($query, $ownerId);
            }
        });
    }

    /**
     * Limit the query to the records of the properties the given owner owns, wholly or in part.
     *
     * @param  Builder<static>  $query
     */
    abstract public static function restrictToOwner(Builder $query, int $ownerId): void;
}
