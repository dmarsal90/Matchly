type LanguageFile = { default: Record<string, string> };

// The laravel-vue-i18n Vite plugin compiles lang/{locale}/*.php into lang/php_{locale}.json.
const languages = import.meta.glob<LanguageFile>('../../../lang/*.json');

export async function resolveLanguage(lang: string): Promise<LanguageFile> {
    const loader =
        languages[`../../../lang/php_${lang}.json`] ??
        languages[`../../../lang/${lang}.json`];

    return loader ? await loader() : { default: {} };
}
