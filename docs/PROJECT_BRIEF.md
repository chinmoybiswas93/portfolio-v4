# Portfolio v4 — Project Brief

Source of truth for scope and decisions. Update this file when a decision changes.
Decided with the owner on 2026-11-22.

## Goal
Replace the WordPress-hosted portfolio at https://chinmoybiswas.com (a Vue SPA served by the
`cb-portfolio` plugin, https://github.com/chinmoybiswas93/cb-portfolio) with a self-hosted
Laravel web app: a public business portfolio for UK/EU clients and a private admin panel.

## Positioning
- Personal brand **Chinmoy Biswas**, freelance web developer (WordPress, plugins, web apps) selling to UK and European businesses.
- Tone: professional and premium, with a technical feel. The aim is "trustworthy senior freelancer", not a student portfolio.

## Hosting facts (checked via the Hostinger API on 2026-11-22)
- Plan: Hostinger **Premium** shared web hosting. Account and server details are in `.private/SERVER.md` (git-ignored).
- `chinmoybiswas.com` (current WP, live) shares the account with other websites. **Never touch them.**
- PHP 8.3 is active (8.4 and 8.5 are available). Extensions include pdo_mysql, pdo_sqlite, gd, imagick, intl, mbstring, zip, fileinfo, bcmath and opcache.
- **No Node.js runtime** on Premium, so Node is used only at build time (locally or in CI).
- `exec`, `shell_exec`, `system`, `passthru` and `symlink` are disabled in PHP. Avoid any package that needs them at runtime (no headless-Chrome PDF, no Browsershot).
- DB (MariaDB 11.x) from the app: host `127.0.0.1:3306`. SSH works with key auth. CLI PHP 8.3, Composer 2 and Git are available on the server (checked 2026-11-22).

## Stack (decided)
- **Laravel 13** (changed from 12 on 2026-11-22: L12 security support ends 2027-02-24), PHP 8.3+ (pin 8.3 to match the server), **MariaDB 11.x** on the server (SQLite allowed for local tests).
- **Public site:** Blade + Tailwind CSS v4 + Alpine.js, server-rendered for SEO. Vite builds the assets.
- **Admin:** **Filament 5** (Livewire 4) at `/admin`.
- **PDF:** `barryvdh/laravel-dompdf` (pure PHP).
- **Media:** Spatie Media Library, or Filament's file upload with responsive images.
- **Markdown/code:** CommonMark plus server-side syntax highlighting (for example `tempest/highlight` or Shiki compiled at build time). Pick one in the architecture phase.
- **Tests:** Pest (feature and unit), Laravel Dusk or Playwright for a small set of end-to-end smoke tests, Larastan level 6+, Pint.
- Always confirm current library APIs with **context7** before coding against them.

## Public site scope
- Keep the current **two-column layout**: a sticky left panel (name, title, short intro, section nav, socials) and scrolling content on the right. Refine it; do not copy any third-party portfolio design.
- **Visual:** refined dark theme (premium typography, generous spacing, one accent colour, subtle motion), with an optional light-mode toggle. It must be responsive and accessible (WCAG 2.2 AA).
- **Home sections:** About, Services, Experience, Selected Projects, Latest Posts, Contact links.
- **Projects:** an archive page plus a **case-study detail page** for each project (client, sector, role, timeline, problem, approach, results/metrics, stack, gallery, live/GitHub links, next/previous project).
- **Blog:** list, post, category and tag archives; code highlighting; reading time.
- **Downloadable CV** (PDF uploaded in admin).
- **Contact:** links only (email, LinkedIn, other socials). No contact form.
- **SEO:** per-page meta, Open Graph/Twitter tags, generated OG images (or uploaded fallback), `sitemap.xml`, RSS feed, JSON-LD (Person, Article, CreativeWork), canonical URLs, clean slugs, 301 redirects for any old URLs.
- **Analytics:** cookieless (Cloudflare Web Analytics beacon), so no cookie banner is needed.
- **Performance budget:** Lighthouse 95+ on all four categories on mobile, LCP under 2.0s, no layout shift.

## Admin scope (Filament)
- Single admin user. **TOTP 2FA**, login rate limiting, no registration route, a secure session config, and an admin path that cannot be guessed is optional.
- Manage: site settings/profile (name, title, intro, about, socials, CV, SEO defaults), Services, Experience, Projects/Case studies (ordering, featured, draft/published), Blog posts, Categories, Tags, Media.
- **Invoices:**
  - **Clients** (saved and reusable): company, contact, email, address, VAT number, default currency.
  - **Invoice:** number (auto, configurable prefix), issue/due dates, currency (**GBP/EUR/USD**), line items (description, qty, unit price, tax rate), per-line or invoice-level **VAT/tax and discounts**, notes, payment details (bank/Wise/PayPal from settings).
  - **Status:** Draft, Sent, Paid, Overdue (scheduled check), Cancelled.
  - **PDF:** generate with DomPDF, **store the PDF in the database or storage with a DB record**, download, regenerate.
  - **Email** the PDF to the client from admin through Hostinger SMTP (`hello@`/own mailbox), and log the send.
  - Money is stored as integer minor units, never floats.
- **Content import:** a one-off importer for the old plugin's JSON export (About, 4 experiences, 6 projects). File: `portfolio-export-2026-11-21.json` in the repo root, git-ignored because it holds contact details and the repo is public.

## Environments and Git flow
- `main` is production (chinmoybiswas.com). `development` is integration and staging (`dev.chinmoybiswas.com`, created by the owner in hPanel as a separate website with its own `~/domains/dev.chinmoybiswas.com/`).
- Feature branches come off `development`, and changes return to it by PR with green CI.
- Work locally first. When the owner is happy, merge `development` into `main` to release.
- The WordPress site stays live until cutover. Back up the WP site and DB before cutover.

## CI/CD (to verify in the architecture phase)
- Hostinger's built-in Git deploy only pulls files and does not run `composer` or `npm`. Planned approach:
  **GitHub Actions** runs Pint, Larastan and Pest, builds the assets, `composer install --no-dev`, rsyncs over SSH to the Hostinger app dir, then runs `php artisan migrate --force` plus the config/route/view caches over SSH.
  - A push to `development` deploys to staging. A push to `main` deploys to production.
- The Laravel app lives outside `public_html`. Point the document root at `public/` (symlink via SSH or a `.htaccess` rewrite). Verify which works on Hostinger.
- Secrets live in GitHub Actions secrets and a server-side `.env` only, never in the repo.
- There are no queue workers on shared hosting. Use a `database` queue drained by the cron `schedule:run` (check the cron interval Hostinger allows), or send synchronously.

## Decisions from Phase 1 kickoff (2026-11-22)
- **Sender mailbox:** `hello@chinmoybiswas.com`, a single Hostinger mailbox for invoices and the public contact.
- **DNS / CDN (revised 2026-11-22):** **no Cloudflare proxy.** The domain stays registered at Namecheap, and DNS stays on Hostinger nameservers, managed in hPanel. Performance uses Hostinger-native tools (Laravel response cache, LiteSpeed server cache, Hostinger CDN). Server location is accepted as is. See ADR-0008.
- **Analytics:** **Cloudflare Web Analytics** (cookieless, free) as a **JS beacon only**. It needs no DNS change and no proxy.
- **Invoice number:** `{PREFIX}-{YYYY}-{NNNN}` with a yearly reset, for example `INV-2026-0001`. The prefix is set in settings.
- **Business/bank details:** the owner enters them in the Filament settings. They never go in the repo, seeders or chat.
- **Tax default:** not VAT-registered, so the default rate is 0% with a configurable note. Per-line and invoice-level tax stay available.
- **Deploy proof:** at the end of Phase 1, after ADR approval, deploy a hello Laravel app to `dev.chinmoybiswas.com`. Every server command needs owner approval.
- **Syntax highlighting:** `tempest/highlight` (pure PHP at render time). Shiki is ruled out because posts are edited at runtime and there is no Node runtime.
- **Media:** Spatie Laravel Media Library with Filament's Spatie plugin. See ADR-0005.

## Invoice tax decision (owner, 2026-11-22, before slice 3.5)
- **No per-line tax.** Lines have description, quantity and unit price only. Tax (VAT) is one optional rate applied to the **invoice total after discount**, so the "Tax" column is removed from the PDF and editor.
- Clients get an optional **tax ID** (VAT number for UK/EU, EIN/tax ID for US). It is printed on the invoice following UK/EU/US conventions. The exact wording is confirmed at the start of 3.5.

## Design decisions (Phase 2, approved by the owner on 2026-11-22)
Designs live in a private Claude design canvas (link in `.private/`). They are the reference for Phase 3 UI work.
- **Direction:** an updated version of the current site, not a redesign. It is minimal, clean and Apple-like calm, with nothing distracting.
- **Colour:** navy `#0F172A` background, slate text (`#E2E8F0` / `#94A3B8` / `#8291A8`), lines `#1E293B`. **One accent, teal `#2DD4BF`**, used only for links and titles on hover and the active state. Light theme: `#FFFFFF`, `#0F172A`, `#475569`, accent `#0F766E`. Every text pair passes WCAG AA.
- **Type:** the current site's **system font stack** (`-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, …`). No web fonts are loaded. Labels and dates are 12 px uppercase with letter-spacing.
- **Layout (home):** 1280 px container with 96 px side padding. The left column is 48% and **always fixed (sticky, full height)** on ≥1024 px: name, role, intro, section nav, and socials + theme toggle at the bottom. The right column is 52% and scrolls. Below 1024 px it is one column. There are no Email/CV buttons and no availability line in the left column.
- **Nav:** line-style section nav (About, Services, Experience, Projects, Writing). The active section is set by scroll position (its section top passes 30% of the viewport, and the last section is active at the page bottom). Smooth scrolling, turned off for reduced motion.
- **Lists:** Experience, Projects and Writing use a 150 px date/thumbnail column + content grid. Experience rows get a hover card (soft background + border, title turns teal). Projects have no hover box (title teal, thumbnail border brightens). Tags are outlined chips with a 5 px radius.
- **Motion:** a **mouse-following spotlight** (600 px radial, `rgba(29,78,216,0.15)`, as on the current site; off on touch and reduced motion) and 200 ms colour fades. Nothing else.
- **"More" links:** 17 px semibold, thin underline, teal on hover.
- **Logo:** the existing favicon traced to an exact SVG (98.4% pixel match). It is used for the favicon, social images and the invoice header, **not** in the site header.
- **Inner pages:** a slim top bar (name + theme toggle) and a centred reading column (case study, blog post).
- **Admin:** Filament 5 themed with the same navy surfaces and teal primary, using the system font.
- **Invoice PDF:** A4, table-based for DomPDF, logo + teal hairline accent, "VAT not applicable" note.

## Phases (each ends with a gate where the owner approves)
1. **Architecture:** ADRs (stack, hosting layout, CI/CD, PDF, media), data model/ERD, routes, security model, test strategy. Prove deploy with a "hello" Laravel app on the staging subdomain.
2. **UI design:** design every screen in a canvas (Claude design artifact) first: home, project archive, case study, blog list/post, 404, invoice PDF template, and key admin screens. The owner approves before any UI code is written.
3. **Build:** backend and data, then admin, then public frontend, then invoices, then SEO/perf. Each slice ships with tests.
4. **QA:** tests, Lighthouse, accessibility, security review, cross-browser checks on staging.
5. **Launch:** deploy checklist, WP backup, DNS/docroot switch, redirects, smoke test, rollback plan.
