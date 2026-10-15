import { describe, expect, it } from 'vitest';
import { formatDate, formatPeriod } from './format';

describe('formatPeriod', () => {
    it('returns an empty string without a value', () => {
        expect(formatPeriod(null, 'en')).toBe('');
    });

    it('keeps a bare year', () => {
        expect(formatPeriod('2020', 'en')).toBe('2020');
    });

    it('formats a year and month', () => {
        expect(formatPeriod('2020-03', 'en')).toBe('Mar 2020');
    });
});

describe('formatDate', () => {
    it('formats an ISO timestamp as a calendar date', () => {
        expect(formatDate('2026-10-14T10:00:00Z', 'en')).toBe('Oct 14, 2026');
    });
});
