import { describe, expect, it } from 'vitest';
import { relativeTime, salaryParts } from './format';

const now = new Date('2026-10-14T12:00:00Z');

describe('relativeTime', () => {
    it('speaks in hours for recent postings', () => {
        expect(relativeTime('2026-10-14T09:00:00Z', 'es', now)).toBe(
            'hace 3 horas',
        );
        expect(relativeTime('2026-10-14T09:00:00Z', 'en', now)).toBe(
            '3 hours ago',
        );
    });

    it('switches to days and natural words', () => {
        expect(relativeTime('2026-10-13T12:00:00Z', 'es', now)).toBe('ayer');
        expect(relativeTime('2026-10-10T12:00:00Z', 'pt', now)).toBe(
            'há 4 dias',
        );
    });
});

describe('salaryParts', () => {
    it('formats ranges with currency', () => {
        expect(
            salaryParts(
                {
                    min: 60000,
                    max: 80000,
                    currency: 'EUR',
                    period: 'year',
                    text: null,
                },
                'en',
            ),
        ).toMatchObject({
            kind: 'range',
            min: '€60K',
            max: '€80K',
            period: 'year',
        });
    });

    it('formats single values and hourly rates', () => {
        expect(
            salaryParts(
                {
                    min: 45.5,
                    max: null,
                    currency: 'USD',
                    period: 'hour',
                    text: null,
                },
                'en',
            ),
        ).toMatchObject({
            kind: 'from',
            min: '$45.50',
        });
    });

    it('falls back to the source text', () => {
        expect(
            salaryParts(
                {
                    min: null,
                    max: null,
                    currency: null,
                    period: null,
                    text: 'Competitive',
                },
                'en',
            ),
        ).toEqual({
            kind: 'text',
            text: 'Competitive',
            period: null,
        });
    });
});
