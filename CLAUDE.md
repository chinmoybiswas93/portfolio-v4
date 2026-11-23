# CLAUDE.md — portfolio-v4

Laravel 13 + Filament 5 portfolio, blog, case studies and invoicing for chinmoybiswas.com, hosted on Hostinger Premium (PHP only, no Node runtime).
Full scope and decisions are in `docs/PROJECT_BRIEF.md`. Read it before planning any phase.
Private server/account details (git-ignored, local only): @.private/SERVER.md

## Public repo rule
The GitHub repo is **public**. Only project and deployment files go to git. Account names, server hostnames, SSH users/ports, other sites on the account, client data, invoices, contact details and secrets belong in `.private/` (git-ignored) or server `.env`. Before every commit, grep the staged diff for them.

## How to work
- **The site is live** (since 2026-11-23); Phases 1–5 are complete. New work is a feature or fix slice: plan it, build it with tests, and **stop for owner approval** before anything reaches production. Never start the next slice unasked.
- **Ask before assuming.** If a requirement is unclear, ask with short multiple-choice questions. Record answers in `docs/PROJECT_BRIEF.md` or a `docs/adr/` entry.
- **Token discipline:** read only the files you need, prefer targeted greps over full reads, do not re-read files you just edited, and keep replies short. Use subagents only for wide searches or independent reviews.
- **Look up library docs with context7** (Laravel, Filament, Tailwind v4, DomPDF, Pest) before writing code against them. Do not rely on memory for APIs.
- Use the installed skills where they fit: `engineering:architecture` (ADRs), `engineering:system-design`, `engineering:testing-strategy`, `engineering:code-review`, `security-review`, `engineering:deploy-checklist`, `frontend-design`, `playwright-cli`, and the `hostinger:*` plugin for hosting.
- **UI:** no UI code before its design is approved in the canvas (Claude design artifact).

## Local development (Herd)
- The app runs in **Laravel Herd** at `https://portfolio-v4.test` (TLS on), isolated to **PHP 8.3**. No Docker and no `php artisan serve`.
- Run PHP commands through Herd's 8.3: `herd php artisan …`, `herd php vendor/bin/pest`, `herd composer …` (the plain `php` CLI is Herd's global 8.4).
- Local database: SQLite. Mail: `MAIL_MAILER=log`. CI covers MariaDB.
- Local admin is at `/admin` (`ADMIN_PATH` defaults to `admin`); production uses its own private path.
- Optional local 2FA: `ADMIN_MFA_REQUIRED=false` in `.env` turns admin two-factor off **only** when `APP_ENV=local`. Every other environment, tests and CI always require it.

## Git rules
- Branches: `main` is production (every merge deploys), `development` is local development plus CI only (no staging), and `feat/*`, `fix/*` and `chore/*` branch off `development`. Release = a PR from `development` into `main`.
- **No AI attribution:** never add `Co-Authored-By` trailers, "Generated with Claude Code" lines or any other AI/tool attribution to commits, PR titles/bodies, issues or code comments. The owner is the sole author.
- Use Conventional Commits. Keep commits small. Commit or push only when the owner asks, or as a phase plan they have approved says.
- Never commit `.env`, credentials, DB dumps or the `storage/` contents.
- Never push to or deploy `main` without the owner's explicit go-ahead.

## Releasing to production
- Flow: PR into `development` (CI must pass) → owner merges → PR `development → main` → **the owner merges it** → the `Deploy` workflow runs CI, builds, and pauses **twice** for the owner's approval in GitHub Actions (SSH preflight, then deploy). Keep both approvals (owner decision, 2026-11-23).
- The deploy migrates, caches, switches `app/current`, health-checks `/up` and rolls back automatically if the check fails.
- Rollback: Actions → **Rollback** → Run workflow on `main` (previous release, or a release id), then approve.
- After a release, smoke-test `https://chinmoybiswas.com` (`/up` shows the new release id). If the Hostinger CDN still serves an old response, clear the cache (Hostinger API `hosting_cache_clear`, with approval).
- Migrations must be backward-compatible with the previous release (rollback does not undo them). Take a `mysqldump` before any destructive migration.

## Code rules
- PHP 8.3, strict types, Pint (Laravel preset) and Larastan level 6 or higher must pass.
- Keep controllers thin. Put logic in Actions/Services. Use Form Requests for validation, Policies for authorization, and Enums for statuses.
- Store money as integer minor units with a currency code. Use no floats for money.
- Every feature ships with Pest tests (feature tests for HTTP and Filament, unit tests for money and invoice maths). Write the test first for invoice calculations.
- Blade and Tailwind v4 for the public site. Use Alpine.js only for small interactions. Do not use a SPA framework on the public side.
- Write accessible HTML: semantic landmarks, focus states, alt text and reduced-motion support.

## Hosting constraints (Hostinger Premium)
- No Node at runtime. Build assets locally or in CI, and deploy the built `public/build`.
- `exec`, `shell_exec`, `system` and `symlink` are disabled in PHP. Do not use packages that need them at runtime.
- The DB host from the app is `127.0.0.1`. Keep secrets only in the server `.env`.
- There are no long-running workers. Run queues and scheduled jobs from cron `schedule:run`.
- The Hostinger account also hosts other websites (listed in `.private/SERVER.md`). **Never modify them.**
- Any Hostinger write action (deploy, DNS, docroot, DB) needs the owner's confirmation first.

## Definition of done (per task)
Tests are green, Pint, Larastan and the build pass, behaviour is checked in a browser on the local server (screens for UI), and docs/ADR are updated if a decision changed.

## Server safety (shared SSH account: all three sites share one user)
- SSH alias, account and server details are in `.private/SERVER.md` (git-ignored). Never ask for or store the password.
- Work only inside `~/domains/chinmoybiswas.com/` and `~/backups/`. There is no staging site any more. Never `cd` into, list-modify, or touch the other domains' folders or databases.
- Read-only first (`ls`, `cat`, `php -v`). Before any change, state the exact commands and take a backup (`tar` of the folder, `mysqldump` of the DB) into `~/backups/`.
- Keep `app`, `app/releases`, `app/shared` and `app/shared/storage` at **755** and `shared/.env` at 600: with 700 the web server cannot reach the site.
- No `rm -rf` outside the app's own release folders, no `chmod -R`/`chown` above the app folder, no edits to `~/.ssh`, `~/.bashrc`, or the PHP config of other sites.
- Never run heavy work on the server (no `npm`, no large `composer` builds, no long loops). The shared server is already under load; builds happen in CI.
- Every ssh/scp/rsync and Hostinger API call requires the owner's approval (enforced in `.claude/settings.json`).
