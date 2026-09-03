import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Rewrite a cross-origin public-storage URL (e.g. a central-domain
 * https://malimanager.test/storage/... asset) to the current origin so the
 * browser can read it without CORS. Tenants share the same app docroot and the
 * public/storage symlink, so the same file is served on the tenant subdomain.
 * Only `/storage/` paths are rewritten; everything else is returned unchanged
 * so unrelated absolute URLs are never redirected to a host that lacks them.
 */
export function sameOriginStorageUrl(url: string): string {
    try {
        const parsed = new URL(url, window.location.origin);

        if (parsed.origin === window.location.origin) {
            return url;
        }

        if (parsed.pathname.startsWith('/storage/')) {
            return `${window.location.origin}${parsed.pathname}${parsed.search}`;
        }
    } catch {
        // Not an absolute URL (e.g. a data URI or relative path) — leave it.
    }

    return url;
}
