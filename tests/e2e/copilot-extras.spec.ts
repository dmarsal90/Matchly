import { expect, test } from '@playwright/test';
import { artisan } from './support/artisan';
import { expectNoViolations } from './support/axe';
import { CANDIDATE, logIn } from './support/users';

test.beforeEach(async ({ page }) => {
    await logIn(page, CANDIDATE);
});

test('warns two days before a deadline the person set', async ({ page }) => {
    await page.goto('/applications');
    const card = page.locator('article').filter({ hasText: 'Hooli' });
    const inTwoDays = new Date(Date.now() + 2 * 86_400_000)
        .toISOString()
        .slice(0, 10);

    await card.getByLabel('Fecha límite').fill(inTwoDays);
    await expect(card.getByLabel('Fecha límite')).toHaveValue(inTwoDays);
    // Rejected: nothing to warn about. A live application is warned instead.
    const live = page.locator('article').filter({ hasText: 'Umbrella' });
    await live.getByLabel('Fecha límite').fill(inTwoDays);
    await expect(live.getByLabel('Fecha límite')).toHaveValue(inTwoDays);

    artisan('applications:deadlines');

    await page.goto('/notifications');
    await expect(
        page.getByText('Se acerca una fecha límite').first(),
    ).toBeVisible();
    await expect(page.getByText(/fecha límite en Umbrella/)).toBeVisible();
    await expect(page.getByText(/fecha límite en Hooli/)).toHaveCount(0);
});

test('downloads the insights as an offline report', async ({ page }) => {
    await page.goto('/insights');

    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('link', { name: 'Descargar informe' }).click(),
    ]);
    expect(download.suggestedFilename()).toMatch(
        /-report-\d{4}-\d{2}-\d{2}\.html$/,
    );
    const stream = await download.createReadStream();
    const chunks: Buffer[] = [];
    for await (const chunk of stream) {
        chunks.push(Buffer.from(chunk));
    }
    const html = Buffer.concat(chunks).toString('utf8');

    expect(html).toContain('Mi informe de búsqueda');
    expect(html).toContain('Cómo van tus candidaturas');
    // Self-contained: nothing is loaded from outside the file.
    expect(html).not.toMatch(/<script|<link |src="http|@import/);
});

test('offers the portfolio as a source of skills, and the study hours', async ({
    page,
}) => {
    await page.goto('/profile/skills');
    const card = page.getByRole('region', { name: 'Portfolio' });
    await expect(card.getByLabel('Dirección de tu portfolio')).toBeVisible();
    await expect(
        card.getByRole('button', { name: 'Leer mi portfolio' }),
    ).toBeVisible();
    await expectNoViolations(page);

    await page.goto('/insights');
    await page.getByText('Dónde aprender Kubernetes').first().click();
    await expect(
        page.getByText(/≈ \d+ h \(estimación orientativa\)/).first(),
    ).toBeVisible();
});

test('practises an answer and gets plain feedback on the text', async ({
    page,
}) => {
    await page.goto('/applications');
    await page
        .locator('article')
        .filter({ hasText: 'Umbrella' })
        .getByRole('link', { name: 'Preparar entrevista' })
        .click();
    await page
        .getByRole('button', { name: /Generar el pack|Generarlo/ })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Preguntas probables' }),
    ).toBeVisible();

    const question = page.locator('article').first();
    await question.getByText('Practicar esta respuesta').click();
    await question.getByLabel('Tu respuesta').fill('Laravel.');
    await question.getByRole('button', { name: 'Guardar respuesta' }).click();
    await expect(question.getByText('· guardada')).toBeVisible();

    await question
        .getByRole('button', { name: 'Comentar mi respuesta' })
        .click();
    await expect(
        question.getByText('Comprobaciones sencillas sobre tu texto'),
    ).toBeVisible();
    await expect(question.getByText(/Es muy corta/)).toBeVisible();
    await expectNoViolations(page);
});

test('runs a mock interview and sees where the answers are weakest', async ({
    page,
}) => {
    await page.goto('/applications');
    await page
        .locator('article')
        .filter({ hasText: 'Umbrella' })
        .getByRole('link', { name: 'Preparar entrevista' })
        .click();
    // The pack of this application already exists (the practice test made it).
    await page.getByRole('link', { name: 'Hacer un simulacro' }).click();

    await page.getByRole('button', { name: 'Empezar el simulacro' }).click();
    await expect(page.getByRole('timer')).toBeVisible();
    await page.getByLabel('Tu respuesta').fill('Respuesta corta.');
    await page.getByRole('button', { name: 'Guardar y siguiente' }).click();

    // Leave the rest blank until the last question.
    const progress = await page
        .getByText(/^Pregunta \d+ de \d+$/)
        .textContent();
    const total = Number(progress?.match(/de (\d+)/)?.[1]);
    for (let step = 2; step <= total; step++) {
        await expect(
            page.getByText(`Pregunta ${step} de ${total}`),
        ).toBeVisible();
        if (step < total) {
            await page
                .getByRole('button', { name: 'Guardar y siguiente' })
                .click();
        }
    }
    await page
        .getByRole('button', { name: 'Terminar y ver el resumen' })
        .click();

    await expect(page.getByRole('status')).toContainText('Respondiste 1 de');
    await expect(page.getByText('Sin responder').first()).toBeVisible();
    await expect(page.getByText(/Es muy corta/)).toBeVisible();
    await expectNoViolations(page);
});

test('follows a company from a job and finds it in the radar', async ({
    page,
}) => {
    await page.goto('/jobs');
    await page.getByRole('link', { name: 'Frontend Developer (Vue)' }).click();

    const follow = page.getByRole('button', { name: 'Seguir empresa' });
    await follow.click();
    await expect(
        page.getByRole('button', { name: 'Siguiendo la empresa' }),
    ).toHaveAttribute('aria-pressed', 'true');

    await page.goto('/radar');
    await expect(
        page.getByRole('heading', { name: 'Radar de empresas' }),
    ).toBeVisible();
    await expect(
        page.getByRole('list', { name: 'Empresas que sigues' }),
    ).toContainText('Umbrella');
    await expectNoViolations(page);

    await page
        .getByRole('button', { name: /Dejar de seguir: Umbrella/ })
        .click();
    await expect(page.getByText(/Aún no sigues ninguna empresa/)).toBeVisible();
});

test('plans four weeks of study and offers it as a calendar', async ({
    page,
}) => {
    await page.goto('/insights');

    await expect(
        page.getByRole('heading', { name: 'Plan de estudio de 4 semanas' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: /^Semana 1/ }),
    ).toBeVisible();

    await page.getByLabel('Horas por semana').selectOption('10');
    await expect(page.getByLabel('Horas por semana')).toHaveValue('10');
    const link = page.getByRole('link', {
        name: 'Descargar calendario (.ics)',
    });
    await expect(link).toHaveAttribute('href', /hours=10/);

    const [download] = await Promise.all([
        page.waitForEvent('download'),
        link.click(),
    ]);
    expect(download.suggestedFilename()).toMatch(/-study-plan\.ics$/);
    await expectNoViolations(page);
});
