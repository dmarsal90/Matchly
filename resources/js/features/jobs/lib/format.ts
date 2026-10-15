import type { JobSalary } from '@/features/jobs/types';

const DIVISIONS: { amount: number; unit: Intl.RelativeTimeFormatUnit }[] = [
    { amount: 60, unit: 'second' },
    { amount: 60, unit: 'minute' },
    { amount: 24, unit: 'hour' },
    { amount: 7, unit: 'day' },
    { amount: 4.34524, unit: 'week' },
    { amount: 12, unit: 'month' },
    { amount: Number.POSITIVE_INFINITY, unit: 'year' },
];

/** "hace 3 horas", "3 hours ago", "há 2 dias"… in the active locale. */
export function relativeTime(
    iso: string,
    locale: string,
    now: Date = new Date(),
): string {
    const format = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
    let duration = (new Date(iso).getTime() - now.getTime()) / 1000;

    for (const division of DIVISIONS) {
        if (Math.abs(duration) < division.amount) {
            return format.format(Math.round(duration), division.unit);
        }
        duration /= division.amount;
    }

    return format.format(Math.round(duration), 'year');
}

function money(
    amount: number,
    currency: string | null,
    locale: string,
): string {
    if (currency === null) {
        return new Intl.NumberFormat(locale, {
            maximumFractionDigits: 0,
            notation: amount >= 10000 ? 'compact' : 'standard',
        }).format(amount);
    }

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        maximumFractionDigits: amount < 1000 ? 2 : 0,
        notation: amount >= 10000 ? 'compact' : 'standard',
    }).format(amount);
}

export type SalaryParts = {
    kind: 'range' | 'from' | 'up_to' | 'text';
    min?: string;
    max?: string;
    text?: string;
    period: string | null;
};

/** Structured salary for display; falls back to the source's own text. */
export function salaryParts(salary: JobSalary, locale: string): SalaryParts {
    const { min, max, currency, period } = salary;

    if (min !== null && max !== null && min !== max) {
        return {
            kind: 'range',
            min: money(min, currency, locale),
            max: money(max, currency, locale),
            period,
        };
    }
    if (min !== null) {
        return { kind: 'from', min: money(min, currency, locale), period };
    }
    if (max !== null) {
        return { kind: 'up_to', max: money(max, currency, locale), period };
    }

    return { kind: 'text', text: salary.text ?? '', period: null };
}
