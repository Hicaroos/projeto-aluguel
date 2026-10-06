import { watch } from 'vue';

/**
 * Open the details of the record requested through `?show={id}` (sent by the server as the
 * `selected` prop), then drop the parameter from the URL so a refresh doesn't reopen it.
 *
 * Each record opens only once: later responses carrying the same record (e.g. after a
 * page change or a form submit) don't reopen a modal the user already closed.
 */
export function useOpenSelectedRecord<T extends { id: number }>(
    selected: () => T | null | undefined,
    open: (record: T) => void,
): void {
    let lastOpenedId: number | null = null;

    watch(
        selected,
        (record) => {
            if (!record || record.id === lastOpenedId) {
                return;
            }

            lastOpenedId = record.id;
            open(record);

            const url = new URL(window.location.href);

            if (url.searchParams.has('show')) {
                url.searchParams.delete('show');
                window.history.replaceState(window.history.state, '', url);
            }
        },
        { immediate: true },
    );
}
