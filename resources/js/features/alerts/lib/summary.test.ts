import { describe, expect, it, vi } from 'vitest';
import { filterChips } from './summary';

vi.mock('laravel-vue-i18n', () => ({ trans: (key: string) => key }));

describe('filterChips', () => {
    it('turns saved filters into readable chips', () => {
        expect(
            filterChips({
                q: 'laravel',
                work_mode: 'remote,hybrid',
                tag: 'php',
                min_score: '70',
                salary_min: '50000',
                currency: 'EUR',
            }),
        ).toEqual([
            '“laravel”',
            'jobs.work_mode.remote',
            'jobs.work_mode.hybrid',
            'php',
            'jobs.filters.min_score ≥ 70',
            `jobs.filters.salary ≥ ${(50000).toLocaleString()} EUR`,
        ]);
    });

    it('ignores empty values and unknown keys', () => {
        expect(filterChips({ q: '', currency: 'EUR', foo: 'bar' })).toEqual([]);
    });
});
