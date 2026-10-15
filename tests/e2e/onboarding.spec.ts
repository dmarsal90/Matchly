import { expect, test } from '@playwright/test';
import { NEWCOMER, logIn } from './support/users';

test('a new user completes the skills questionnaire step by step', async ({
    page,
}) => {
    await logIn(page, NEWCOMER);
    await page.goto('/onboarding');

    // 1. Role
    await expect(page.getByText('Paso 1 de 5')).toBeVisible();
    await page.getByRole('button', { name: 'Guardar y continuar' }).click();
    await expect(page.getByText('Paso 1 de 5')).toBeVisible();
    await expect(page.getByText(/es obligatorio/).first()).toBeVisible();

    await page.getByRole('checkbox', { name: 'Backend' }).check();
    await page.getByLabel('Nivel de seniority').selectOption('senior');
    await page.getByLabel('Años de experiencia total').fill('6');
    await page.getByRole('button', { name: 'Guardar y continuar' }).click();

    // 2. Skills
    await expect(page.getByText('Paso 2 de 5')).toBeVisible();
    await page.getByRole('button', { name: 'Añadir habilidad' }).click();
    await page.locator('#skill-0-name').fill('Laravel');
    await page.locator('#skill-0-level').selectOption('4');
    await page.locator('#skill-0-years').fill('5');
    await page.getByRole('button', { name: 'Añadir habilidad' }).click();
    await page.locator('#skill-1-name').fill('PostgreSQL');
    await page.getByRole('button', { name: 'Guardar y continuar' }).click();

    // 3. Languages
    await expect(page.getByText('Paso 3 de 5')).toBeVisible();
    await page.getByRole('button', { name: 'Guardar y continuar' }).click();

    // 4. Where and how
    await expect(page.getByText('Paso 4 de 5')).toBeVisible();
    await page.getByRole('checkbox', { name: 'Remoto' }).check();
    await page.getByRole('checkbox', { name: 'Jornada completa' }).check();
    await page.getByRole('button', { name: 'Guardar y continuar' }).click();

    // 5. Conditions
    await expect(page.getByText('Paso 5 de 5')).toBeVisible();
    await page.getByRole('button', { name: 'Guardar y terminar' }).click();

    await expect(page).toHaveURL(/\/profile\/skills/);
    await expect(
        page.getByText('¡Listo! Ya tienes tu perfil de habilidades.'),
    ).toBeVisible();
    await expect(page.getByText('Laravel').first()).toBeVisible();
    await expect(page.getByText('PostgreSQL').first()).toBeVisible();

    // Answers are kept when the questionnaire is reopened.
    await page.goto('/onboarding?step=role');
    await expect(page.getByRole('checkbox', { name: 'Backend' })).toBeChecked();
    await expect(page.getByLabel('Años de experiencia total')).toHaveValue('6');
});
