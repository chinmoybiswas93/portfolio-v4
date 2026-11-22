# Phase 4 QA report

**Date:** 2026-11-23 · **Scope:** the whole app on `development` after slice 3.9 · **Status:** local checks done; staging checks pending (see the end).

## Summary
| Area | Result |
|---|---|
| Performance (Lighthouse, mobile) | 100 on all 5 public pages; LCP 0.9–1.4 s; CLS 0 |
| Performance (Lighthouse, desktop) | 100; LCP 0.2–0.4 s; CLS 0 |
| Accessibility (Lighthouse) | 100 on every public page |
| Accessibility (axe, every impact level) | Public pages (light, dark, mobile), all 23 admin screens and the login pass after 4 fixes |
| Keyboard | Skip link first on every page and reaches the content; every focusable element shows a focus ring |
| Cross-browser | 27 browser tests pass in Chromium and WebKit; Firefox passes all but the axe checks (see notes) |
| Tests | 385 unit and feature tests green; Pint and Larastan level 6 pass |
| Coverage | 90.0% of lines (CI, PCOV); CI now fails below 88% |
| Invoice maths | Mutation score 96.4% (target 90%) |
| Security review (whole app) | No high findings; 4 medium fixed; low items fixed or listed below |

SEO scores locally and on staging are low only because those environments send `noindex` on purpose. Production allows indexing (covered by tests).

## Accessibility fixes
1. **Admin muted text contrast.** Slate 500 was 3.7:1 on the dark panels; dark-mode grey 500 is now the canvas `#8291A8` (5:1+).
2. **Empty table headers.** The row-actions column and the project cover column now have screen-reader-only headers.
3. **Empty link.** The project cover cell is no longer a link (the title links to the edit page).
4. **Unlabelled inputs.** Invoice line, project metric and social link inputs in table repeaters have `aria-label`s.

New tests: `tests/Browser/QaAccessibilityTest.php` runs all of the above on every change.

## Security fixes (whole-app review)
| # | Finding | Fix |
|---|---|---|
| M1 | Session and XSRF cookies were not `Secure` unless an env var was set | Secure by default on every environment except local and testing |
| M2 | `?page=` junk could fill the page cache (disk and inodes on the shared account) | Only page numbers 1–200 are cached; blog pages past the last one return 404 |
| M3 | A signed-out visit to `/oauth/authorize` redirected to the private admin login | Neutral "sign in to the admin first" page (401), with no redirect or link |
| M4 | Cached pages built their links from the request's `Host` | Links and assets always use `APP_URL` over https outside local and testing |
| L | Directory listing was disabled only when `mod_negotiation` was loaded | `Options -Indexes` now applies everywhere |
| L | A cancelled invoice's emailed link kept working for 30 days | It now answers 410 Gone |

Trusted-host filtering was tried and left out: if the Hostinger CDN passes an unexpected `Host`, the whole site would answer 400. Fixing links to `APP_URL` removes the risk it was meant to cover.

## Coverage gaps closed
The coverage report showed thin spots in security-sensitive code; each now has tests (`tests/Feature/Phase4CoverageTest.php`):
- `mcp:prune-clients` deletes only old apps that were never approved and never had a token (it had no tests).
- The MCP `get-invoice` and `list-clients` tools: lookups, not-found errors, literal wildcards in search, and no payment details in the output.
- Admin sign-in: blocked after the hourly limit even with the right password.
- `app:admin-reset`: new password, two-factor reset, remember-me sign-out; weak passwords and unknown emails refused.

Remaining files under 80% are thin Filament wiring (resource route bindings, form schemas) covered by the browser tests.

## Open items (low, for the owner to decide or check on staging)
- **Client IP behind the Hostinger CDN.** Login and MCP rate limits use the client IP. If PHP sees the CDN's address, they become global. Check `REMOTE_ADDR` on staging; configure trusted proxies if needed.
- **Private files and `/storage`.** The private disk's signed URLs share the `/storage` prefix with public files. Give it its own prefix or turn serving off if Filament doesn't need it.
- **`/up` health check** uses the session middleware and isn't throttled.
- **No CSP on the admin.** Output escaping is correct; a CSP would be defence in depth.
- **Invoicing logo upload is unused.** The PDF always uses the brand logo; remove the field or wire it up.
- **Firefox and axe.** The test plugin's axe injection sees a blank document in Firefox. Accessibility is covered in Chromium and WebKit, and Firefox passes every functional test.

## Still to do on staging (needs the owner)
1. Lighthouse and PageSpeed Insights on the real Hostinger origin and CDN.
2. Headers check with `curl -I` (CSP, HSTS, cache) and the `REMOTE_ADDR` check above.
3. Invoice email end to end, which needs the SMTP password in the staging `.env` and cron running `schedule:run`.
4. A manual screen-reader pass (VoiceOver) on the home page, a case study and the invoice editor.
