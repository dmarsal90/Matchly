type LanguageFile = { default: Record<string, string> };

// SSR only (app.ts imports it behind import.meta.env.SSR): the server cannot await a dynamic import, so it bundles every language.
const bundled = import.meta.glob<LanguageFile>('../../../lang/*.json', {
    eager: true,
});

export function resolveLanguageSync(lang: string): Record<string, string> {
    return (
        (
            bundled[`../../../lang/php_${lang}.json`] ??
            bundled[`../../../lang/${lang}.json`]
        )?.default ?? {}
    );
}
