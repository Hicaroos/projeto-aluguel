<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Uri;

trait ResolvesSelectedRecord
{
    /**
     * Resolve the record requested through `?show={id}` so the page can open its details,
     * even when it is not on the current page of the list.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  Already scoped to the user's account.
     * @return TModel|null
     */
    protected function resolveSelectedRecord(Request $request, Builder $query): ?Model
    {
        $id = $request->integer('show');

        return $id > 0 ? $query->find($id) : null;
    }

    /**
     * Get the current query string without `show`, for the pagination links.
     *
     * Keeping `show` there would reopen the selected record on every page change.
     *
     * @return array<string, mixed>
     */
    protected function queryWithoutSelection(Request $request): array
    {
        return Arr::except($request->query(), ['show', 'page']);
    }

    /**
     * Go back to the previous page with the given record selected, so its open details
     * show the change even when the record is not on the current page of the list.
     */
    protected function backWithSelectedRecord(int $id): RedirectResponse
    {
        return redirect()->to((string) Uri::of(url()->previous())->withQuery(['show' => $id]));
    }
}
