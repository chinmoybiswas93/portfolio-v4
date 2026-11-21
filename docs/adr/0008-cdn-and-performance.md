# ADR-0008: Caching and performance (Hostinger-native, no DNS change)

**Status:** Proposed. Revised 2026-11-22: the Cloudflare proxy is dropped at the owner's request.
**Date:** 2026-11-22
**Deciders:** Owner

## Context
- The owner's constraints: the domain stays registered at **Namecheap**, DNS stays on **Hostinger nameservers, managed in hPanel**, and nothing sits in front of Hostinger. The server location is accepted as is.
- Hostinger provides per website (hPanel and the API: `hosting_websites_get-settings` / `update-settings` / `hosting_cache_clear`):
  - **Server cache:** the LiteSpeed page cache, on/off plus a "cacheless" dev mode.
  - **Hostinger CDN:** on/off, currently on for staging (`server: hcdn`).
- Budget (unchanged): Lighthouse 95+ on mobile, LCP < 2.0s, CLS ≈ 0.
- The public site is read-mostly and changes only when the owner publishes.

## Decision
Use three Hostinger-native and in-app layers. They need no DNS change and no third-party proxy.

1. **App full-page cache: `spatie/laravel-responsecache`** on the `public` route group only.
   - It caches successful GET HTML for 1 day in a dedicated `file` cache store (`storage/framework/cache/responses`), so it is independent of the DB.
   - **Clear on publish:** a model observer on Service, Experience, Project, Post, Category, Tag and settings calls `ResponseCache::clear()` (the content set is tiny, so clearing everything is simplest and safe). Each deploy also runs `responsecache:clear`.
   - A cache hit returns HTML in a few milliseconds of PHP, with no DB queries.
2. **Static assets: long-lived browser cache, plus Hostinger CDN where it helps.**
   - `/build/*` (Vite-hashed) and `/storage/*` (media-ID paths, ADR-0005) get `Cache-Control: public, max-age=31536000, immutable` via `public/.htaccess`.
   - **Hostinger CDN:** kept **on** if the deploy proof shows it respects origin headers and **does not cache admin, Livewire or `/up` responses**. Otherwise it is turned off for our sites in hPanel (owner action). The deploy runs `hosting_cache_clear` only if the CDN is caching HTML. That would need an API token in CI, so it is avoided if possible.
3. **LiteSpeed server cache:** the app sends `X-LiteSpeed-Cache-Control: no-cache` on admin, Livewire, `/up` and `/_deploy/*` responses, so the server cache can never store private pages. Public pages rely on layer 1, so there is one cache to reason about and purge. The deploy proof checks the actual behaviour, and this ADR records the result.

**Front-end budget measures** (these matter more than the CDN for Lighthouse):
- One CSS file (Tailwind v4, purged) and one small JS bundle (Alpine only where needed).
- Self-hosted subsetted WOFF2 fonts (≤ 2), `font-display: swap`, and the LCP font preloaded.
- Responsive WebP with `width`/`height`, `fetchpriority="high"` on the LCP image, and lazy-loading below the fold.
- No third-party scripts except the deferred **Cloudflare Web Analytics beacon** (a JS snippet only, with no DNS or proxy involvement).
- Origin: OPcache, `artisan optimize`, `filament:optimize`, eager loading, and Markdown pre-rendered on save.

## Options considered
| Option | Effort | DNS change | Verdict |
|---|---|---|---|
| **Response cache + Hostinger CDN/server cache (chosen)** | Low | None | Meets the owner's constraints. Pure PHP, testable, and purge is under app control. |
| Cloudflare proxy (previous proposal) | Medium | NS move | **Rejected by the owner:** DNS must stay in hPanel. |
| LiteSpeed page cache only (`X-LiteSpeed-Cache-Control: public`) | Low | None | Hostinger-specific behaviour, purging needs response headers or the API, and it is hard to test in CI. It is the fallback if the response cache is not enough. |
| Move the hosting to an EU data centre | — | — | Out of scope. The owner accepts the current location. |

## Consequences
- Easier: no DNS or third-party changes. Caching is visible and testable in Pest (a header is present, a second hit skips the DB, and publishing clears the cache).
- Harder: an uncached HTML request still pays the round trip to the server. TTFB from the UK/EU will be higher than with an edge cache, so Lighthouse scores rely on small pages and the font and image budget. This is accepted.
- `HSTS` can be enabled once HTTPS is confirmed on both sites (Hostinger SSL).
- Revisit if PageSpeed Insights on staging (from an EU location) misses LCP < 2.0s: first try the LiteSpeed page-cache fallback, then reconsider the hosting location.

## Action items
1. [ ] Deploy proof: record `hosting_websites_get-settings` for staging (read-only), check the CDN and server-cache behaviour with `curl -I` on a public page, `/up`, and an admin page.
2. [ ] Phase 3: add `spatie/laravel-responsecache` (check the Laravel 13 version), the observer and the `.htaccess` cache headers.
