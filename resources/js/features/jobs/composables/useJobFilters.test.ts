import { describe, expect, it } from 'vitest';
import type { JobFilters } from '../types';
import { toQuery } from './useJobFilters';

const empty: JobFilters = {
    q: null,
    work_mode: [],
    scope: [],
    type: [],
    role: [],
    category: [],
    seniority: [],
    source: [],
    tag: [],
    posted: null,
    from: null,
    to: null,
    salary_min: null,
    currency: null,
    sort: 'newest',
    group: null,
    per_page: 20,
    tab: null,
    min_score: null,
    ineligible: false,
};

describe('toQuery', () => {
    it('omits empty values and defaults', () => {
        expect(toQuery(empty, 20)).toEqual({});
    });

    it('serializes every filter compactly', () => {
        expect(
            toQuery(
                {
                    ...empty,
                    q: ' vue ',
                    work_mode: ['remote', 'hybrid'],
                    tag: ['php'],
                    posted: '7d',
                    salary_min: 50000,
                    currency: 'EUR',
                    sort: 'salary',
                    group: 'role',
                    per_page: 50,
                },
                20,
                3,
            ),
        ).toEqual({
            q: 'vue',
            work_mode: 'remote,hybrid',
            tag: 'php',
            posted: '7d',
            salary_min: '50000',
            currency: 'EUR',
            sort: 'salary',
            group: 'role',
            per_page: '50',
            page: '3',
        });
    });

    it('drops the currency when there is no salary filter', () => {
        expect(toQuery({ ...empty, currency: 'USD' }, 20)).toEqual({});
    });

    it('keeps the recommended tab and its score order implicit', () => {
        expect(
            toQuery({ ...empty, tab: 'recommended', sort: 'match' }, 20),
        ).toEqual({ tab: 'recommended' });
        expect(
            toQuery({ ...empty, tab: 'recommended', sort: 'newest' }, 20),
        ).toEqual({ tab: 'recommended', sort: 'newest' });
        expect(toQuery({ ...empty, sort: 'match', min_score: 70 }, 20)).toEqual(
            { sort: 'match', min_score: '70' },
        );
    });
});
