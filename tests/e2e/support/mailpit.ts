import { expect } from '@playwright/test';

const MAILPIT = process.env.E2E_MAILPIT_URL ?? 'http://localhost:8025';

type Summary = { ID: string };

/** Waits for the newest e-mail sent to an address and returns its HTML body. */
export async function latestEmailTo(address: string): Promise<string> {
    let id: string | undefined;

    await expect
        .poll(
            async () => {
                const response = await fetch(
                    `${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:"${address}"`)}`,
                );
                const body = (await response.json()) as { messages: Summary[] };
                id = body.messages[0]?.ID;

                return id;
            },
            { message: `no e-mail reached ${address}`, timeout: 15_000 },
        )
        .toBeTruthy();

    const message = await fetch(`${MAILPIT}/api/v1/message/${id}`);

    return ((await message.json()) as { HTML: string }).HTML;
}

/** First link in an e-mail whose URL contains the given fragment. */
export function linkIn(html: string, fragment: string): string {
    const links = [...html.matchAll(/href="([^"]+)"/g)].map((match) =>
        match[1].replaceAll('&amp;', '&'),
    );
    const link = links.find((href) => href.includes(fragment));
    if (!link) {
        throw new Error(`no link containing "${fragment}" in the e-mail`);
    }

    return link;
}
