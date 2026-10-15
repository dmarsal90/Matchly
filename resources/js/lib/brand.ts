let appName = '';

/** The product name comes from config('app.name') on the server, never from the bundle. */
export function setAppName(name: string): void {
    appName = name;
}

export function getAppName(): string {
    if (appName === '' && typeof document !== 'undefined') {
        appName =
            document
                .querySelector<HTMLMetaElement>('meta[name="application-name"]')
                ?.getAttribute('content') ?? '';
    }

    return appName;
}

export function pageTitle(title?: string): string {
    const name = getAppName();

    return title ? `${title} - ${name}` : name;
}
