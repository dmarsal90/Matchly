import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { CANDIDATE, logIn } from './support/users';

/** WCAG 2.2 A and AA rules, in both colour schemes. */
async function expectNoViolations(page: Page): Promise<void> {
    // The scan is only meaningful if the page really follows the scheme.
    const dark = await page.evaluate(
        () => window.matchMedia('(prefers-color-scheme: dark)').matches,
    );
    await expect(page.locator('html')).toHaveClass(
        dark ? /\bdark\b/ : /^(?!.*\bdark\b)/,
    );

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
        .analyze();

    const summary = results.violations.map(
        (violation) =>
            `${violation.id} (${violation.impact}): ${violation.nodes
                .slice(0, 3)
                .map((node) => node.target.join(' '))
                .join(', ')}`,
    );
    expect(summary).toEqual([]);
}

for (const scheme of ['light', 'dark'] as const) {
    test.describe(`${scheme} theme`, () => {
        test.use({ colorScheme: scheme });

        test('public pages', async ({ page }) => {
            for (const path of ['/', '/jobs', '/login', '/register']) {
                await page.goto(path);
                await page.waitForLoadState('networkidle');
                await expectNoViolations(page);
            }

            await page.goto('/jobs');
            await page.locator('main article a').first().click();
            await expect(page).toHaveURL(/\/jobs\/\d+/);
            await expectNoViolations(page);
        });

        test('signed-in pages', async ({ page }) => {
            await logIn(page, CANDIDATE);
            for (const path of [
                '/jobs?tab=recommended',
                '/applications',
                '/alerts',
                '/profile/skills',
                '/onboarding',
                '/settings/profile',
            ]) {
                await page.goto(path);
                await page.waitForLoadState('networkidle');
                await expectNoViolations(page);
            }
        });
    });
}
