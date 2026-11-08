/**
 * One money formatter for every dashboard figure. Amounts arrive as
 * decimal strings/numbers; the currency rides with the payload (the org's
 * own settings, KES by default) — never a literal in the page, and never
 * `$` on a platform that bills in shillings.
 */
export function formatMoney(value: number | string, currency = 'KES'): string {
    return `${currency} ${Number(value).toLocaleString()}`;
}
