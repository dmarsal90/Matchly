import { expect, test } from '@playwright/test';
import { expectNoViolations } from './support/axe';
import { CANDIDATE, logIn } from './support/users';

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
                '/insights',
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
