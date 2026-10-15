import { expect, test } from '@playwright/test';
import { CANDIDATE, logIn } from './support/users';

test.beforeEach(async ({ page }) => {
    await logIn(page, CANDIDATE);
});

test('recommends the offers that fit the profile first', async ({ page }) => {
    await page.goto('/jobs');
    await page.getByRole('tab', { name: 'Recomendadas para ti' }).click();

    await expect(page).toHaveURL(/tab=recommended/);
    const first = page.locator('main article').first();
    await expect(first).toContainText(/Laravel|Backend/);
    await expect(
        first.getByRole('img', { name: /Compatibilidad: \d+ de 100/ }),
    ).toBeVisible();
});

test('prepares, reviews and approves a quick application', async ({ page }) => {
    await page.goto('/jobs');
    await page
        .getByRole('link', { name: 'Desarrollador Backend Senior' })
        .click();
    await expect(page).toHaveURL(/\/jobs\/\d+/);
    await page.getByRole('button', { name: 'Solicitud rápida' }).click();

    const dialog = page.getByRole('dialog', { name: 'Solicitud rápida' });
    await expect(dialog).toContainText('Globex');
    await dialog.getByRole('button', { name: 'Preparar solicitud' }).click();

    // No AI key in the e2e environment: template letter and reordered CV.
    await expect(
        page.getByRole('heading', {
            name: 'Solicitud para Desarrollador Backend Senior',
        }),
    ).toBeVisible();
    await expect(page.getByText('Carta de presentación').first()).toBeVisible();
    await expect(page.getByText('CV adaptado').first()).toBeVisible();
    await expect(page.getByText('Ana García').first()).toBeVisible();
    await expect(
        page.getByText('Aprueba la solicitud para descargar el CV.'),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Aprobar' }).click();
    await expect(
        page.getByText('Aprobada. Ya puedes descargar el CV'),
    ).toBeVisible();

    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('link', { name: 'Descargar CV (PDF)' }).click(),
    ]);
    expect(download.suggestedFilename()).toMatch(/\.pdf$/);
    const stream = await download.createReadStream();
    const header = await new Promise<string>((resolve) =>
        stream.once('data', (chunk) => resolve(chunk.toString('latin1', 0, 5))),
    );
    expect(header).toBe('%PDF-');

    await page.getByRole('button', { name: 'Ya la he enviado' }).click();
    await expect(
        page.getByText('Marcada como enviada en Mis ofertas.'),
    ).toBeVisible();

    await page.goto('/applications');
    await expect(page.getByText('Desarrollador Backend Senior')).toBeVisible();
});
