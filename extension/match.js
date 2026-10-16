/*
 * Decides which Matchly field a form control asks for, from the text around
 * it (autocomplete, type, name, id, placeholder, label). Pure and
 * dependency-free: it is injected into pages before fill.js and unit tested.
 * Keywords are in English, Spanish and Portuguese.
 */
(function (root) {
    const AUTOCOMPLETE = {
        'given-name': 'first_name',
        'family-name': 'last_name',
        name: 'full_name',
        email: 'email',
        tel: 'phone',
        'tel-national': 'phone',
        url: 'portfolio',
    };

    // Most specific first: the first rule whose pattern matches wins.
    const RULES = [
        [
            'cover_letter',
            /cover ?letter|carta (de )?(presentacion|motivacion|apresentacao)|motivation|motivacao|why .*(join|want)|por que|por qu[eé]/,
        ],
        [
            'salary_expectation',
            /salar|sueldo|remuner|expectativa|pretens|compensation|pay expect/,
        ],
        ['linkedin', /linkedin/],
        ['github', /github/],
        [
            'portfolio',
            /portfolio|portafolio|portf[oó]lio|website|web ?site|sitio web|personal (site|web)|p[aá]gina web|\burl\b/,
        ],
        ['email', /e-?mail|correo|\bmail\b/],
        [
            'phone',
            /phone|tel[eé]fono|telemovel|telem[oó]vel|mobile|m[oó]vil|celular|whatsapp/,
        ],
        [
            'full_name',
            /full ?name|nombre completo|nome completo|your name|^(name|nombre|nome) ?\*?$/,
        ],
        [
            'first_name',
            /first ?name|given ?name|nombre|primeiro nome|\bnome\b|prenom/,
        ],
        [
            'last_name',
            /last ?name|sur ?name|family ?name|apellido|sobrenome|apelido/,
        ],
        [
            'location',
            /location|city|ciudad|ubicaci|localiza|cidade|residence|residencia|address|direcci/,
        ],
        [
            'headline',
            /headline|job title|current (role|position)|puesto actual|cargo actual|t[ií]tulo profesional/,
        ],
        [
            'summary',
            /summary|about (you|yourself)|resumen|sobre ti|sobre mim|perfil profesional|bio\b|introduce yourself/,
        ],
    ];

    const SKIP_TYPES = [
        'hidden',
        'password',
        'submit',
        'button',
        'checkbox',
        'radio',
        'image',
        'reset',
        'search',
    ];

    function normalise(text) {
        return String(text || '')
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/[_\-.[\]]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    /**
     * @param {{tag?: string, type?: string, autocomplete?: string, name?: string, id?: string, placeholder?: string, label?: string, ariaLabel?: string}} control
     * @returns {string|null} a field key of the Matchly API, 'resume' for a file input, or null
     */
    function classify(control) {
        const type = normalise(control.type || 'text');
        if (SKIP_TYPES.includes(type)) {
            return null;
        }
        if (type === 'file') {
            return /cv|resume|curriculum|curr[ií]culo/.test(
                normalise(
                    [
                        control.name,
                        control.id,
                        control.label,
                        control.ariaLabel,
                    ].join(' '),
                ),
            )
                ? 'resume'
                : null;
        }

        const auto = String(control.autocomplete || '')
            .toLowerCase()
            .trim()
            .split(/\s+/)
            .pop();
        if (AUTOCOMPLETE[auto]) {
            return AUTOCOMPLETE[auto];
        }
        if (type === 'email') {
            return 'email';
        }
        if (type === 'tel') {
            return 'phone';
        }

        const tag = normalise(control.tag || 'input');
        // The label is what the person reads: it beats the technical name.
        const sources = [
            control.label,
            control.ariaLabel,
            control.placeholder,
            control.name,
            control.id,
        ]
            .map(normalise)
            .filter(Boolean);
        for (const text of sources) {
            for (const [key, pattern] of RULES) {
                if (pattern.test(text)) {
                    // A long free-text box is a letter or a summary, never a one-line field.
                    if (
                        tag !== 'textarea' &&
                        (key === 'cover_letter' || key === 'summary') &&
                        type !== 'text'
                    ) {
                        continue;
                    }

                    return key;
                }
            }
        }

        return null;
    }

    root.MatchlyMatch = { classify };
})(globalThis);
