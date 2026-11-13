/**
 * One date-time formatter for every timestamp the UI renders. Browser locales
 * disagree on order (11/10/2026 reads as October in Nairobi and November in
 * New York), so the locale and timezone are pinned: East Africa Time, the
 * product's home ground. Render inside `<time dateTime>` for machines.
 */
const formatter = new Intl.DateTimeFormat('en-KE', {
    timeZone: 'Africa/Nairobi',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
});

const dateFormatter = new Intl.DateTimeFormat('en-KE', {
    timeZone: 'Africa/Nairobi',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

export function formatDateTime(value: string | number | Date): string {
    return formatter.format(new Date(value));
}

export function formatDate(value: string | number | Date): string {
    return dateFormatter.format(new Date(value));
}
