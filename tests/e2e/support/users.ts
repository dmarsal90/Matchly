import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

/** Accounts created by database/seeders/E2eSeeder.php. */
export const CANDIDATE = 'candidate@e2e.test';
export const NEWCOMER = 'newcomer@e2e.test';
export const PASSWORD = 'password';

export async function logIn(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill(email);
    await page.getByLabel('Contraseña', { exact: true }).fill(PASSWORD);
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login/);
}

/** A unique address per test run, so registrations never collide. */
export function uniqueEmail(prefix: string): string {
    return `${prefix}+${Date.now()}@e2e.test`;
}
