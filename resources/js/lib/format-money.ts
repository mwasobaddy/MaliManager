/**
 * One money formatter for every dashboard figure. Amounts arrive as
 * decimal strings/numbers; the currency rides with the payload (the org's
 * own settings, KES by default) — never a literal in the page, and never
 * `$` on a platform that bills in shillings. Null reads as an em dash so
 * missing figures never render as "KES 0".
 */
export function formatMoney(
    value: number | string | null,
    currency?: string | null,
): string {
    if (value === null) {
        return '—';
    }

    return `${currency ?? 'KES'} ${Number(value).toLocaleString()}`;
}
