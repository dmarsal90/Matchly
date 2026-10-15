import { expect, test } from '@playwright/test';
import { artisan } from './support/artisan';
import { latestEmailTo, linkIn } from './support/mailpit';
import { CANDIDATE, logIn } from './support/users';

/** Seeded by E2eSeeder with two backend offers waiting for the daily digest. */
const DAILY_ALERT = 'Backend en remoto';

test.beforeEach(async ({ page }) => {
    await logIn(page, CANDIDATE);
});

test('saves the current search as an alert and manages it', async ({
    page,
}) => {
    await page.goto('/jobs?role=frontend');
    await expect(page.locator('main article')).toHaveCount(2);

    await page.getByRole('button', { name: 'Crear alerta' }).click();
    const dialog = page.getByRole('dialog', {
        name: 'Crear alerta con esta búsqueda',
    });
    await expect(dialog).toContainText('Frontend');
    await dialog.getByLabel('Nombre').fill('Frontend Vue');
    await dialog.getByLabel('Frecuencia').selectOption('immediate');
    await dialog.getByRole('button', { name: 'Guardar alerta' }).click();
    await expect(
        page.getByText('Alerta creada. Te avisaremos de las ofertas nuevas.'),
    ).toBeVisible();

    await page.goto('/alerts');
    const alert = page
        .getByRole('listitem')
        .filter({ hasText: 'Frontend Vue' });
    await expect(alert).toContainText('Al momento');
    await expect(page.getByText(DAILY_ALERT)).toBeVisible();

    await alert.getByRole('button', { name: 'Pausar' }).click();
    await expect(alert).toContainText('En pausa');
    await alert.getByRole('button', { name: 'Reanudar' }).click();
    await expect(alert).toContainText('Activa');

    page.once('dialog', (confirm) => confirm.accept());
    await alert.getByRole('button', { name: 'Eliminar' }).click();
    await expect(page.getByText('Alerta eliminada.')).toBeVisible();
    await expect(page.getByText('Frontend Vue')).toHaveCount(0);
});

test('delivers the daily digest in the app and by e-mail, with one-click unsubscribe', async ({
    page,
}) => {
    artisan('alerts:digest', 'daily');

    await page.goto('/jobs');
    const bell = page.getByRole('button', {
        name: 'Notificaciones: 1 notificación nueva',
    });
    await expect(bell).toBeVisible();

    await page.goto('/notifications');
    await expect(
        page.getByText(`Resumen: 2 ofertas para «${DAILY_ALERT}»`),
    ).toBeVisible();

    const html = await latestEmailTo(CANDIDATE);
    expect(html).toContain('Desarrollador Backend Senior');
    expect(html).toContain('Senior Laravel Engineer');

    await page.goto(linkIn(html, '/unsubscribe'));
    await expect(
        page.getByRole('heading', { name: 'Te has dado de baja' }),
    ).toBeVisible();

    await page.goto('/alerts');
    await expect(
        page.getByRole('listitem').filter({ hasText: DAILY_ALERT }),
    ).toContainText('Solo en la app');
});
