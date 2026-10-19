// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';

const source = readFileSync(
    resolve(process.cwd(), 'extension/fill.js'),
    'utf8',
);

function run(payload) {
    globalThis.__matchlyPayload = payload;

    // The same way the extension injects the file: as a script in the page.
    return (0, eval)(source);
}

beforeAll(async () => {
    await import('./match.js');
    // jsdom has neither DataTransfer nor assignable file lists: a minimal stand-in.
    globalThis.DataTransfer = class {
        constructor() {
            this.files = [];
            this.items = { add: (file) => this.files.push(file) };
        }
    };
    Object.defineProperty(HTMLInputElement.prototype, 'files', {
        get() {
            return this._files ?? [];
        },
        set(value) {
            this._files = value;
        },
        configurable: true,
    });
    // jsdom has no layout: say every element is visible.
    Object.defineProperty(HTMLElement.prototype, 'offsetParent', {
        get() {
            return document.body;
        },
        configurable: true,
    });
});

beforeEach(() => {
    document.body.innerHTML = `
        <form>
            <label for="n">First name</label><input id="n" type="text">
            <label for="l">Last name</label><input id="l" type="text" value="Typed by hand">
            <label>Email <input type="email"></label>
            <textarea aria-label="Cover letter"></textarea>
            <input type="file" name="resume">
            <input type="password" aria-label="Email password">
            <input type="text" aria-label="Favourite colour">
            <input type="submit" value="Send">
        </form>`;
});

describe('fill', () => {
    const payload = {
        fields: {
            first_name: 'Ana',
            last_name: 'García',
            email: 'ana@example.com',
            cover_letter: 'Hola',
        },
        resume: { name: 'cv.pdf', base64: btoa('%PDF-1.4') },
    };

    it('fills empty fields, keeps what is already there and fires the events', () => {
        let changed = 0;
        document.getElementById('n').addEventListener('input', () => changed++);

        const result = run(payload);

        expect(document.getElementById('n').value).toBe('Ana');
        expect(changed).toBe(1);
        expect(document.getElementById('l').value).toBe('Typed by hand');
        expect(document.querySelector('input[type=email]').value).toBe(
            'ana@example.com',
        );
        expect(document.querySelector('textarea').value).toBe('Hola');
        expect(result.filled).toBe(3);
    });

    it('leaves passwords and unknown fields alone and never submits', () => {
        let submitted = false;
        document.querySelector('form').addEventListener('submit', (event) => {
            submitted = true;
            event.preventDefault();
        });

        run(payload);

        expect(document.querySelector('input[type=password]').value).toBe('');
        expect(
            document.querySelector('[aria-label="Favourite colour"]').value,
        ).toBe('');
        expect(submitted).toBe(false);
    });

    it('attaches the CV to the resume upload when it was asked for, and not otherwise', () => {
        const withCv = run(payload);
        const file = document.querySelector('input[type=file]').files[0];

        expect(withCv.attached).toBe(1);
        expect(file.name).toBe('cv.pdf');
        expect(file.type).toBe('application/pdf');

        document.querySelector('input[type=file]').files = [];
        expect(run({ ...payload, resume: null }).attached).toBe(0);
    });

    it('collects empty open questions and writes the drafted answers only into empty boxes', () => {
        document.body.insertAdjacentHTML(
            'beforeend',
            `<label for="q">What interests you about our product?</label><textarea id="q"></textarea>
             <label for="d">What is your biggest achievement?</label><textarea id="d">Mine</textarea>`,
        );

        const { questions } = run({ ...payload, resume: null });

        expect(questions.map((item) => item.text)).toEqual([
            'What interests you about our product?',
        ]);

        const written = run({ answers: { [questions[0].id]: 'Because…' } });
        expect(document.getElementById('q').value).toBe('Because…');
        expect(document.getElementById('d').value).toBe('Mine');
        expect(written.filled).toBe(1);
    });
});
