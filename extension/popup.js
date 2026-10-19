const MAX_QUESTIONS = 5;

const TEXT = {
    en: {
        choose: 'Approved application',
        attach: 'Attach the CV (PDF)',
        run: 'Fill this page',
        review: 'It only fills empty fields. You review and submit the form yourself.',
        settings: 'Connection',
        address: 'Matchly address',
        token: 'Access token',
        save: 'Save',
        saved: 'Saved.',
        none: 'No approved applications yet. Approve one in Matchly first.',
        connect: 'Add your address and token below.',
        denied: 'Permission to reach that address was denied.',
        badToken:
            'The token is not valid. Create a new one in Matchly › Settings.',
        failed: 'Could not reach Matchly.',
        done: 'Filled {n} field(s){cv}. Review before you submit.',
        cv: ' and attached the CV',
        saveJob: 'Save this job in Matchly',
        saveJobNote:
            'It reads only the public job data of the page and never anything behind a login.',
        saved_job: 'Saved in Matchly.',
        no_job: 'This page has no job posting Matchly can read.',
        open_job: 'Open it in Matchly',
        drafting: 'Drafting answers to the open questions…',
        answers: ' Drafted {n} answer(s): they are suggestions, edit them.',
        nothing: 'Nothing to fill on this page.',
    },
    es: {
        choose: 'Solicitud aprobada',
        attach: 'Adjuntar el CV (PDF)',
        run: 'Rellenar esta página',
        review: 'Solo rellena campos vacíos. Tú revisas y envías el formulario.',
        settings: 'Conexión',
        address: 'Dirección de Matchly',
        token: 'Token de acceso',
        save: 'Guardar',
        saved: 'Guardado.',
        none: 'Aún no hay solicitudes aprobadas. Aprueba una en Matchly primero.',
        connect: 'Añade abajo tu dirección y tu token.',
        denied: 'Se denegó el permiso para acceder a esa dirección.',
        badToken: 'El token no es válido. Crea uno nuevo en Matchly › Ajustes.',
        failed: 'No se pudo conectar con Matchly.',
        done: 'Rellenados {n} campo(s){cv}. Revisa antes de enviar.',
        cv: ' y adjuntado el CV',
        saveJob: 'Guardar esta oferta en Matchly',
        saveJobNote:
            'Solo lee los datos públicos de la oferta de la página, nunca nada que esté tras un inicio de sesión.',
        saved_job: 'Guardada en Matchly.',
        no_job: 'Esta página no tiene una oferta que Matchly pueda leer.',
        open_job: 'Abrirla en Matchly',
        drafting: 'Redactando las respuestas a las preguntas abiertas…',
        answers: ' Redactadas {n} respuesta(s): son sugerencias, edítalas.',
        nothing: 'No hay nada que rellenar en esta página.',
    },
    pt: {
        choose: 'Candidatura aprovada',
        attach: 'Anexar o CV (PDF)',
        run: 'Preencher esta página',
        review: 'Só preenche campos vazios. Tu revês e envias o formulário.',
        settings: 'Ligação',
        address: 'Endereço do Matchly',
        token: 'Token de acesso',
        save: 'Guardar',
        saved: 'Guardado.',
        none: 'Ainda não há candidaturas aprovadas. Aprova uma no Matchly primeiro.',
        connect: 'Adiciona em baixo o teu endereço e token.',
        denied: 'A permissão para aceder a esse endereço foi negada.',
        badToken: 'O token não é válido. Cria um novo em Matchly › Definições.',
        failed: 'Não foi possível ligar ao Matchly.',
        done: 'Preenchidos {n} campo(s){cv}. Revê antes de enviar.',
        cv: ' e anexado o CV',
        saveJob: 'Guardar esta oferta no Matchly',
        saveJobNote:
            'Só lê os dados públicos da oferta da página, nunca nada que esteja atrás de um login.',
        saved_job: 'Guardada no Matchly.',
        no_job: 'Esta página não tem uma oferta que o Matchly consiga ler.',
        open_job: 'Abri-la no Matchly',
        drafting: 'A redigir as respostas às perguntas abertas…',
        answers: ' Redigidas {n} resposta(s): são sugestões, edita-as.',
        nothing: 'Não há nada para preencher nesta página.',
    },
};
const language = (navigator.language || 'en').slice(0, 2);
const t = TEXT[language] || TEXT.en;
document.documentElement.lang = TEXT[language] ? language : 'en';

const $ = (id) => document.getElementById(id);
const status = (message, isError = false) => {
    $('status').textContent = message;
    $('status').className = isError ? 'error' : '';
};

for (const element of document.querySelectorAll('[data-i]')) {
    element.textContent = t[element.dataset.i];
}

async function api(path, asBlob = false, body = null) {
    const { base, token } = await chrome.storage.local.get(['base', 'token']);
    const response = await fetch(`${base}/api/extension/${path}`, {
        method: body ? 'POST' : 'GET',
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
            ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    if (response.status === 401) {
        throw new Error('badToken');
    }
    if (!response.ok) {
        throw new Error('failed');
    }

    return asBlob ? response.arrayBuffer() : response.json();
}

function toBase64(buffer) {
    let binary = '';
    for (const byte of new Uint8Array(buffer)) {
        binary += String.fromCharCode(byte);
    }

    return btoa(binary);
}

async function load() {
    const { base, token } = await chrome.storage.local.get(['base', 'token']);
    $('base').value = base || '';
    $('token').value = token || '';
    if (!base || !token) {
        $('settings').open = true;
        status(t.connect);

        return;
    }
    try {
        const { packages } = await api('packages');
        $('package').replaceChildren(
            ...packages.map((item) =>
                Object.assign(document.createElement('option'), {
                    value: item.id,
                    textContent: `${item.title} · ${item.company}`,
                }),
            ),
        );
        $('capture').hidden = false;
        $('fill').hidden = packages.length === 0;
        status(packages.length === 0 ? t.none : '');
    } catch (error) {
        $('settings').open = true;
        status(t[error.message] || t.failed, true);
    }
}

$('save').addEventListener('click', async () => {
    let origin;
    try {
        origin = new URL($('base').value.trim()).origin;
    } catch {
        status(t.failed, true);

        return;
    }
    // Ask only for the Matchly address, and only when the person saves it.
    const granted = await chrome.permissions.request({
        origins: [`${origin}/*`],
    });
    if (!granted) {
        status(t.denied, true);

        return;
    }
    await chrome.storage.local.set({
        base: origin,
        token: $('token').value.trim(),
    });
    status(t.saved);
    await load();
});

$('save-job').addEventListener('click', async () => {
    try {
        const [tab] = await chrome.tabs.query({
            active: true,
            currentWindow: true,
        });
        const [result] = await chrome.scripting.executeScript({
            target: { tabId: tab.id },
            files: ['capture.js'],
        });
        if (!result.result) {
            status(t.no_job, true);

            return;
        }
        const { url } = await api('jobs', false, result.result);
        status('');
        const link = Object.assign(document.createElement('a'), {
            href: url,
            target: '_blank',
            rel: 'noopener',
            textContent: t.open_job,
        });
        $('status').append(`${t.saved_job} `, link);
    } catch (error) {
        status(t[error.message] || t.failed, true);
    }
});

$('run').addEventListener('click', async () => {
    try {
        const id = $('package').value;
        const { fields } = await api(`packages/${id}/fields`);
        const resume = $('attach').checked
            ? {
                  name: 'cv.pdf',
                  base64: toBase64(
                      await api(`packages/${id}/resume.pdf`, true),
                  ),
              }
            : null;
        const [tab] = await chrome.tabs.query({
            active: true,
            currentWindow: true,
        });
        const target = { tabId: tab.id, allFrames: true };

        await chrome.scripting.executeScript({
            target,
            func: (payload) => {
                globalThis.__matchlyPayload = payload;
            },
            args: [{ fields, resume }],
        });
        const results = await chrome.scripting.executeScript({
            target,
            files: ['match.js', 'fill.js'],
        });
        const total = results.reduce(
            (sum, item) => ({
                filled: sum.filled + ((item.result || {}).filled || 0),
                attached: sum.attached + ((item.result || {}).attached || 0),
            }),
            { filled: 0, attached: 0 },
        );

        // Open questions: ask Matchly for a draft of each one (a few at most) and fill them as well.
        const questions = results
            .flatMap((item) => (item.result || {}).questions || [])
            .slice(0, MAX_QUESTIONS);
        if (questions.length > 0) {
            status(t.drafting);
            const answers = {};
            for (const question of questions) {
                try {
                    const draft = await api(`packages/${id}/answer`, false, {
                        question: question.text,
                    });
                    answers[question.id] = draft.answer;
                } catch (error) {
                    if (error.message === 'badToken') {
                        throw error;
                    }
                }
            }
            await chrome.scripting.executeScript({
                target,
                func: (payload) => {
                    globalThis.__matchlyPayload = payload;
                },
                args: [{ answers }],
            });
            const written = await chrome.scripting.executeScript({
                target,
                files: ['match.js', 'fill.js'],
            });
            total.answers = written.reduce(
                (sum, item) => sum + ((item.result || {}).filled || 0),
                0,
            );
        }

        status(
            total.filled + total.attached + (total.answers || 0) === 0
                ? t.nothing
                : t.done
                      .replace('{n}', total.filled)
                      .replace('{cv}', total.attached > 0 ? t.cv : '') +
                      (total.answers
                          ? t.answers.replace('{n}', total.answers)
                          : ''),
        );
    } catch (error) {
        status(t[error.message] || t.failed, true);
    }
});

load();
