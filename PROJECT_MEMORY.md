# Project continuity

Updated: 2026-11-10

## Current work

A visual refresh for Matchly is ready to publish: charcoal and coral branding across the public app and Filament admin, updated light/dark design tokens, refreshed welcome/auth pages and logos/icons, and admin table refinements. Filament also uses soft radial backgrounds, translucent navigation, elevated cards, refined table controls, and reduced-motion/transparency support.

## Decisions

Playwright snapshots and console logs in `.playwright-cli/` are local review artifacts and are excluded from source commits. No tests or builds were run during this publishing session.

## Verification

Reviewed the Git status and diff summary. No fresh tests or build were run.

## Next step

Review the published visual changes in a normal browser, especially `/admin/sources` and `/admin/ats-companies` table fit and readability, and check the public welcome/header branding.

## Recent committed work

- `1fe6ddb` — show saved job state immediately while request runs.
- `891c717`, `8ce8db4` — Laravel Pulse and real-user web vitals observability.
- `d14f4b2` — cache public job listing total and group counts for 60 seconds.
