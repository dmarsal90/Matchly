let page = '';

export function setVitalsPage(component: string): void {
    page = component;
}

/** Real-user metrics, sent after load so they never compete with rendering. */
export function reportWebVitals(): void {
    if (!import.meta.env.PROD || import.meta.env.SSR) {
        return;
    }

    // shortcut: LCP/TTFB belong to the first page, INP/CLS to the whole visit; all are tagged with the page open when they fire. Split if that skews the numbers.
    const send = ({ name, value }: { name: string; value: number }): void => {
        if (page) {
            navigator.sendBeacon(
                '/vitals',
                new Blob([JSON.stringify({ metric: name, page, value })], {
                    type: 'application/json',
                }),
            );
        }
    };

    window.addEventListener('load', () => {
        void import('web-vitals').then(({ onCLS, onINP, onLCP, onTTFB }) => {
            onLCP(send);
            onINP(send);
            onCLS(send);
            onTTFB(send);
        });
    });
}
