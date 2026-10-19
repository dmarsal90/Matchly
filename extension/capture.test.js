// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { beforeEach, describe, expect, it } from 'vitest';

const source = readFileSync(
    resolve(process.cwd(), 'extension/capture.js'),
    'utf8',
);

const run = () => (0, eval)(source);

function page(...blocks) {
    document.head.innerHTML = blocks
        .map((block) => `<script type="application/ld+json">${block}</script>`)
        .join('');
}

describe('capture', () => {
    beforeEach(() => {
        document.head.innerHTML = '';
        document.body.innerHTML = '<h1>Some page</h1>';
    });

    it('reads a JobPosting from the structured data', () => {
        page(
            JSON.stringify({
                '@context': 'https://schema.org',
                '@type': 'JobPosting',
                title: 'Backend Engineer',
                hiringOrganization: { '@type': 'Organization', name: 'Acme' },
                description: '<p>Build APIs</p>',
                datePosted: '2026-10-01',
                jobLocation: {
                    address: {
                        addressLocality: 'Madrid',
                        addressCountry: 'ES',
                    },
                },
            }),
        );

        expect(run()).toMatchObject({
            title: 'Backend Engineer',
            company: 'Acme',
            description: '<p>Build APIs</p>',
            location: 'Madrid, ES',
            posted_at: '2026-10-01',
        });
    });

    it('finds the posting inside a @graph, skipping broken blocks', () => {
        page(
            '{not json',
            JSON.stringify({
                '@graph': [
                    { '@type': 'WebSite', name: 'Site' },
                    {
                        '@type': ['JobPosting'],
                        title: 'Dev',
                        hiringOrganization: 'Globex',
                    },
                ],
            }),
        );

        expect(run()).toMatchObject({ title: 'Dev', company: 'Globex' });
    });

    it('returns null without a posting, a title or a company', () => {
        expect(run()).toBeNull();
        page(JSON.stringify({ '@type': 'JobPosting', title: 'Dev' }));
        expect(run()).toBeNull();
    });
});
