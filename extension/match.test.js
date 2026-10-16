import { beforeAll, describe, expect, it } from 'vitest';

let classify;

beforeAll(async () => {
    await import('./match.js');
    classify = globalThis.MatchlyMatch.classify;
});

describe('classify', () => {
    it('trusts the autocomplete hint first', () => {
        expect(classify({ autocomplete: 'given-name', name: 'x' })).toBe(
            'first_name',
        );
        expect(classify({ autocomplete: 'section-a family-name' })).toBe(
            'last_name',
        );
        expect(classify({ autocomplete: 'email' })).toBe('email');
        expect(classify({ autocomplete: 'tel' })).toBe('phone');
    });

    it('uses the input type for email and phone', () => {
        expect(classify({ type: 'email', name: 'contact' })).toBe('email');
        expect(classify({ type: 'tel', label: 'Mobile' })).toBe('phone');
    });

    it('reads English, Spanish and Portuguese labels', () => {
        expect(classify({ label: 'First name *' })).toBe('first_name');
        expect(classify({ label: 'Apellidos' })).toBe('last_name');
        expect(classify({ label: 'Sobrenome' })).toBe('last_name');
        expect(classify({ label: 'Nombre completo' })).toBe('full_name');
        expect(classify({ label: 'Nome completo' })).toBe('full_name');
        expect(classify({ label: 'Correo electrónico' })).toBe('email');
        expect(classify({ label: 'Teléfono' })).toBe('phone');
        expect(classify({ label: 'Cidade' })).toBe('location');
        expect(classify({ placeholder: 'LinkedIn profile URL' })).toBe(
            'linkedin',
        );
        expect(classify({ name: 'github_url' })).toBe('github');
        expect(classify({ label: 'Personal website' })).toBe('portfolio');
        expect(classify({ label: 'Pretensión salarial' })).toBe(
            'salary_expectation',
        );
    });

    it('knows a letter from a summary and ignores them in one-line fields', () => {
        expect(classify({ tag: 'textarea', label: 'Cover letter' })).toBe(
            'cover_letter',
        );
        expect(
            classify({ tag: 'textarea', label: 'Carta de presentación' }),
        ).toBe('cover_letter');
        expect(
            classify({ tag: 'textarea', label: 'Tell us about yourself' }),
        ).toBe('summary');
        expect(
            classify({
                tag: 'input',
                type: 'number',
                label: 'Cover letter pages',
            }),
        ).toBeNull();
    });

    it('recognises the CV upload and nothing else that is a file', () => {
        expect(classify({ type: 'file', label: 'Upload your CV' })).toBe(
            'resume',
        );
        expect(classify({ type: 'file', name: 'resume' })).toBe('resume');
        expect(
            classify({ type: 'file', label: 'Portfolio samples' }),
        ).toBeNull();
    });

    it('never touches passwords, checkboxes, hidden fields or unknown controls', () => {
        expect(
            classify({ type: 'password', label: 'Email password' }),
        ).toBeNull();
        expect(
            classify({ type: 'checkbox', label: 'Email me updates' }),
        ).toBeNull();
        expect(classify({ type: 'hidden', name: 'email' })).toBeNull();
        expect(classify({ label: 'Favourite colour' })).toBeNull();
    });
});
