import { execFileSync } from 'node:child_process';

/** Runs an Artisan command against the e2e database (docker/scripts/e2e.sh artisan). */
export function artisan(...args: string[]): string {
    const prefix = (
        process.env.E2E_ARTISAN_COMMAND ??
        'docker compose exec -T app sh docker/scripts/e2e.sh artisan'
    ).split(' ');

    return execFileSync(prefix[0], [...prefix.slice(1), ...args], {
        encoding: 'utf8',
    });
}
