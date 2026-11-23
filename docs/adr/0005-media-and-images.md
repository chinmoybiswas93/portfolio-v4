# ADR-0005: Media and images

**Status:** Accepted (owner, 2026-11-22)
**Date:** 2026-11-22
**Deciders:** Owner

## Context
- Media covers project covers and galleries, post images, profile photo, CV PDF, OG images and the invoice logo.
- The Lighthouse 95+ and LCP < 2.0s budget needs responsive, modern-format images with fixed dimensions (no CLS).
- The server has GD and Imagick. PHP `symlink()` is disabled. Queues are drained by cron only (ADR-0006).
- The old site's 6 project images (`.webp`) and the CV are saved locally in a git-ignored local folder (git-ignored).

## Decision
- Use **`spatie/laravel-medialibrary` (v11)** + **`filament/spatie-laravel-media-library-plugin`** (check Filament 5 compatibility at install).
- Disks: **`public`** (`shared/storage/app/public`, served via `public/storage`, linked by the deploy script with `ln -s`) for public media. **`private`** for the CV source, invoice logo and invoices.
- Conversions use the **GD** driver by default (`IMAGE_DRIVER=imagick` if AVIF is wanted later). Output is **WebP**, with the original kept.
  - `thumb` (admin) is generated **nonQueued**, so the admin preview is instant.
  - `card` (≈ 800w) and `hero` (≈ 1600w) are generated **withResponsiveImages()** and **queued**. They are ready within about 1 cron tick, and the original is used until then.
- Store `width`, `height` and a required `alt` (custom property) on every image, and render `<img srcset sizes width height loading decoding>` through one Blade component.
- **Site-wide single files** (CV, avatar, invoice logo, default OG image) are **not** media rows. They are settings file paths uploaded via Filament `FileUpload` (see ARCHITECTURE §3). The CV is served at `/cv` (stable URL) with a friendly download filename.
- **Caching:** media URLs contain the media ID and a conversion name, and replacing an image creates a new ID. That allows `Cache-Control: public, max-age=31536000, immutable` on `/storage/*` (set in `public/.htaccess`).
- Upload limits: images ≤ 8 MB with allowed MIME types (jpeg, png, webp, avif), PDF ≤ 10 MB.

## Options considered
| Option | Verdict |
|---|---|
| **Spatie Media Library + Filament plugin** | **Chosen.** Collections, conversions, responsive srcset, ordering and custom properties, with first-class Filament support. |
| Plain Filament `FileUpload` + Intervention | Fewer tables, but responsive sets, alt text and ordering would have to be hand-built. |
| On-the-fly resizing (Glide) | CPU per cache-miss on a shared, already loaded server. Rejected. |
| External image CDN (Cloudinary, ImageKit) | Another account and cost. Pre-generated files with long browser caching (plus Hostinger CDN) are enough. |

## Consequences
- Easier: consistent, optimised images and easy galleries in case studies.
- Harder: `public/storage` must be linked per release by shell. `media:regenerate` must run as an artisan command if conversions change. Run it via an owner-approved SSH command or a scheduled one-off.
- The old images were downloaded on 2026-11-22 to the git-ignored local folder a git-ignored local folder. The importer reads them from there.
