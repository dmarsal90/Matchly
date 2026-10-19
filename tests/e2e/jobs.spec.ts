import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { expectNoViolations } from './support/axe';

/** Job cards in the listing (database/seeders/E2eSeeder.php seeds eight open, fresh jobs). */
const cards = (page: Page) => page.locator('main article');

/**
 * Runs an action on the filter panel. On a phone the panel lives in a drawer
 * behind the "Filtros" button; on a desktop it is always on screen.
 */
async function inFilterPanel(
    page: Page,
    action: () => Promise<void>,
): Promise<void> {
    const trigger = page.getByRole('button', { name: /^Filtros/ });
    const drawer = page.getByRole('dialog');
    const inDrawer = await trigger.isVisible();
    if (inDrawer) {
        await trigger.click();
        await expect(drawer).toBeVisible();
    }

    await action();

    if (inDrawer) {
        await page.keyboard.press('Escape');
        await expect(drawer).toBeHidden();
    }
}

async function toggleFilter(
    page: Page,
    section: string,
    option: string,
): Promise<void> {
    await inFilterPanel(page, async () => {
        const details = page
            .locator('details')
            .filter({ visible: true })
            .filter({ has: page.locator('summary', { hasText: section }) });
        if ((await details.getAttribute('open')) === null) {
            await details.locator('summary').click();
        }
        await details
            .getByRole('checkbox', { name: option, exact: true })
            .click();
    });
}

test.beforeEach(async ({ page }) => {
    await page.goto('/jobs');
    await expect(cards(page).first()).toBeVisible();
});

test('lists only open offers within the freshness window', async ({ page }) => {
    await expect(cards(page)).toHaveCount(8);
    await expect(page.getByText('Stale Backend Role')).toHaveCount(0);
    await expect(page.getByText('Closed Backend Role')).toHaveCount(0);
    // Newest first by default.
    await expect(cards(page).first()).toContainText('Senior Laravel Engineer');
});

test('full-text search narrows the list and lives in the URL', async ({
    page,
}) => {
    await page
        .getByRole('searchbox', { name: 'Buscar ofertas' })
        .fill('laravel');

    await expect(page).toHaveURL(/[?&]q=laravel/);
    await expect(cards(page)).toHaveCount(2);
    await expect(page.getByText('Senior Laravel Engineer')).toBeVisible();
    // Matched in the description, not the title.
    await expect(page.getByText('Desarrollador Backend Senior')).toBeVisible();

    await page.reload();
    await expect(
        page.getByRole('searchbox', { name: 'Buscar ofertas' }),
    ).toHaveValue('laravel');
    await expect(cards(page)).toHaveCount(2);

    await page.getByRole('searchbox', { name: 'Buscar ofertas' }).fill('cobol');
    await expect(
        page.getByText('No hay ofertas con estos filtros'),
    ).toBeVisible();
});

test('filters combine and survive a reload', async ({ page }) => {
    await toggleFilter(page, 'Rol', 'Frontend');
    await expect(page).toHaveURL(/[?&]role=frontend/);
    await expect(cards(page)).toHaveCount(2);

    await toggleFilter(page, 'Seniority', 'Junior');
    await expect(page).toHaveURL(/seniority=junior/);
    await expect(cards(page)).toHaveCount(1);
    await expect(cards(page).first()).toContainText('Junior React Developer');

    await page.reload();
    await expect(cards(page)).toHaveCount(1);

    await inFilterPanel(page, () =>
        page.getByRole('button', { name: 'Limpiar filtros' }).click(),
    );
    await expect(cards(page)).toHaveCount(8);
    await expect(page).not.toHaveURL(/role=/);
});

test('groups the listing by role with a count per group', async ({ page }) => {
    await page
        .getByRole('combobox', { name: 'Agrupar por' })
        .selectOption('role');

    await expect(page).toHaveURL(/group=role/);
    await expect(
        page.getByRole('heading', { level: 2, name: /Backend/ }),
    ).toBeVisible();
    await expect(page.getByText('2 ofertas').first()).toBeVisible();
});

test('opens an offer with a link to the original source', async ({ page }) => {
    await page.getByRole('link', { name: 'Product Designer' }).click();

    await expect(page).toHaveURL(/\/jobs\/\d+/);
    await expect(
        page.getByRole('heading', { level: 1, name: 'Product Designer' }),
    ).toBeVisible();
    await expect(page.getByText('Stark Industries').first()).toBeVisible();
    await expect(page.getByRole('link', { name: /E2E Board/ })).toHaveAttribute(
        'href',
        /^https?:\/\//,
    );
});

test('warns that a long-running, reposted offer may be stale', async ({
    page,
}) => {
    await page.goto('/jobs/9001');

    const ghost = page.getByTestId('ghost');
    await expect(ghost).toContainText('Esta oferta puede estar desactualizada');
    await expect(ghost).toContainText(
        'La misma empresa ha publicado este puesto varias veces.',
    );
    await expectNoViolations(page);

    // A fresh offer says nothing.
    await page.goto('/jobs');
    await cards(page).first().getByRole('link').first().click();
    await expect(page.getByTestId('ghost')).toHaveCount(0);
});
