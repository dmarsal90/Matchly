import { expect, test } from '@playwright/test';
import { NEWCOMER, logIn } from './support/users';
import { linkedinExport } from './support/zip';

test('a user imports a LinkedIn export, reviews it and confirms', async ({
    page,
}) => {
    await logIn(page, NEWCOMER);
    await page.goto('/profile/skills');

    await page.getByLabel('Archivo ZIP de LinkedIn').setInputFiles({
        name: 'Basic_LinkedInDataExport.zip',
        mimeType: 'application/zip',
        buffer: linkedinExport(),
    });

    // Consent is required before anything is read.
    await page.getByRole('button', { name: 'Importar ZIP' }).click();
    await expect(
        page.getByText(/consentimiento|acepta/i).first(),
    ).toBeVisible();
    await expect(page).toHaveURL(/\/profile\/skills$/);

    await page
        .getByRole('checkbox', {
            name: 'Acepto que se procese mi exportación de LinkedIn para importar mi experiencia y habilidades.',
        })
        .check();
    await page.getByRole('button', { name: 'Importar ZIP' }).click();

    await expect(page).toHaveURL(/\/profile\/imports\/\d+/);
    await expect(
        page.getByRole('heading', { name: 'Revisa lo importado' }),
    ).toBeVisible();
    await expect(
        page.getByText('Senior Backend Engineer').first(),
    ).toBeVisible();
    await expect(page.getByText('Laravel').first()).toBeVisible();
    // Messages and connections never make it into the review.
    await expect(page.getByText(/Connections|messages\.csv/)).toHaveCount(0);

    await page.getByRole('button', { name: 'Guardar selección' }).click();

    await expect(page).toHaveURL(/\/profile\/skills/);
    await expect(
        page.getByText(
            'Importación guardada. Tu perfil de habilidades se ha actualizado.',
        ),
    ).toBeVisible();
});
