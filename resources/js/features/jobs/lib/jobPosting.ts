import type { Job } from '../types';

const EMPLOYMENT = {
    full_time: 'FULL_TIME',
    part_time: 'PART_TIME',
    contract: 'CONTRACTOR',
    freelance: 'CONTRACTOR',
    internship: 'INTERN',
    temporary: 'TEMPORARY',
} as const;

const SALARY_UNIT = {
    hour: 'HOUR',
    day: 'DAY',
    week: 'WEEK',
    month: 'MONTH',
    year: 'YEAR',
} as const;

/** schema.org JobPosting for Google for Jobs, serialised so it can sit inside a <script> tag. */
export function jobPostingJsonLd(job: Job): string {
    const { salary } = job;
    const remote = job.work_mode === 'remote';
    const countries = job.allowed_regions.filter((code) => code.length === 2);

    const posting = {
        '@context': 'https://schema.org',
        '@type': 'JobPosting',
        title: job.title,
        description: job.description ?? job.title,
        datePosted: job.posted_at,
        hiringOrganization: {
            '@type': 'Organization',
            name: job.company,
            ...(job.company_logo_url ? { logo: job.company_logo_url } : {}),
        },
        ...(job.employment_type && job.employment_type in EMPLOYMENT
            ? {
                  employmentType:
                      EMPLOYMENT[
                          job.employment_type as keyof typeof EMPLOYMENT
                      ],
              }
            : {}),
        ...(remote
            ? {
                  jobLocationType: 'TELECOMMUTE',
                  ...(countries.length > 0
                      ? {
                            applicantLocationRequirements: countries.map(
                                (name) => ({ '@type': 'Country', name }),
                            ),
                        }
                      : {}),
              }
            : job.location
              ? {
                    jobLocation: {
                        '@type': 'Place',
                        address: {
                            '@type': 'PostalAddress',
                            addressLocality: job.location,
                        },
                    },
                }
              : {}),
        ...(salary?.currency &&
        salary.period &&
        (salary.min !== null || salary.max !== null)
            ? {
                  baseSalary: {
                      '@type': 'MonetaryAmount',
                      currency: salary.currency,
                      value: {
                          '@type': 'QuantitativeValue',
                          ...(salary.min !== null
                              ? { minValue: salary.min }
                              : {}),
                          ...(salary.max !== null
                              ? { maxValue: salary.max }
                              : {}),
                          unitText: SALARY_UNIT[salary.period],
                      },
                  },
              }
            : {}),
    };

    // The description is HTML from third parties: "<" must never end the script block.
    return JSON.stringify(posting).replace(/</g, '\\u003c');
}
