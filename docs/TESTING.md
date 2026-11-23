# Testing strategy: portfolio-v4

Status: **Proposed (Phase 1)**, 2026-11-22. Every feature ships with tests (CLAUDE.md "Definition of done").

## 1. Pyramid and tools

```
          ▲  Lighthouse CI budgets (4 URLs, mobile)        per PR touching UI
         ▲▲  Browser smoke (Pest Browser plugin/Playwright) ~10 tests, CI
       ▲▲▲▲  Feature tests (HTTP, Filament/Livewire)       most of the suite
    ▲▲▲▲▲▲▲  Unit tests (money, invoice maths, domain)     fast, test-first
  ─────────  Static: Pint · Larastan · composer/npm audit  every push
```

| Tool | Purpose | Gate |
|---|---|---|
| **Pint** (Laravel preset) | Formatting | `pint --test` fails CI |
| **Larastan** level 6 at start, raised to 8 once stable | Types, Eloquent misuse | No new errors. A baseline is allowed only for vendor-generated code. |
| **Pest** (latest major compatible with Laravel 13, confirmed at install) + `pest-plugin-laravel` + `pest-plugin-livewire` | Unit + feature | 100% pass, `--parallel` |
| **Pest arch tests** | Layering rules | Part of the suite |
| **Pest Browser plugin** (drives Playwright) | Smoke + axe accessibility | Part of CI (needs `npx playwright install chromium` in CI only) |
| **Lighthouse CI** (`@lhci/cli`) | Perf/a11y/SEO budgets | Assertions fail the job |
| `composer audit`, `npm audit --omit=dev` | Known vulnerabilities | Fail on high or critical |

The browser layer uses the Pest Browser plugin because it **is** Playwright under the hood, while sharing Laravel's test DB, factories and `RefreshDatabase`. That gives one test runner. The `playwright-cli` skill is used for manual or visual checks against the local and staging servers.

**DB:** CI uses a MariaDB 11 service, matching production (JSON, collation and locking behaviour). Locally, SQLite in-memory is allowed for unit and most feature tests, with a `@group mariadb` marker on tests that need row locks (invoice numbering).

## 2. Coverage targets
| Area | Target | Why |
|---|---|---|
| `App\Domain\Invoicing\*` (calculator, numbering, state machine, money) | **100% lines + mutation testing** (`pest --mutate`, score ≥ 90%) | Financial correctness |
| Actions/Services | ≥ 90% | Business logic |
| HTTP + Filament resources | Every route/resource has at least one happy-path and one authz test | Regressions and access control |
| Overall | ≥ 80% (reported, not a hard gate except the Invoicing domain) | Avoid gaming the metric |

## 3. Unit tests: invoice maths (written **before** the code)
`tests/Unit/Invoicing/InvoiceCalculatorTest.php` uses datasets. All amounts are minor units.

| # | Case | Expectation |
|---|---|---|
| 1 | 1 line, qty 1, £100.00, no discount, 0% tax | subtotal 10000, tax 0, total 10000 |
| 2 | qty 1.5 (`1500` milli) × £33.33 | amount = 4999.5 → **5000** (half up) |
| 3 | 20% VAT on £10.01 | tax 200.2 → **200**, total 1201 |
| 4 | Tax on the total, not per line: 3 lines × £0.03 at 20% | subtotal 9, tax 1.8 → **2**, total 11 |
| 5 | 10% discount on £99.99 | discount 999.9 → **1000**, taxable 8999 |
| 6 | Fixed £10.00 discount on £99.99 | discount 1000, taxable 8999 |
| 7 | Canvas sample: £350 + 6 × £45 + 2.5 × £45, 5% discount, 0% VAT | subtotal 73250, discount 3663, total **69587** (£695.87) |
| 8 | Discount and 20% VAT together | VAT on the discounted total |
| 9 | Discount larger than subtotal; percent > 100%; tax rate > 100% | rejected |
| 10 | Negative qty/price or empty description | rejected (credit notes out of scope) |
| 11 | 0 lines | total 0 |
| 12 | Large values (qty 1000 × £99,999.99 at 20%) | no overflow (int64) |
| 13 | Property test: 200 random invoices | `total = subtotal − discount + tax`, `tax = round_half_up(taxable × rate)` |

Other units:
- `InvoiceNumberFormatter`: `INV` + 2026 + 1 → `INV-2026-0001`, 10000 → `INV-2026-10000`, custom prefix.
- `InvoiceStatus` transitions: an allowed/forbidden matrix (dataset over every pair).
- `ReadingTime`, `MarkdownRenderer` (escapes raw HTML, highlights a PHP fence, strips `javascript:` links), `Slug` uniqueness.

## 4. Feature tests

**Public HTTP** (`tests/Feature/Public/*`)
- Home renders the sections from seeded data. Drafts and unpublished items never appear (each listing).
- `/projects/{slug}`: published case study 200, draft 404, `has_case_study=false` 404, prev/next order.
- Blog index pagination, category/tag archives, post page shows reading time and highlighted code.
- **Cacheability:** public responses have **no `Set-Cookie`** and `Cache-Control` contains `s-maxage`. Admin responses are `private`/`no-store`.
- SEO: canonical, OG tags, JSON-LD parses as valid JSON with the expected `@type`; `/feed.xml` is valid RSS; `/sitemap.xml` lists only published URLs; staging `robots.txt` disallows all and adds `X-Robots-Tag`.
- `redirects` table → 301 with hit count. An unknown path gives the 404 view.
- Security headers are present (CSP with nonce, nosniff, referrer policy).
- `/cv` returns 200 with `Content-Disposition` when set and 404 when not.

**Admin / Filament** (`tests/Feature/Admin/*`, Livewire testing helpers)
- Guests are redirected to login. Non-admin users get 403. An admin without MFA is forced into MFA setup.
- No registration or reset routes exist (`route('filament.admin.auth.register')` undefined).
- Login is throttled after 5 failures.
- CRUD smoke for each resource (list, create, edit, validation errors).
- Publishing or updating any public model clears the response cache (`ResponseCache` faked or asserted). A cached second hit runs 0 DB queries.

**Invoices** (`tests/Feature/Invoicing/*`)
- Issue assigns sequential numbers (`INV-2026-0001`, `…0002`), resets in a new year (`travelTo`), snapshots client and seller, and locks items (edit attempts fail).
- Concurrent issue (MariaDB group): two transactions never get the same number (unique index + lock).
- Generate PDF writes to the private disk, stores the hash, and the response is `application/pdf`. Text extraction (`smalot/pdfparser`, dev) contains the number, total and currency symbol.
- Download: an admin gets 200 and a guest is redirected. The direct `/storage/...` path to the invoice does not exist publicly.
- Email (`Mail::fake()`): sends to the client with the PDF attached, creates an `invoice_emails` row with an immutable copy path and hash, and sets status Sent. An SMTP failure creates a `failed` row with the error, and the status is unchanged.
- `invoices:mark-overdue`: only Sent and past-due invoices change. Paid and Cancelled are untouched.

**Arch tests** (`tests/ArchTest.php`): `preset()->laravel()`, `preset()->security()`, controllers do not use `DB::`, `App\Domain` does not depend on `Illuminate\Http`, no `dd/dump/ray`, Enums in `App\Enums`.

## 5. Browser smoke (Pest Browser / Playwright), about 10 tests
1. `/` loads, has `assertNoSmoke()`, `assertNoAccessibilityIssues()`, and the sticky nav links scroll to their sections.
2. Mobile viewport: the nav is reachable by keyboard, and focus is visible.
3. Light/dark toggle persists (localStorage only, no cookie).
4. `/projects` → case study → next project.
5. `/blog` → post → tag archive.
6. `prefers-reduced-motion` disables animations.
7. Admin login with TOTP (time-mocked secret) → dashboard.
8. Admin: create a client → invoice draft → issue → PDF download link works.
9. 404 page renders with navigation.
10. No broken images on home or a case study (`assertNoBrokenImages()`).

## 6. Lighthouse budgets (Lighthouse CI, mobile, 3 runs median)
Run against `php artisan serve` with production build and seeded realistic content, on `/`, `/projects/{seeded}`, `/blog`, `/blog/{seeded}`.

| Metric | Budget |
|---|---|
| Performance / Accessibility / Best Practices / SEO | **≥ 95** each (error below 95) |
| LCP | **< 2000 ms** |
| CLS | **< 0.01** |
| TBT | < 150 ms |
| Total transfer (home) | < 350 KB |
| JS (public) | < 30 KB gzip (Alpine + app) |
| CSS | < 25 KB gzip |
| Fonts | ≤ 2 WOFF2 files, < 60 KB total |

`php artisan serve` does not compress responses, so the gzip budgets for CSS and JS are checked on the built files by `scripts/asset-budgets.sh`. Lighthouse checks scores, LCP, CLS, TBT, total transfer and font count. Run it locally with `scripts/lighthouse.sh` (it uses its own SQLite database and restores `.env`). Reports are kept as a CI artifact for 14 days.

Because CI runs from a US/EU runner against a local server, staging is also checked manually with the `playwright-cli` skill and PageSpeed Insights (real Hostinger origin and CDN) before each release to `main`.

## 7. CI order (fail fast)
`pint --test` → `phpstan` → `pest --parallel` (unit+feature+arch) → `npm run build` → browser smoke → Lighthouse CI. Deploy runs only after all of these are green (ADR-0003).

## 8. Gaps and risks
- DomPDF visual fidelity is not asserted (only text and hash). The owner reviews a sample PDF per template change.
- Email deliverability (SPF/DKIM/DMARC) cannot be unit-tested. Check it manually with mail-tester.com after the DNS move.
- Hostinger CDN/server-cache behaviour is verified on staging only (headers via `curl -I`), not in CI.
