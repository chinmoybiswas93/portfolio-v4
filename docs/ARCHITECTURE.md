# Architecture: portfolio-v4

Status: **Proposed (Phase 1)**, 2026-11-22. Decisions are in `docs/adr/`. Scope is in `docs/PROJECT_BRIEF.md`.

## 1. Requirements summary
| Kind | Requirement |
|---|---|
| Functional (public) | Home (About, Services, Experience, Selected Projects, Latest Posts, Contact links), project archive + case studies, blog with categories/tags, CV download, sitemap/RSS/OG/JSON-LD |
| Functional (admin) | Filament CRUD for content, settings, clients, invoices (PDF, email, statuses) |
| Non-functional | Lighthouse 95+ mobile, LCP < 2.0s in the UK/EU, WCAG 2.2 AA, cookieless public pages, TOTP-protected admin |
| Constraints | Hostinger Premium shared (PHP 8.3, MariaDB 11.8, LiteSpeed, no Node or daemons, `exec`/`symlink()` disabled), one developer, about €0 extra running cost |
| Load estimate | < 10k page views/month. Admin: 1 user. Invoices: about 5–30/month. Response-cache hit rate is expected above 90%. |

## 2. High-level design

```mermaid
flowchart LR
  V[Visitor] -->|HTTPS| CDN[Hostinger CDN<br/>static assets]
  O[Owner] -->|HTTPS + TOTP| CDN
  CDN --> LS[LiteSpeed - Hostinger<br/>public_html -> app/current/public]
  LS --> L[Laravel 13<br/>public Blade + Filament 5]
  L --> DB[(MariaDB 11)]
  L --> FS[(shared/storage<br/>public media · private invoices)]
  L -->|SMTP| M[Hostinger mail<br/>hello@]
  L --> RC[(response cache<br/>file store)]
  CRON[hPanel cron * * * * *] --> S[schedule:run<br/>queue:work --stop-when-empty]
  S --> L
  GH[GitHub Actions] -->|rsync + ssh| LS
```

Data flows:
- **Publish:** admin save → Markdown rendered to `*_html` → OG image generated → sitemap regenerated → response cache cleared.
- **Invoice:** draft → *Issue* (number assigned, snapshots frozen, PDF generated) → *Email* (immutable PDF copy + `invoice_emails` row) → *Paid* / *Overdue* (daily job) / *Cancelled*.

## 3. Data model

Conventions: `id` bigint PK, `timestamps` on every table, slugs unique, `status` columns are backed PHP Enums, money in **integer minor units** (`*_minor` BIGINT) + `currency` CHAR(3), rates in **basis points** (`*_bp`, 2000 = 20%), quantities in **thousandths** (`quantity_milli`, 1500 = 1.5). Rendered HTML is cached in `*_html` next to its `*_md` source.

```mermaid
erDiagram
  users {
    bigint id PK
    string name
    string email UK
    string password
    bool is_admin
    text app_authentication_secret "encrypted"
    text app_authentication_recovery_codes "encrypted"
  }
  settings {
    bigint id PK
    string group "profile|seo|invoicing|payment|social"
    string name
    json payload "payment group encrypted"
    bool locked
  }
  services {
    bigint id PK
    string title
    string slug UK
    string summary
    text body_md
    text body_html
    string icon
    int sort_order
    bool is_published
  }
  experiences {
    bigint id PK
    string company
    string company_url
    string position
    string location
    date started_on
    date ended_on "null = current"
    text description_md
    text description_html
    int sort_order
    bool is_published
  }
  projects {
    bigint id PK
    string title
    string slug UK
    string summary
    string client_name
    string sector
    string role
    string made_at "e.g. Personal / company"
    smallint year
    date started_on
    date ended_on
    text problem_md
    text approach_md
    text results_md
    text body_html "rendered sections"
    json metrics "[{label,value}]"
    string live_url
    string repo_url
    bool is_featured
    bool has_case_study
    string status "ProjectStatus: draft|published"
    datetime published_at
    int sort_order
    string seo_title
    string seo_description
    datetime deleted_at
  }
  categories {
    bigint id PK
    string name
    string slug UK
    string description
  }
  posts {
    bigint id PK
    bigint category_id FK
    string title
    string slug UK
    string excerpt
    text body_md
    longtext body_html
    smallint reading_minutes
    string status "PostStatus: draft|scheduled|published"
    datetime published_at
    bool is_featured
    string seo_title
    string seo_description
    datetime deleted_at
  }
  tags {
    bigint id PK
    string name
    string slug
    string type "TagType: topic|tech"
  }
  taggables {
    bigint tag_id FK
    string taggable_type "post|project|experience"
    bigint taggable_id
  }
  media {
    bigint id PK
    string model_type
    bigint model_id
    string collection_name "cover|gallery|images|og|cv|avatar|logo"
    string disk
    string file_name
    string mime_type
    bigint size
    json custom_properties "alt, width, height"
    json generated_conversions
    json responsive_images
    int order_column
  }
  redirects {
    bigint id PK
    string from_path UK
    string to_path
    smallint status_code "301"
    int hits
  }
  clients {
    bigint id PK
    string company_name
    string contact_name
    string email
    string address_line1
    string address_line2
    string city
    string postcode
    char country_code "ISO-3166 alpha-2"
    string vat_number
    char default_currency "GBP|EUR|USD"
    text notes
    datetime deleted_at
  }
  invoices {
    bigint id PK
    bigint client_id FK
    string number UK "null while draft"
    smallint sequence_year
    int sequence_no "unique(year,no)"
    string status "InvoiceStatus"
    char currency
    date issue_date
    date due_date
    json client_snapshot "frozen at issue"
    json seller_snapshot "business + payment details at issue"
    string discount_type "none|percent|fixed"
    int discount_value "bp or minor units"
    bigint subtotal_minor
    bigint discount_minor
    bigint tax_minor
    bigint total_minor
    text notes
    string tax_note "e.g. VAT not applicable"
    string pdf_path
    string pdf_sha256
    datetime pdf_generated_at
    datetime issued_at
    datetime sent_at
    datetime paid_at
    datetime cancelled_at
  }
  invoice_items {
    bigint id PK
    bigint invoice_id FK
    smallint position
    string description
    int quantity_milli
    bigint unit_price_minor
    int discount_bp
    int tax_rate_bp
    bigint net_minor
    bigint discount_minor
    bigint tax_minor
    bigint total_minor
  }
  invoice_emails {
    bigint id PK
    bigint invoice_id FK
    bigint sent_by FK
    string to_email
    json cc
    string subject
    text body
    string pdf_path "immutable copy"
    string pdf_sha256
    string status "EmailStatus: sent|failed"
    text error
    string message_id
    datetime sent_at
  }

  categories ||--o{ posts : has
  tags ||--o{ taggables : links
  posts ||--o{ taggables : tagged
  projects ||--o{ taggables : "tech stack"
  experiences ||--o{ taggables : skills
  projects ||--o{ media : "cover, gallery, og"
  posts ||--o{ media : "cover, images, og"
  services ||--o{ media : icon
  clients ||--o{ invoices : billed
  invoices ||--|{ invoice_items : contains
  invoices ||--o{ invoice_emails : "send log"
  users ||--o{ invoice_emails : sent_by
```

Framework tables (not shown): `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens` (unused).

### Notes and trade-offs
- **Settings:** `spatie/laravel-settings` (+ Filament settings plugin) with typed classes `ProfileSettings` (name, title, tagline, about, public location, email, socials), `SeoSettings` (defaults, default OG image path), `InvoiceSettings` (prefix `INV`, default due days, default currency, default tax rate 0, tax note, footer), and `PaymentSettings` (business name/address, bank/Wise/PayPal details, **encrypted**). Single-file site assets (CV, avatar, invoice logo, default OG) are stored as settings file paths via Filament `FileUpload`, not media rows. This avoids a fake singleton model. *Revisit* if responsive avatars are needed.
- **Tags with `type`:** one polymorphic table serves blog topics (`topic`) and the tech stack (`tech`) for projects and experience. It avoids two near-identical tables. Tag archive routes use only `topic`.
- **Experience:** `ended_on = null` means current. There is no separate `current` flag (avoids contradictory states).
- **Projects:** `has_case_study=false` projects appear in the archive but link to the live URL instead of a detail page.
- **Invoice numbering (`INV-2026-0001`):** drafts have **no number**. On *Issue*, a transaction runs `SELECT MAX(sequence_no) … WHERE sequence_year=? FOR UPDATE`, then +1 and a unique index `(sequence_year, sequence_no)`. This gives gap-free, sequential numbers as UK/EU practice expects. Issued invoices are **locked**: items, amounts and snapshots are read-only, and corrections are made by cancel + reissue (or a credit note, out of scope).
- **Invoice maths** (`App\Domain\Invoicing\InvoiceCalculator`, pure and unit-tested first):
  1. `net = round_half_up(quantity_milli × unit_price_minor / 1000)`
  2. `line_discount = round_half_up(net × discount_bp / 10000)`
  3. The invoice-level discount (percent or fixed) is **allocated pro rata** across lines by taxable amount using the largest-remainder method, so allocations sum exactly to the discount and tax is computed on the discounted amount.
  4. `line_tax = round_half_up(taxable × tax_rate_bp / 10000)`, rounded **per line**.
  5. Totals are sums of line values. Every amount uses the invoice currency (2 decimal places for GBP/EUR/USD, via `brick/money` contexts).
- **Statuses** (`InvoiceStatus`): `Draft → Issued(Sent) → Paid`, `Sent → Overdue` (daily job when `due_date < today`), `Overdue → Paid`, and any non-paid status → `Cancelled`. *Sent* is set by a successful email or a manual "mark as sent". A transition guard rejects illegal transitions.

## 4. Route map

### Public (middleware group `public`: no session, no cookies, no CSRF, response-cached)
| Method | Path | Name | Notes |
|---|---|---|---|
| GET | `/` | home | Sections from settings, services, experience, featured projects, latest 3 posts |
| GET | `/projects` | projects.index | All published, ordered |
| GET | `/projects/{project:slug}` | projects.show | Case study, prev/next, CreativeWork JSON-LD. 404 if no case study. |
| GET | `/blog` | posts.index | `?page=` paginated |
| GET | `/blog/{post:slug}` | posts.show | Article JSON-LD, reading time |
| GET | `/blog/category/{category:slug}` | posts.category | |
| GET | `/blog/tag/{tag:slug}` | posts.tag | `type=topic` only |
| GET | `/cv` | cv | Streams the CV PDF from settings path. 404 if none. |
| GET | `/feed.xml` | feed | RSS 2.0 (spatie/laravel-feed) |
| GET | `/sitemap.xml` | (static file) | Generated (ADR-0006) |
| GET | `/robots.txt` | robots | Env-aware (staging disallows all) |
| GET | `/up` | health | Laravel health + release id + last scheduler run |
| * | fallback | — | `redirects` lookup (301 + hit count), else the 404 view |

Trailing-slash and `www` are redirected to the apex canonical (`public/.htaccess` rule plus a Laravel fallback).

### Admin (Filament, `/{ADMIN_PATH}`, default `/admin`; `web` + auth + MFA)
| Area | Resource / page | Key actions |
|---|---|---|
| Dashboard | Widgets | Outstanding total per currency, overdue list, recent posts |
| Content | Services, Experiences, Projects, Posts, Categories, Tags | Reorder, publish/unpublish, preview, featured |
| Site | Settings pages: Profile, SEO, Invoicing, Payment | CV/avatar/logo upload |
| Site | Redirects | CRUD |
| Billing | Clients | CRUD, view invoices |
| Billing | Invoices | Create/edit draft, **Issue**, Generate/Regenerate PDF, Download, **Email**, Mark sent / paid, Cancel, Duplicate |
| Account | Profile, MFA setup, recovery codes | — |

Also: `/livewire/*` (Filament transport, behind the same Access rule), and `GET /{ADMIN_PATH}/invoices/{invoice}/pdf`, which uses the auth policy and streams from the private disk.

## 5. Security model

| Asset | Threat | Control |
|---|---|---|
| Admin account | Credential stuffing, brute force | Required TOTP + recovery codes, Filament login throttle, app-level IP throttle on the login POST, no registration or reset routes, optional hPanel IP blocking (ADR-0007) |
| Client PII, invoices | Unauthorised read | Private disk only, policy-checked download route, unpredictable admin path, encrypted payment settings, no PII in logs |
| Secrets | Repo leak | `.env` only on the server (`chmod 600`), GitHub Environment secrets, `.gitignore` covers `.env*`, dumps and the export JSON. `composer audit` + Dependabot. |
| Public pages | XSS via content | CommonMark `html_input: 'escape'`, `allow_unsafe_links: false`. Blade escapes by default. Strict **CSP** with Vite nonces on public pages (`default-src 'self'`, Cloudflare Insights beacon allowed, `frame-ancestors 'none'`). |
| Admin pages | XSS / clickjacking / CSRF | Laravel CSRF, `SameSite=Lax` secure cookies, admin CSP (Livewire/Alpine-compatible), `X-Frame-Options: DENY` |
| Uploads | Malicious files | MIME + extension allow-lists, size limits, files served from `storage/` (no PHP execution: `.htaccess` `php_flag engine off` / `RemoveHandler` in `storage`) |
| Deploy channel | Key theft, MITM | Dedicated CI key, pinned host key, production needs manual approval (ADR-0003) |
| Mail | Spoofing, poor deliverability | SPF, DKIM, DMARC (`p=quarantine`) for the domain, checked in hPanel (Emails → DNS) |
| Shared account | Collateral damage to other sites | Deploy script confined to `$DEPLOY_PATH`, CLAUDE.md server-safety rules, owner approval for every server write |

Global headers (middleware): `Strict-Transport-Security: max-age=31536000; includeSubDomains` (once HTTPS is confirmed on both sites), `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`.

## 6. SEO
- **Meta:** the `<x-seo>` component sets the title (`{page} · Chinmoy Biswas`), description, canonical (absolute, apex, no query except `page`), `og:*`, `twitter:card=summary_large_image`, `article:published_time`/`modified_time` for posts. Staging is `noindex`.
- **OG images:** 1200×630 PNGs generated on save (Intervention/GD) from the title, type label and accent colour, stored as an `og` media collection. An uploaded override wins. The fallback is the default from SEO settings.
- **JSON-LD** (`spatie/schema-org`): `Person` + `WebSite` on home, `BlogPosting` on posts, `CreativeWork` on case studies, and `BreadcrumbList` on detail pages.
- **Sitemap:** home, `/projects`, case studies, `/blog`, posts, categories, tags with posts. `lastmod` = `updated_at`.
- **RSS:** `/feed.xml`, latest 20 published posts, full content, linked via `<link rel="alternate">`.
- **Redirects:** the `redirects` table (admin-managed) for old URLs. The old site is a Vue SPA in WordPress, so collect its indexed URLs from Search Console or `site:` before cutover (Phase 5).
- **Performance:** see ADR-0008. Budgets are in `docs/TESTING.md`.

## 7. Scale, reliability, monitoring
- The response cache makes origin load trivial. MariaDB stays tiny (hundreds of rows).
- **Backups:** Hostinger daily backups + a pre-deploy `mysqldump` option + a pre-cutover full backup. Restore steps go in the Phase 5 runbook.
- **Monitoring:** a free uptime check on `/up` (for example UptimeRobot), Laravel `daily` logs (14 days), and failed jobs visible in admin.
- **Revisit when:** many more users or comments, or a contact form is added (needs spam protection and possibly sessions on the public side). Also if the invoice volume needs credit notes or multi-user access.
