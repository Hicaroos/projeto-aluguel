<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SortDirection;

trait SortsTable
{
    /**
     * Order the query by the column requested through `?sort=` and `?direction=`.
     *
     * Only the keys of `$sorters` are accepted; anything else falls back to the default
     * column and direction. The primary key is used as a tie-breaker so pages are stable.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, Closure(Builder<TModel>, SortDirection): mixed>  $sorters
     * @return array{sort: string, direction: 'asc'|'desc'}
     */
    protected function applySort(
        Builder $query,
        Request $request,
        array $sorters,
        string $default,
        SortDirection $defaultDirection = SortDirection::Ascending,
    ): array {
        $sort = $request->string('sort')->toString();

        if (array_key_exists($sort, $sorters)) {
            $direction = $request->string('direction')->toString() === 'desc'
                ? SortDirection::Descending
                : SortDirection::Ascending;
        } else {
            $sort = $default;
            $direction = $defaultDirection;
        }

        $sorters[$sort]($query, $direction);
        $query->orderBy($query->getModel()->getQualifiedKeyName(), $direction);

        return [
            'sort' => $sort,
            'direction' => $direction === SortDirection::Descending ? 'desc' : 'asc',
        ];
    }
}
