# ADR-0001: Application stack

**Status:** Proposed
**Date:** 2026-11-22
**Deciders:** Owner (Chinmoy Biswas)

## Context
- The site is a public portfolio, blog and case studies (SEO-critical, UK/EU audience), plus a private admin with invoicing.
- The host is Hostinger Premium shared hosting. It has PHP 8.3.37 (8.4/8.5 available), **MariaDB 11.8** (the server's MySQL is MariaDB, checked 2026-11-22) and **no Node runtime**.
- PHP has `exec`, `shell_exec`, `system`, `passthru`, `popen`, `symlink`, `link` and `mb_send_mail` disabled. `proc_open` is available on the CLI.
- One developer (the owner), with a long-lived codebase and low running cost.
- Laravel 12 gets security fixes only until **2027-02-24**. Laravel 13 (released 2026-03-17, PHP ≥ 8.3) gets them until **2028-03-17**.

## Decision
| Layer | Choice |
|---|---|
| Framework | **Laravel 13**, PHP 8.3 (`"php": "^8.3"`, CI pinned to 8.3) |
| DB | **MariaDB 11.x**, Laravel `mariadb` driver. CI runs a MariaDB 11 service for parity. SQLite in-memory is allowed for fast local unit runs. |
| Admin | **Filament 5** (Livewire 4, Tailwind v4) at a configurable path, default `/admin` |
| Public UI | Blade + **Tailwind CSS v4** + Alpine.js (small interactions only), built by Vite in CI |
| Markdown | `league/commonmark` (GFM) + **`tempest/highlight`** (pure PHP). HTML is rendered **on save** into `body_html`, so there is no parsing cost per request. |
| Money | Integer minor units + ISO currency, with **`brick/money`** for arithmetic and rounding. No floats. |
| PDF | `barryvdh/laravel-dompdf` (ADR-0004) |
| Settings | `spatie/laravel-settings` + Filament settings plugin (typed, payment group encrypted) |
| Media | `spatie/laravel-medialibrary` + Filament Spatie plugin (ADR-0005) |
| SEO | `spatie/laravel-sitemap` (no crawler, built from models), `spatie/laravel-feed` (RSS), `spatie/schema-org` (JSON-LD), own Blade `<x-seo>` component |
| OG images | Generated at save time with `intervention/image` (GD) from a template. An uploaded image overrides it. |
| Quality | Pest, Larastan (level 6, aim for 8), Pint (Laravel preset), Playwright smoke tests (ADR-0003, TESTING.md) |

**Package rule:** before adding a package, check it does not need `exec`/`shell_exec`/`proc_open` at web runtime, headless Chrome, Node, or a long-running daemon.

## Options considered

### A: Laravel 13 + Filament 5 (chosen)
| Dimension | Assessment |
|---|---|
| Complexity | Low. Standard Laravel. |
| Cost | Free, and runs on the existing plan |
| Support window | Security fixes until 2028-03 |
| Familiarity | High (PHP/WordPress background) |

**Pros:** current majors, Filament has built-in TOTP MFA, Livewire 4, Tailwind 4 everywhere. **Cons:** some third-party Filament plugins may lag behind v5, so check each one at install.

### B: Laravel 12 + Filament 5 (as in the original brief)
**Pros:** a slightly larger plugin ecosystem. **Cons:** security EOL roughly at launch, which forces a major upgrade within months.

### C: Statamic / WordPress again
**Cons:** does not fit the invoicing requirement or the goal of a custom Laravel showcase. Rejected.

## Consequences
- Easier: one PHP stack, server-rendered HTML that is good for SEO and fully response-cacheable (ADR-0008).
- Harder: no SSR JS framework, and Markdown highlighting is limited to `tempest/highlight`'s supported languages (PHP, JS, CSS, HTML, SQL, JSON, YAML, Bash and more). That is enough for this blog.
- Revisit: PHP 8.4 once the server default and CI image are both 8.4. Upgrade to Laravel 14 within its support window.

## Action items
1. [ ] Phase 1 deploy proof: `laravel new` (13.x) skeleton deployed to staging.
2. [ ] Phase 3: check each package's Filament 5 / Laravel 13 compatibility at install (context7 and Packagist).
