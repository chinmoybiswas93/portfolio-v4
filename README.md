# Portfolio v4

A self-hosted portfolio, blog, case-study site and invoicing back office in one Laravel app. It is generic: your name, logo, domain and contact details live in the admin settings and your server `.env`, not in the code.

The public site is server-rendered Blade with Tailwind CSS v4. Everything is managed from a Filament admin panel, and an MCP server lets an AI assistant such as Claude read and edit content after the owner approves it.

## Features

**Public site**
- Two-column home page with About, Services, Experience, Projects and Writing sections
- Project archive and case-study pages (client, role, problem, approach, results, metrics, gallery, links)
- Blog with categories, tags, syntax-highlighted code and reading time
- Dark and light themes, reduced-motion support, built for WCAG 2.2 AA
- SEO: Open Graph and Twitter tags, JSON-LD, `sitemap.xml`, RSS feed, `robots.txt`, generated share images and 301 redirects
- Cookieless analytics, so no cookie banner

**Admin (Filament 5)**
- Projects and case studies, posts, services, experience, categories, tags, redirects and media
- Profile, SEO, invoicing and payment settings
- Single admin user, required two-factor authentication, login rate limiting and a configurable admin path
- Clients and invoices: auto numbering (`INV-2026-0001`), GBP, EUR and USD, discounts, optional VAT, PDF export with DomPDF, email with an immutable sent copy, and status tracking (draft, sent, paid, overdue, cancelled)
- Money is stored as integer minor units with a currency code, never as floats

**MCP server**
- Streamable HTTP endpoint at `/mcp`, with OAuth 2.1 and PKCE through Laravel Passport
- Every assistant must be approved by the owner on a consent screen, behind admin login and 2FA
- Tools for posts, projects, services, experience, categories, tags, profile, SEO, clients, invoices and image upload
- Safe by design: deletes go to the trash, invoices are draft-only, and payment and security settings are never exposed
- Kill switch, audit log and per-token rate limit

## Stack

| Area | Choice |
|---|---|
| Framework | Laravel 13, PHP 8.3 |
| Admin | Filament 5 (Livewire 4) |
| Public UI | Blade, Tailwind CSS v4, Alpine.js, Vite |
| Database | MariaDB 11 in production, SQLite locally |
| PDF | `barryvdh/laravel-dompdf` (pure PHP) |
| Media | Spatie Media Library |
| Markdown | `league/commonmark` and `tempest/highlight` |
| MCP and auth | `laravel/mcp`, `laravel/passport` |
| Tests and quality | Pest 4, Pest Browser (Playwright), Larastan level 6, Pint, Lighthouse CI |

It is designed to run on shared PHP hosting: no Node at runtime, no queue workers (the scheduler runs from cron), and no packages that need `exec` or `symlink`.

## Getting started

**Requirements:** PHP 8.3 with the usual Laravel extensions (`pdo_sqlite`, `gd` or `imagick`, `intl`, `mbstring`, `zip`, `fileinfo`, `bcmath`), Composer 2, Node 24 (build time only, as used in CI).

```bash
git clone <this-repository-url> portfolio-v4
cd portfolio-v4

composer setup          # install, create .env, generate key, migrate, build assets
touch database/database.sqlite   # if it does not exist yet
php artisan db:seed     # optional: starter services (safe to re-run)
php artisan app:create-admin     # create the admin user
composer dev            # start the dev processes (runs `php artisan dev`)
```

Then open the URL printed by `composer dev` (or your Herd or Valet site). The admin is at `/admin`, which is `ADMIN_PATH` in `.env`.

Two-factor authentication is always required. For local development only, you can turn it off by setting `ADMIN_MFA_REQUIRED=false` in `.env`. It is ignored in every other environment.

If you use Laravel Herd, link the folder, run `herd php artisan ...` and `herd composer ...` so the PHP version matches the project.

## Configuration

Copy `.env.example` to `.env` and adjust it. The settings that matter most:

| Variable | Purpose |
|---|---|
| `APP_URL` | Public URL, used in links, feeds and share images |
| `DB_CONNECTION` | `sqlite` locally, `mariadb` in production (add `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) |
| `ADMIN_PATH` | URL path of the admin panel. Use a private value in production |
| `MAIL_*` | Mail settings. `MAIL_MAILER=log` writes emails to the log locally |
| `BRAND_SITE_NAME` | Name shown when Settings → Profile has none (default `Your Name`) |
| `BRAND_DOMAIN` | Domain printed on generated share images (default `example.com`) |
| `BRAND_CONTACT_EMAIL` | Contact address when Settings → Profile has none (default `hello@example.com`) |
| `BRAND_ADMIN_NAME` | Name in the admin header and MCP consent screens (default: site name) |
| `BRAND_MCP_NAME` | Name the MCP server reports (default: site name + " portfolio") |
| `MCP_ENABLED` | Hard switch for the MCP server, off by default |

Settings entered in the admin override the `BRAND_*` defaults. Business details, bank details, invoice prefix and SEO defaults are entered under **Settings**. Upload your logo, favicon, touch icon and share-image logo under **Settings → Branding**; the bundled files are used until you do. None of this goes in the repository.

## Using the admin

1. Sign in at `/admin` and finish the two-factor set-up on first login.
2. Fill in **Settings → Profile** (name, title, intro, about, socials, CV) and **SEO**.
3. Add **Services**, **Experience**, **Projects** and **Posts**. Slugs are filled from the title, and a status field controls whether an item is draft or published.
4. For invoicing, add a **Client**, create an **Invoice**, issue it to get a number, download or email the PDF, and mark it as paid.

Lost access? Reset the password (and optionally 2FA) from the command line:

```bash
php artisan app:admin-reset you@example.com --mfa
```

## Connecting an AI assistant (MCP)

1. Set `MCP_ENABLED=true` in `.env`, then turn the server on under **Settings → MCP**.
2. Add `https://your-site.example/mcp` as a remote MCP server in your assistant. Examples:
   - Claude Code: `claude mcp add --transport http portfolio https://your-site.example/mcp`
   - Claude.ai or other clients: add it as a custom connector with that URL.
3. The assistant registers itself and opens the consent screen. Sign in with your admin account and 2FA, choose which scopes to allow, and approve.
4. Ask the assistant to list, draft or update content. Anything it saves can be set to `draft` so you can review it in the admin first.

The design and threat model are in [ADR-0009](docs/adr/0009-mcp-server.md) and [`docs/security/mcp-threat-model.md`](docs/security/mcp-threat-model.md).

## Development

```bash
composer test                    # Pest suite (config cache cleared first)
vendor/bin/pint --test           # code style
vendor/bin/phpstan analyse       # Larastan, level 6
npm run build                    # production assets
```

Rules the code follows: strict types, thin controllers, logic in Actions and Services, Form Requests for validation, Policies for authorisation, Enums for statuses, and a Pest test for every feature. Invoice maths is tested first. See [`docs/TESTING.md`](docs/TESTING.md) for the test strategy.

## Deployment (shared hosting outline)

The app is built for PHP-only shared hosting. A typical setup:

1. Create a MariaDB database and a `.env` on the server (never in git) with `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, the `DB_*` values, `MAIL_*`, `ADMIN_PATH` (use a long random value) and the `BRAND_*` keys.
2. Build in CI or locally: `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build`. Upload the built app, including `public/build`, to a release folder.
3. Point the web root at the release's `public/` folder, for example through a `current` symlink or the host's document-root setting.
4. Run `php artisan migrate --force`, `php artisan storage:link` (or your host's equivalent) and the cache commands (`config:cache`, `route:cache`, `view:cache`).
5. Add a cron entry that runs `php artisan schedule:run` every minute. There are no queue workers.
6. Create the admin with `php artisan app:create-admin`, then finish two-factor set-up at your admin path.
7. Health-check `/up` after each release and keep the previous release for rollback.

The repository's GitHub Actions workflows (`ci.yml`, `deploy.yml`, `rollback.yml`) automate steps 2 to 7 over SSH with approval gates. They read all server details from GitHub Actions secrets. See [`docs/adr/0002-hostinger-app-layout.md`](docs/adr/0002-hostinger-app-layout.md) and [`docs/adr/0003-ci-cd.md`](docs/adr/0003-ci-cd.md).

## Documentation

| File | Contents |
|---|---|
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | How the app is structured |
| [`docs/adr/`](docs/adr) | Architecture decision records |
| [`docs/TESTING.md`](docs/TESTING.md) | Test strategy |
| [`docs/QA_REPORT.md`](docs/QA_REPORT.md) | QA results |

## Contributing

Bug reports are welcome as GitHub issues. Please do not include private data in them. For security problems, follow [`SECURITY.md`](SECURITY.md).

## Licence

Released under the [MIT licence](LICENSE).
