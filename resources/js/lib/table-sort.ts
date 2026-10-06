export type SortDirection = 'asc' | 'desc';

export type TableSort = {
    sort: string;
    direction: SortDirection;
};

/**
 * Get the sort that follows a click on a column header: a new column starts ascending,
 * and clicking the current column flips its direction.
 */
export function nextSort(current: TableSort, column: string): TableSort {
    if (current.sort !== column) {
        return { sort: column, direction: 'asc' };
    }

    return {
        sort: column,
        direction: current.direction === 'asc' ? 'desc' : 'asc',
    };
}
