import { router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';

/**
 * URL state for page overlays.
 *
 * The query string is read from Inertia's `page.url`, NOT `window.location`.
 * Inertia pushes history *after* React renders, so `window.location` is stale
 * during render and nothing re-renders on the change afterwards — the overlay
 * would not open until a full page load. `page.url` is part of the reactive
 * page object, so it is correct and is a real dependency.
 */
export function useOverlayParam() {
    const page = usePage();

    const query = useMemo(() => {
        const index = page.url.indexOf('?');

        return new URLSearchParams(index === -1 ? '' : page.url.slice(index));
    }, [page.url]);

    /**
     * Changes only the URL, never the result set — opening or closing an
     * overlay. Opening pushes history so Back closes it; closing replaces so
     * Back does not walk back through every overlay the user opened.
     *
     * `only` names the props this page needs after the change. Pass the ones it
     * renders and the visit stays a partial reload instead of re-running every
     * query behind the page.
     */
    function setOverlayParam(
        key: string,
        value: string | null,
        options?: {
            replace?: boolean;
            /** Extra params to carry through, e.g. the row being opened. */
            keep?: Record<string, string>;
            /** Props to re-request. Omit to reload the page's props. */
            only?: string[];
        },
    ) {
        const params: Record<string, string> = Object.fromEntries(
            query.entries(),
        );

        // An empty value is how a param is removed: `?mode=` reads as absent,
        // and keeping the key means the URL shape does not change between an
        // overlay being open and closed.
        params[key] = value ?? '';

        router.get(
            page.url.split('?')[0],
            { ...params, ...(options?.keep ?? {}) },
            {
                preserveState: true,
                preserveScroll: true,
                replace: options?.replace ?? false,
                ...(options?.only ? { only: options.only } : {}),
            },
        );
    }

    return { query, setOverlayParam };
}
