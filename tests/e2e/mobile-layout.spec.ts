import { expect, test, type Page } from '@playwright/test';
import { CANDIDATE, logIn } from './support/users';

test.beforeEach(async ({ page }) => {
    await logIn(page, CANDIDATE);
});

/** The first block of the page content must not touch the screen edges. */
async function expectSidePadding(page: Page) {
    const box = await page.locator('#main-content h1').first().boundingBox();
    const width = page.viewportSize()?.width ?? 0;
    expect(box).not.toBeNull();
    expect(box!.x).toBeGreaterThanOrEqual(12);
    expect(box!.x + box!.width).toBeLessThanOrEqual(width - 12);
    const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth,
    );
    expect(overflow).toBeLessThanOrEqual(0);
}

test('the offers list keeps a margin on both sides', async ({ page }) => {
    await page.goto('/jobs');
    await expectSidePadding(page);
});

test('an offer keeps a margin on both sides', async ({ page }) => {
    await page.goto('/jobs');
    await page
        .getByRole('link', { name: 'Desarrollador Backend Senior' })
        .click();
    await expectSidePadding(page);
});
