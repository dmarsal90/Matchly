/** Calendar date in the page language ("14 oct 2026"). */
export function formatDate(iso: string, locale?: string): string {
    return new Intl.DateTimeFormat(
        locale ?? (document.documentElement.lang || 'en'),
        { day: 'numeric', month: 'short', year: 'numeric' },
    ).format(new Date(iso));
}

/** "2020-03" / "2020" as a short month-year in the page language. */
export function formatPeriod(value: string | null, locale?: string): string {
    if (!value) return '';
    const [year, month] = value.split('-').map(Number);
    if (!month) return String(year);

    return new Intl.DateTimeFormat(
        locale ?? (document.documentElement.lang || 'en'),
        { month: 'short', year: 'numeric' },
    ).format(new Date(Date.UTC(year, month - 1, 15)));
}
