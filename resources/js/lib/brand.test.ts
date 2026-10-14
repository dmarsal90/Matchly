import { afterEach, describe, expect, it } from 'vitest';
import { getAppName, pageTitle, setAppName } from './brand';

describe('brand', () => {
    afterEach(() => {
        setAppName('');
        document.head.innerHTML = '';
    });

    it('reads the product name from the server-rendered meta tag', () => {
        document.head.innerHTML =
            '<meta name="application-name" content="Acme Jobs">';

        expect(getAppName()).toBe('Acme Jobs');
    });

    it('builds page titles from the configured name', () => {
        setAppName('Acme Jobs');

        expect(pageTitle('Dashboard')).toBe('Dashboard - Acme Jobs');
        expect(pageTitle()).toBe('Acme Jobs');
    });
});
