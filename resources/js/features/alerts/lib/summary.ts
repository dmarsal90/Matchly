import { trans } from 'laravel-vue-i18n';

/** Filter keys whose values are listing options with translations under jobs.<key>.<value>. */
const ENUM_KEYS: Record<string, string> = {
    work_mode: 'work_mode',
    scope: 'scope',
    type: 'type',
    role: 'role',
    category: 'category',
    seniority: 'seniority',
    posted: 'posted',
};

/** Human-readable chips for a saved search's filters. */
export function filterChips(filters: Record<string, string>): string[] {
    const chips: string[] = [];

    for (const [key, value] of Object.entries(filters)) {
        if (!value) continue;
        const values = value.split(',');

        if (key === 'q') chips.push(`“${value}”`);
        else if (key in ENUM_KEYS)
            chips.push(
                ...values.map((item) =>
                    trans(`jobs.${ENUM_KEYS[key]}.${item}`),
                ),
            );
        else if (key === 'tag' || key === 'source') chips.push(...values);
        else if (key === 'salary_min')
            chips.push(
                `${trans('jobs.filters.salary')} ≥ ${Number(value).toLocaleString()}${filters.currency ? ` ${filters.currency}` : ''}`,
            );
        else if (key === 'min_score')
            chips.push(`${trans('jobs.filters.min_score')} ≥ ${value}`);
        else if (key === 'from' || key === 'to')
            chips.push(`${trans(`jobs.filters.${key}`)} ${value}`);
    }

    return chips;
}
