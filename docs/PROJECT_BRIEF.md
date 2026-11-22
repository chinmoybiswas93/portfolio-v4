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
- **Discount:** invoice-level only (percent or fixed amount), no per-line discount.
- **Client tax ID** (optional) is labelled by country: UK "VAT reg. no.", EU "VAT ID", US "EIN / Tax ID", other "Tax ID".
- **0% to a UK/EU business with a tax ID:** the invoice prints "Reverse charge: the customer is liable to account for VAT." Otherwise it prints the default tax note ("VAT not applicable").
- **Owner's own tax ID** (optional, encrypted) is printed under the business details when filled in.

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

### Design changes after approval (owner review of slices 3.3–3.4, 2026-11-22)
The canvas is updated to match; it remains the source of truth for UI.
- **Inner pages:** one shared top bar on every inner page: hexagon logo mark + name on the left; PROJECTS / WRITING + theme toggle on the right.
- **Home:** Selected projects use the same hover card as Experience (soft background and border, title turns teal). The contact address has an envelope icon before it. Removed earlier: availability line, Email me / Download CV buttons, reply-time line.
- **Writing list:** full page width; each row is date | cover thumbnail (16:10) | title, excerpt and meta.
- **Post page:** the cover image sits between the lead paragraph and the body.
- **Admin project editor:** two columns as in the canvas (Basics, Case study tabs, Media | Publishing, Results metrics, Tech stack, Links, SEO). The header holds Preview / Save / Save and publish. Primary buttons are teal `#2DD4BF` with dark text.
- **Slugs (projects, posts, categories):** filled from the title on create while empty. After saving they are read-only, with an **Edit** link at the bottom right of the field and a URL preview; unlocking shows a warning to add a redirect.
- **Media:** every admin image upload is stored on the public disk (Filament's default was the private disk).
- **Contact email:** the importer never imports the old personal email; the site falls back to `hello@chinmoybiswas.com` until one is set in Profile.


### Built in slice 3.5 Invoices (2026-11-22, canvas v15)
- **Invoice editor:** no per-line Tax column; one "VAT rate on the total" field replaces the free-text tax note (the note is chosen by the tax rules). New **Project / reference** field, printed as "Project" on the PDF. Header actions that don't apply yet are hidden, not shown disabled; the status shows under the title.
- **PDF:** footer page numbers ("Page x / y") are drawn by DomPDF after rendering; seller tax ID prints under the business details when set.
- **Email:** sent synchronously; each attempt is logged with an immutable copy of the attached PDF (`invoices/{year}/sent/`).
- **Deleting:** only drafts can be deleted; issued invoices are cancelled instead, so numbering stays gap-free.
- **Dashboard:** "Outstanding by currency" and "Overdue invoices" built; "Recent posts" follows in 3.6.
- **Admin hardening:** a route-walk test checks every admin/filament screen (guest → login, non-admin → 403, admin without 2FA → set-up); a route inventory allows only the Livewire update/upload, signed storage upload and admin logout as write routes; tampered Livewire snapshots answer 419; unused Filament export/import downloads answer 404.

### Built in slice 3.6 SEO and performance (2026-11-22)
- **SEO:** canonical, Open Graph and Twitter tags on every page; JSON-LD (Person + WebSite, BlogPosting, CreativeWork, breadcrumbs); `/sitemap.xml`, `/feed.xml`, env-aware `/robots.txt`; generated 1200×630 share cards (navy card, teal label, logo, title).
- **Performance:** Lighthouse CI in CI on 4 URLs (all 100 locally on mobile, LCP ≈ 0.9–1.2 s, CLS 0); gzip budgets for CSS and JS; long-lived cache headers for hashed assets; LiteSpeed server cache kept off admin, Livewire and `/up`.
- **Accessibility:** the category link in a post's meta line is now underlined (subtle teal underline), so it doesn't rely on colour alone. Canvas updated.
- **Dashboard:** "Recent posts" widget (five most recently edited posts with status).
### Built in slice 3.8a Compact admin and standard table (2026-11-22)
- Compact admin theme (canvas v17): 232 px sidebar with uppercase, non-collapsible groups; 32 px controls; 22 px page titles; tighter cards.
- Top bar: global search (posts, projects, services, clients, invoices) and a "View site" link; the Welcome/Sign out widget is removed.
- One table design for every list: status tabs and filters inline in the toolbar (applied at once), search on the right, a single "⋯" row-actions button, ≈40 px rows, 25 rows per page.
- Dashboard: greeting with New post / New invoice; widgets load immediately.
- Invoice list: "Draft (no number yet)" in italics; amount and currency on one line.

### Built in slice 3.8b Edit layout, modal resources, Settings tabs (2026-11-22)
- Common edit layout on Projects, Posts, Services and Experience: Cancel, Preview (when public), Delete and Save in the page header; Create pages show Cancel and Create. "Save and publish" is removed: publishing is the Status field in the Publishing card.
- Categories, Tags, Redirects and Clients are managed on one list page with create and edit in a modal (no separate create/edit URLs).
- One Settings page (Filament cluster) with tabs Profile, SEO, Invoicing, Payment at `/admin/settings/*`; each tab keeps its own settings class, validation and encryption; "Save changes" sits in the header. The MCP tab arrives with 3.9c.

### Built in slice 3.8c Tags and slugs (2026-11-22)
- **One tag pool** for posts, projects and experience. A migration merged tags that shared a slug (links moved to the oldest tag) and dropped the topic/tech type. "Technologies" and "Skills" are only field labels. Every tag field searches, preloads and creates from the same pool; tag names that differ only in case or spacing resolve to the same tag.
- `/blog/tag/{slug}` shows any tag that has published posts (404 otherwise); the sitemap follows the same rule. The Tags list shows how many posts, projects and experience entries use each tag.
- **Slugs** on posts, projects, services, categories and tags: optional in the form (made from the title or name when empty, with -2, -3… when taken), normalised to lowercase-with-hyphens on blur, and checked inline for format and uniqueness. The lock-and-Edit behaviour is unchanged.

### Built in slice 3.8d Invoice actions and compact PDF (2026-11-22)
- **One action matrix** (`InvoiceStatus::availableActions()`), checked by the editor buttons and by every server-side action:
  - Draft: save, issue, duplicate, delete (a draft has no number, so it is deleted, not cancelled).
  - Sent / Overdue: regenerate PDF, download, email, mark as paid, cancel, duplicate.
  - Paid: regenerate PDF, download, email, duplicate.
  - Cancelled: download, duplicate.
- **Invoice PDF** in Inter (Regular, Medium, SemiBold; SIL OFL, files in `resources/fonts/inter`, loaded locally by DomPDF, no remote fonts) with the compact canvas v17 layout. DomPDF writes font metrics to `storage/fonts`, created on first use.

### Built in slice 3.8e Public features (2026-11-22)
- **Theme:** System / Light / Dark switch. First visit follows `prefers-color-scheme`; a chosen theme is saved in localStorage; System clears it. `public/theme-init.js` applies it before first paint (no flash, no inline script).
- **Post page:** breadcrumb; "On this page" contents from h2/h3 (sticky on desktop, collapsible on mobile, shown with two or more headings); share links for LinkedIn, X and Facebook (plain URLs, no scripts) and Copy link; related posts by shared tags, then same category; back to writing.
- **Case study:** the live-site and source links sit at the end of the tags row; gallery images open in a native `<dialog>` lightbox with arrows, ← → and Esc, a counter and the alt text as caption.

### Built in slice 3.8f Deploy preflight (2026-11-22)
- The Deploy workflow starts with an SSH preflight job (before CI and the build) and checks again just before upload. With SSH off, it fails within seconds: "SSH is off — enable SSH Access in hPanel and re-run". A rejected key and a host-key mismatch get their own messages. Rollback uses the same shared setup action (`.github/actions/ssh-setup`).
- Manual re-run: Actions → Deploy → Run workflow on `development` (staging). On `main` it stops with "enabled in Phase 5". Steps are in `docs/MANUAL_STEPS.md`.

### Built in slice 3.8g Private admin path and 2FA label (2026-11-22)
- The admin path is read from `ADMIN_PATH` (repo default `admin` for local work; staging and production set a private value in their server `.env`, never committed). The old `/admin` returns 404 once it is set. Tests run under a non-default path (`phpunit.xml`), so none can rely on `/admin`.
- `robots.txt` no longer lists the admin path; admin, Livewire and `/up` responses send `X-Robots-Tag: noindex, nofollow`.
- Authenticator label "CB Portfolio" for new 2FA set-ups; re-enrol steps are in `docs/MANUAL_STEPS.md`.

### Built in slice 3.8h Admin density and fixed width (2026-11-23)
- Admin content has a fixed, centred max width (Filament `6xl`, 72rem) instead of full width.
- Every section uses Filament's compact size. Modal forms (Categories, Tags, Redirects, Clients) have no inner card. Case-study tabs are left-aligned underline tabs with no extra box.
- Results/metrics and profile social links are compact table repeaters with a "+ Add …" text action.
- Follow-up (3.8i): metrics and social links stay as inline rows in narrow columns (no stacked boxes); dropdowns are 32 px like text inputs; "+ Add" links in teal; post SEO section always open.

### Built in slice 3.8j Theme toggle, transitions, share logos (2026-11-23)
- **Theme:** one sun/moon toggle, replacing the 3.8e System/Light/Dark switch. The first visit follows the device and shows the matching icon; a click saves light/dark in localStorage. Applied before the first paint.
- **Public transitions:** cross-document View Transitions (160 ms fade) and a 2 px teal progress bar on link clicks. Reduced motion turns off the fade and the bar animation.
- **Admin transitions:** Filament SPA mode (Livewire navigate) with a teal progress bar and a short content fade.
- **Share buttons:** LinkedIn, X and Facebook logos (inline SVG) and a link icon.

## Scope added before Phase 4 (owner, 2026-11-22)
Order: 3.7 → 3.8 → 3.9, with a stop for owner review at each gate.

### 3.7 Design update (canvas first, no code)
- Design-fidelity audit of every built screen (public and admin) against its canvas artboard, desktop and mobile, as a list of deviations per screen.
- Canvas changes, approved before any code:
  - **Admin density:** more compact spacing, padding, inputs and tables on every admin screen.
  - **One edit layout:** title + breadcrumbs left; Save / Cancel / Preview / Delete as header actions top-right; consistent section cards.
  - **Modal create/edit** for Categories, Tags, Redirects, Clients.
  - **One Settings page with tabs** (Profile, SEO, Payment, Invoicing, MCP, others) instead of separate pages under "Site".
  - **Blog post:** table of contents from h2/h3 (sticky on desktop, collapsible on mobile); breadcrumb, previous/next, back to writing, related posts by shared tags; share links for Facebook, LinkedIn, X and Copy link (plain share URLs, no third-party scripts or tracking).
  - **Theme toggle:** system / light / dark states.
  - **Invoice PDF:** more compact A4 layout set in Inter; tighter header, rows and totals; legible at print size.
  - **MCP admin screens:** OAuth consent (lists requested scopes), Connected apps (tokens, last used, revoke), MCP audit log, MCP on/off switch in the Settings tabs.
  - **Tables (owner, 2026-11-22):** one table design for every list. Status tabs, inline filters and search sit in one toolbar row inside the table card (canvas `Admin-Invoices` is the reference); "Showing x to y of z" plus numbered pages below; one "⋯" row-actions button.
  - **Case study (owner, 2026-11-22):** the live-site link sits at the right end of the tags row under the facts, not at the bottom.
  - **Gallery lightbox (owner, 2026-11-22):** case-study gallery images open in a modal with previous/next arrows, keyboard (← → Esc), a counter and the alt text as caption. Built in-house with Alpine, no third-party script.

### 3.8 UI and admin fixes (one PR per slice, CI green, staging deploy, stop)
- Fix every audit deviation; side-by-side screenshots in each PR.
- **Theme:** follow `prefers-color-scheme` on first visit; a chosen light/dark persists in localStorage; "system" resets it; no flash of the wrong theme.
- **Tags:** one shared pool for posts, projects and experience (migrate, merge duplicates). "Technologies"/"Skills" stay as field labels only. Search, preload and create work everywhere.
- **Slugs** on every sluggable resource: auto-generated from the title when blank, normalised, inline format and uniqueness errors, existing lock/edit behaviour kept.
- Common edit layout and modal resources as designed; Settings tabs page (each group keeps its validation and permissions); compact admin theme; blog post features.
- **Invoice actions:** only valid next transitions are shown for each status and enforced on the server; a Pest test per status.
- **Invoice PDF:** Inter (Regular/Medium/SemiBold TTF in the repo, loaded from the DomPDF font folder, no remote fonts) and the compact layout; PDF parser tests updated; before/after PDFs shown.
- **Deploy workflow:** SSH preflight that fails fast with "SSH is off — enable SSH Access in hPanel and re-run"; `workflow_dispatch` re-run for development and main. The owner keeps SSH off except when deploying.
- Route inventory and admin access tests updated for new routes and screens.

### Scope revision (owner, 2026-11-22, second brief)
Changes and additions to 3.7/3.8. Items already built in 3.8a–3.8f stay unless listed here.

**Design (3.7 revision, canvas v18–19)**
- **Admin density:** stricter compactness on every screen; no nested boxes. The main content (`.fi-main-content`) has a fixed, centred max width of about 1120 px instead of full width.
- **Project Results/metrics:** compact: no inner wrapper, 28 px rows, "+ Add metric" as a text action.
- **Theme switch:** reversed from 3.8e. One toggle with two icons (sun/moon) and no "system" icon. The first visit follows the OS and shows the matching icon; a click stores light/dark.
- **Page transitions:** a thin 2 px teal top progress bar plus a subtle fade, on the public site and in admin. With reduced motion, no fade and no animation.
- **Share buttons:** each has its brand logo (inline SVG) before the name. Plain share URLs, no scripts.
- **Invoices in admin:** a "Preview PDF" action (inline viewer with a version list) next to Regenerate/Download. The email log shows the status badge (Pending / Sent / Failed), the exact PDF version sent (previewable), recipient and time.
- **Invoice email:** invoice number, client, issue/due dates, amount due and currency, a payment summary, a "View invoice" button (secure link), the PDF attached, and the owner's contact details.

**Build (3.8 follow-up slices)**
- **Admin path:** `/admin` becomes a private path via `config('admin.path')` + `ADMIN_PATH` in each server `.env` (the value is never committed); the old `/admin` returns 404. Update tests, docs and CI health checks.
- **2FA label:** the authenticator brand name changes to "CB Portfolio".
- **Loading:** Filament SPA mode with a progress bar in admin; cross-document View Transitions plus a top progress bar on the public site; no flash; reduced motion respected.
- **Theme:** two icons (as above), applied before the first paint.
- **Currency:** BDT (Bangladeshi taka, ৳, 2 decimals). If Inter in DomPDF lacks ৳, the PDF shows the code "BDT". Tests for BDT totals and formatting.
- **PDF versions:** every generated PDF is kept (never overwritten); each email log entry links to the exact version sent; Preview action.
- **Invoice email:** new template. PDF attached, plus a "View invoice" button with a signed link that expires after 30 days and serves only that one PDF version (no login, no listing). Status goes Pending (queued) → Sent (accepted by SMTP) or Failed (with the error). "Sent" means accepted by the mail server, not opened. Tests cover expired, tampered and other-invoice links.
- **Route tests:** the route inventory and access tests cover the new admin path, the signed invoice link and the preview.
- **3.9 MCP:** the consent screen sits behind the private admin path + 2FA. MCP starts only after these 3.8 follow-ups are merged and approved.

### 3.9 MCP server (ADR + threat model first, then slices)
- **Transport:** official `laravel/mcp`, Streamable HTTP at `/mcp`, stateless (fits shared hosting).
- **Auth:** OAuth 2.1 via Laravel Passport with discovery endpoints and dynamic client registration (Claude Code, Claude.ai, Codex, ChatGPT). Consent screen behind the admin login + required 2FA; the owner approves each client and its scopes. Short-lived access tokens with refresh; connected apps listed and revocable in admin.
- **Scopes:** `content:read`, `content:write`, `content:trash`, `seo:write`, `media:upload`, `invoices:read`, `invoices:draft`, `settings:read`, `settings:write`.
- **Tools** reuse the existing Actions, Policies and validation. No raw SQL, file, shell or config tools; MCP never touches code, files or the database directly.
- **Content** in and out as Markdown; rendering stays safe (CommonMark, raw HTML escaped, unsafe links blocked).
- **Deletes** are soft deletes to a trash: SoftDeletes + admin trash filter, restore and permanent delete for posts, projects, services, tags, categories and clients. MCP can never permanently delete.
- **Invoices:** read invoices and clients; create and edit drafts only. Issue, paid/cancelled and email stay admin-only.
- **Settings:** read profile/SEO; write profile and SEO only. Payment, invoicing and security/2FA settings are excluded from MCP.
- **Images:** upload from file data only (no URL fetching); jpeg/png/webp allowlist; size limit; re-encoded to strip EXIF and payloads; stored via the media library; returns media id, URL and a Markdown snippet; usable as cover, gallery or inline.
- **Safety:** MCP on/off switch in Settings (off = 503 on `/mcp`); audit log of every tool call (client, tool, target, before/after summary, time, IP) viewable in admin; rate limit per token.
- **Tests:** guest 401, missing scope 403, switch off blocks calls, trashed items are not public, draft-only invoices enforced; `/mcp` and OAuth routes added to the route inventory on purpose.
- **Slices:** 3.9a OAuth + server + read tools; 3.9b content/SEO write, trash, media upload; 3.9c invoice drafts, audit log UI, switch, connected apps. Security review on every slice. After 3.9a: exact steps to connect Claude Code to `dev.chinmoybiswas.com/mcp` and verify the browser approval flow. An ADR with a threat model is written and approved before any 3.9 code.

## Phases (each ends with a gate where the owner approves)
1. **Architecture:** ADRs (stack, hosting layout, CI/CD, PDF, media), data model/ERD, routes, security model, test strategy. Prove deploy with a "hello" Laravel app on the staging subdomain.
2. **UI design:** design every screen in a canvas (Claude design artifact) first: home, project archive, case study, blog list/post, 404, invoice PDF template, and key admin screens. The owner approves before any UI code is written.
3. **Build:** backend and data, then admin, then public frontend, then invoices, then SEO/perf. Each slice ships with tests.
4. **QA:** tests, Lighthouse, accessibility, security review, cross-browser checks on staging.
5. **Launch:** deploy checklist, WP backup, DNS/docroot switch, redirects, smoke test, rollback plan.
