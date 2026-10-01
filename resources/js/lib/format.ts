/**
 * Display helpers. Date-only values (and the date part of `due_at`) are read
 * as UTC calendar dates so a viewer's timezone never shifts them by a day.
 */
function toUtcDate(value: string): Date | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        return null;
    }

    return new Date(
        Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])),
    );
}

const shortDate = new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
});

const longDate = new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    timeZone: 'UTC',
});

/** "Oct 4" in the current year, "Oct 4, 2027" otherwise. */
export function formatDate(
    value: string | null | undefined,
    fallback = '—',
): string {
    const date = value ? toUtcDate(value) : null;

    if (!date) {
        return fallback;
    }

    return date.getUTCFullYear() === new Date().getFullYear()
        ? shortDate.format(date)
        : longDate.format(date);
}

/** Whole calendar days from today to the date; negative when it is past. */
export function daysUntil(value: string | null | undefined): number | null {
    const date = value ? toUtcDate(value) : null;

    if (!date) {
        return null;
    }

    const now = new Date();
    const today = Date.UTC(now.getFullYear(), now.getMonth(), now.getDate());

    return Math.round((date.getTime() - today) / 86_400_000);
}
