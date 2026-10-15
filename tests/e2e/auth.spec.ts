import { expect, test } from '@playwright/test';
import { latestEmailTo, linkIn } from './support/mailpit';
import { PASSWORD, logIn, uniqueEmail } from './support/users';

test('a visitor registers, verifies the e-mail and logs back in', async ({
    page,
}) => {
    const email = uniqueEmail('register');

    await page.goto('/register');
    await page.getByLabel('Nombre').fill('Rita Registro');
    await page.getByLabel('Correo electrónico').fill(email);
    await page.getByLabel('Contraseña', { exact: true }).fill(PASSWORD);
    await page.getByLabel('Confirmar contraseña').fill(PASSWORD);
    await page.getByRole('button', { name: 'Crear cuenta' }).click();

    // The public listing is open straight away; private pages wait for the
    // link in the e-mail.
    await expect(page).toHaveURL(/\/jobs/);
    await page.goto('/applications');
    await expect(page).toHaveURL(/\/email\/verify/);
    await expect(
        page.getByRole('heading', { name: 'Verifica tu correo' }),
    ).toBeVisible();

    const html = await latestEmailTo(email);
    await page.goto(linkIn(html, '/email/verify/'));
    // Back to the page that asked for verification.
    await expect(page).toHaveURL(/\/applications/);

    await page.getByRole('button', { name: 'Rita Registro' }).click();
    await page.getByRole('menuitem', { name: 'Cerrar sesión' }).click();
    await expect(page).toHaveURL(/\/$|\/login/);

    await logIn(page, email);
    await expect(page).toHaveURL(/\/jobs/);
});

test('wrong credentials are rejected without revealing which part failed', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('nobody@e2e.test');
    await page.getByLabel('Contraseña', { exact: true }).fill('wrong-password');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();

    await expect(page).toHaveURL(/\/login/);
    await expect(
        page.getByText(
            'Estas credenciales no coinciden con nuestros registros.',
        ),
    ).toBeVisible();
});
