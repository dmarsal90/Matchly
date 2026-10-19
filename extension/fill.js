/*
 * Runs in the page (after match.js) with the data in globalThis.__matchlyPayload.
 * It only fills empty fields and never clicks, submits or sends anything: the
 * person reviews the form and submits it themselves.
 */
(function () {
    const payload = globalThis.__matchlyPayload || { fields: {}, resume: null };
    const { classify } = globalThis.MatchlyMatch;
    const MIN_QUESTION = 12;

    function labelOf(element) {
        if (element.labels && element.labels.length > 0) {
            return Array.from(element.labels)
                .map((label) => label.textContent)
                .join(' ');
        }
        const wrapper = element.closest('label');
        if (wrapper) {
            return wrapper.textContent;
        }
        const by = element.getAttribute('aria-labelledby');

        return by
            ? by
                  .split(' ')
                  .map(
                      (id) =>
                          (document.getElementById(id) || {}).textContent || '',
                  )
                  .join(' ')
            : '';
    }

    function setValue(element, value) {
        // The native setter plus events, so frameworks that track input state see the change.
        const prototype =
            element instanceof HTMLTextAreaElement
                ? HTMLTextAreaElement.prototype
                : HTMLInputElement.prototype;
        Object.getOwnPropertyDescriptor(prototype, 'value').set.call(
            element,
            value,
        );
        element.dispatchEvent(new Event('input', { bubbles: true }));
        element.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function resumeFile() {
        if (!payload.resume) {
            return null;
        }
        const bytes = Uint8Array.from(atob(payload.resume.base64), (char) =>
            char.charCodeAt(0),
        );

        return new File([bytes], payload.resume.name, {
            type: 'application/pdf',
        });
    }

    // Second pass: put the drafted answers (keyed by the marker set in the first pass) into their boxes.
    if (payload.answers) {
        let written = 0;
        for (const [id, answer] of Object.entries(payload.answers)) {
            const box = document.querySelector(`[data-matchly-q="${id}"]`);
            if (box && box.value.trim() === '') {
                setValue(box, answer);
                written++;
            }
        }

        return { filled: written, attached: 0, questions: [] };
    }

    let filled = 0;
    let attached = 0;
    const questions = [];
    for (const element of document.querySelectorAll('input, textarea')) {
        if (
            element.disabled ||
            element.readOnly ||
            element.offsetParent === null
        ) {
            continue;
        }
        const key = classify({
            tag: element.tagName,
            type: element.getAttribute('type') || 'text',
            autocomplete: element.getAttribute('autocomplete'),
            name: element.getAttribute('name'),
            id: element.id,
            placeholder: element.getAttribute('placeholder'),
            label: labelOf(element),
            ariaLabel: element.getAttribute('aria-label'),
        });
        if (key === null) {
            // An empty free-text box with a question as its label is an open question to draft an answer for.
            const text = (
                labelOf(element) ||
                element.getAttribute('aria-label') ||
                ''
            )
                .replace(/\s+/g, ' ')
                .trim();
            if (
                element instanceof HTMLTextAreaElement &&
                element.value.trim() === '' &&
                text.length >= MIN_QUESTION
            ) {
                const id = Math.random().toString(36).slice(2, 10);
                element.setAttribute('data-matchly-q', id);
                questions.push({ id, text: text.slice(0, 500) });
            }
            continue;
        }

        if (key === 'resume') {
            const file = resumeFile();
            if (file && element.files.length === 0) {
                const transfer = new DataTransfer();
                transfer.items.add(file);
                element.files = transfer.files;
                element.dispatchEvent(new Event('change', { bubbles: true }));
                attached++;
            }
            continue;
        }

        const value = payload.fields[key];
        // Never overwrite what the person (or the site) already put there.
        if (value && element.value.trim() === '') {
            setValue(element, value);
            filled++;
        }
    }

    return { filled, attached, questions };
})();
