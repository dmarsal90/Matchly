import { execSync } from 'node:child_process';

/** Fresh, seeded database before every run (database/seeders/E2eSeeder.php). */
export default function globalSetup(): void {
    const command =
        process.env.E2E_RESET_COMMAND ??
        'docker compose exec -T app sh docker/scripts/e2e.sh reset';

    execSync(command, { stdio: 'inherit' });
}
