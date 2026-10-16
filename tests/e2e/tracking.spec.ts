import { expect, test } from '@playwright/test';
import { artisan } from './support/artisan';
import { expectNoViolations } from './support/axe';
import { latestEmailTo } from './support/mailpit';
import { CANDIDATE, logIn } from './support/users';

/** Seeded by E2eSeeder: Initech is applied 3 days ago, Umbrella 15, Hooli was rejected. */
test.beforeEach(async ({ page }) => {
    await logIn(page, CANDIDATE);
});

test('records the history of an application and moves it to interview', async ({
    page,
}) => {
    await page.goto('/applications');
    const card = page
        .locator('article')
        .filter({ hasText: 'Senior Laravel Engineer' });

    await card.getByText('Historial').click();
    await expect(card).toContainText('Pasó a Aplicada');
    // Applied and waiting: a follow-up draft to copy, never sent.
    await expect(card.getByText('Borrador de seguimiento')).toBeVisible();
    await expect(card).toContainText('Initech');

    await card.getByLabel('Qué ha pasado').selectOption('follow_up');
    await card.getByRole('button', { name: 'Añadir entrada' }).click();
    await expect(card).toContainText('Seguimiento enviado');
    // The form resets when the request finishes: wait, or it would clear what is typed next.
    await expect(
        card.getByRole('button', { name: 'Añadir entrada' }),
    ).toBeEnabled();

    await card.getByLabel('Qué ha pasado').selectOption('interview');
    await card.getByLabel('Detalles').fill('Charla con RRHH');
    await card.getByRole('button', { name: 'Añadir entrada' }).click();

    // The interview moves the card to its column.
    const column = page.getByRole('region', { name: /Entrevista/ });
    const moved = column
        .locator('article')
        .filter({ hasText: 'Senior Laravel Engineer' });
    await expect(moved).toBeVisible();
    await moved.getByText('Historial').click();
    await expect(moved).toContainText('Entrevista, ronda 1');
    await expect(moved).toContainText('Charla con RRHH');
});

test('prepares for the interview with a pack built from the CV', async ({
    page,
}) => {
    await page.goto('/applications');
    await page
        .locator('article')
        .filter({ hasText: 'Initech' })
        .getByRole('link', { name: 'Preparar entrevista' })
        .click();

    await expect(
        page.getByRole('heading', { name: 'Preparar la entrevista' }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Generar el pack' }).click();

    await expect(
        page.getByRole('heading', { name: 'Preguntas probables' }),
    ).toBeVisible();
    await expect(
        page.getByText('Construido con plantillas y tu CV'),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Preguntas para hacerles' }),
    ).toBeVisible();
    // Task and result are left for the person: nothing is invented.
    await page.getByText('Esquema de respuesta (STAR)').first().click();
    await expect(
        page.getByText('Completa esto con un ejemplo real tuyo.').first(),
    ).toBeVisible();

    await expectNoViolations(page);
});

test('reminds about an application with no answer, in the app and by e-mail', async ({
    page,
}) => {
    artisan('applications:follow-up');

    await page.goto('/notifications');
    await expect(
        page.getByText('¿Toca hacer seguimiento?').first(),
    ).toBeVisible();
    await expect(page.getByText(/Umbrella no ha respondido/)).toBeVisible();

    const html = await latestEmailTo(CANDIDATE);
    expect(html).toContain('Aún sin respuesta de Umbrella');
    expect(html).toContain('Me presenté el');
    expect(html).toContain('nunca contactamos con las empresas por ti');
});

test('shows the funnel and the skills worth learning', async ({ page }) => {
    await page.goto('/insights');

    await expect(page.getByRole('heading', { name: 'Panorama' })).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Cómo van tus candidaturas' }),
    ).toBeVisible();
    const stages = page.getByRole('table', {
        name: 'Candidaturas por etapa alcanzada',
    });
    await expect(stages.getByRole('row', { name: /Enviadas/ })).toBeVisible();
    await expect(stages.getByRole('row', { name: /Entrevista/ })).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Qué aprender a continuación' }),
    ).toBeVisible();
    await expect(page.getByText('E2E Board')).toBeVisible();
});
