import { describe, expect, it } from 'vitest';
import type { Job } from '../types';
import { jobPostingJsonLd } from './jobPosting';

const job = {
    title: 'Backend Engineer',
    company: 'Initech',
    company_logo_url: null,
    location: null,
    work_mode: 'remote',
    allowed_regions: ['US', 'EUROPE'],
    employment_type: 'full_time',
    salary: {
        min: 60000,
        max: null,
        currency: 'USD',
        period: 'year',
        text: null,
    },
    posted_at: '2026-10-01T00:00:00+00:00',
    description: '<p>Build things</script><script>alert(1)</script></p>',
} as Job;

describe('jobPostingJsonLd', () => {
    it('describes a remote posting with country and salary', () => {
        const posting = JSON.parse(jobPostingJsonLd(job));

        expect(posting.jobLocationType).toBe('TELECOMMUTE');
        expect(posting.applicantLocationRequirements).toEqual([
            { '@type': 'Country', name: 'US' },
        ]);
        expect(posting.employmentType).toBe('FULL_TIME');
        expect(posting.baseSalary.value).toEqual({
            '@type': 'QuantitativeValue',
            minValue: 60000,
            unitText: 'YEAR',
        });
    });

    it('cannot close the script block from the description', () => {
        expect(jobPostingJsonLd(job)).not.toContain('<');
    });
});
