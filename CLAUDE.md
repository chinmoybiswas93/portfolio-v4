# CLAUDE.md — portfolio-v4

Laravel 13 + Filament 5 portfolio, blog, case studies and invoicing, built for shared PHP hosting (no Node runtime).
Architecture decisions are in `docs/adr/`; the overall design is in `docs/ARCHITECTURE.md`. Read them before planning a change.
Owner-specific notes (domain, hosting account, private paths) live in a git-ignored `CLAUDE.local.md` and `.private/`.

## Public repo rule
The GitHub repo is **public** and generic. Only project and deployment files go to git. Account names, server hostnames, SSH users and ports, other sites on the account, client data, invoices, contact details, personal names and domains, and secrets belong in `.private/` (git-ignored), the database or the server `.env`. Before every commit, grep the staged diff for them. A test fails if personal terms appear in tracked files.

## How to work
- New work is a feature or fix slice: plan it, build it with tests, and **stop for owner approval** before anything reaches production. Never start the next slice unasked.
- **Ask before assuming.** If a requirement is unclear, ask with short multiple-choice questions. Record answers in a `docs/adr/` entry.
- **Token discipline:** read only the files you need, prefer targeted greps over full reads, do not re-read files you just edited, and keep replies short. Use subagents only for wide searches or independent reviews.
- **Look up library docs with context7** (Laravel, Filament, Tailwind v4, DomPDF, Pest) before writing code against them. Do not rely on memory for APIs.
- **UI:** no UI code before its design is approved by the owner.

## Local development
- Any PHP 8.3 with SQLite works. Laravel Herd is optional: with Herd, run `herd php artisan …`, `herd php vendor/bin/pest` and `herd composer …` so the PHP version matches.
- Local database: SQLite. Mail: `MAIL_MAILER=log`. CI covers MariaDB.
- Local admin is at `/admin` (`ADMIN_PATH` defaults to `admin`); production uses its own private path.
- `ADMIN_MFA_REQUIRED=false` in `.env` turns admin two-factor off **only** when `APP_ENV=local`. Every other environment, tests and CI always require it.
- Branding comes from Settings (database) and `config/brand.php` (`BRAND_*` env keys). Never hard-code a name, domain or email in `app/` or `resources/`.

## Git rules
- Branches: `main` is production, `development` is integration plus CI, and `feat/*`, `fix/*` and `chore/*` branch off `development`. Release = a PR from `development` into `main`.
- Use Conventional Commits. Keep commits small. Commit or push only when the owner asks, or as an approved plan says.
- Never commit `.env`, credentials, DB dumps or the `storage/` contents.
- Never push to or deploy `main` without the owner's explicit go-ahead.

## Code rules
- PHP 8.3, strict types, Pint (Laravel preset) and Larastan level 6 or higher must pass.
- Keep controllers thin. Put logic in Actions/Services. Use Form Requests for validation, Policies for authorization, and Enums for statuses.
- Store money as integer minor units with a currency code. Use no floats for money.
- Every feature ships with Pest tests (feature tests for HTTP and Filament, unit tests for money and invoice maths). Write the test first for invoice calculations.
- Blade and Tailwind v4 for the public site. Use Alpine.js only for small interactions. Do not use a SPA framework on the public side.
- Write accessible HTML: semantic landmarks, focus states, alt text and reduced-motion support.

## Hosting constraints (shared PHP hosting)
- No Node at runtime. Build assets locally or in CI, and deploy the built `public/build`.
- `exec`, `shell_exec`, `system` and `symlink` may be disabled in PHP. Do not use packages that need them at runtime.
- Keep secrets only in the server `.env`.
- There are no long-running workers. Run queues and scheduled jobs from cron `schedule:run`.
- Migrations must be backward-compatible with the previous release (rollback does not undo them). Take a database dump before any destructive migration.

## Definition of done (per task)
Tests are green, Pint, Larastan and the build pass, behaviour is checked in a browser on the local server (screens for UI), and docs/ADR are updated if a decision changed.

## Server safety
Any ssh/scp/rsync, hosting-API call or deploy needs the owner's approval. Do read-only checks first, back up before any change, and never run heavy work on a shared server. Account-specific rules are in `CLAUDE.local.md`.
