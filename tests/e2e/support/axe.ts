import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

/** WCAG 2.2 A and AA rules, in both colour schemes. */
export async function expectNoViolations(page: Page): Promise<void> {
    // The scan is only meaningful if the page really follows the scheme.
    const dark = await page.evaluate(
        () => window.matchMedia('(prefers-color-scheme: dark)').matches,
    );
    await expect(page.locator('html')).toHaveClass(
        dark ? /\bdark\b/ : /^(?!.*\bdark\b)/,
    );

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
        .analyze();

    const summary = results.violations.map(
        (violation) =>
            `${violation.id} (${violation.impact}): ${violation.nodes
                .slice(0, 3)
                .map((node) => node.target.join(' '))
                .join(', ')}`,
    );
    expect(summary).toEqual([]);
}
