# ADR-0002: Hostinger app layout and release folders

**Status:** Accepted (owner, 2026-11-22). **Option A proven on staging on 2026-11-22.**
**Date:** 2026-11-22
**Deciders:** Owner

## Context
Server facts, checked read-only on 2026-11-22:
- Each website has `~/domains/<domain>/` containing `public_html/` (the docroot, a real directory) and an empty marker file `DO_NOT_UPLOAD_HERE`. On `dev.chinmoybiswas.com`, `public_html/` holds only Hostinger's `default.php`.
- The web server is **LiteSpeed**. Staging currently responds via `server: hcdn` (the Hostinger CDN is on).
- **Shell `ln -s` works** (tested in a temp dir under `/tmp`, then removed). PHP `symlink()`/`link()` are disabled, so `php artisan storage:link` **will fail** and links must be created from the shell.
- `rsync`, `tar`, `git`, `mysqldump` and `composer` exist on the server. CLI `memory_limit=1536M`.
- The account is shared with other websites. CLAUDE.md limits our work to `~/domains/chinmoybiswas.com/` and `~/domains/dev.chinmoybiswas.com/`.

**About `DO_NOT_UPLOAD_HERE`:** Hostinger has no public documentation for this file (searched 2026-11-22). Its docs only say that website files belong in `public_html` and that everything there is public. The marker is a **user-facing warning** that files uploaded to the domain root will not be served on the web. It is not a technical restriction: the folder is ours and writable. Not being web-served is exactly what we want for app code, `.env` and storage. Placing the app beside `public_html` keeps everything inside the per-site folder we are allowed to touch.
Residual risk: a Hostinger tool (for example "reset website" or a backup restore of `public_html` only) might ignore or remove `app/`. Mitigations: never use hPanel "reset/delete website" on these sites, and keep our own `tar` + `mysqldump` backups. Optionally ask Hostinger support to confirm (owner).

## Decision
Use the same layout per environment (`<d>` = `dev.chinmoybiswas.com` or `chinmoybiswas.com`):

```
~/domains/<d>/
├── DO_NOT_UPLOAD_HERE          (Hostinger marker, left untouched)
├── app/
│   ├── releases/
│   │   ├── 20261122-1530-ab12cd3/   ← one CI build (code + vendor + public/build)
│   │   │   ├── .env           -> ../../shared/.env
│   │   │   ├── storage        -> ../../shared/storage
│   │   │   └── public/storage -> ../../../shared/storage/app/public
│   │   └── …                  (keep last 3, to save inodes)
│   ├── shared/
│   │   ├── .env               (chmod 600, created by hand, never in git)
│   │   └── storage/           (app/public, app/private, framework/*, logs, fonts)
│   └── current -> releases/<active>
└── public_html -> app/current/public      ← Option A
```

- **Switch** a release with an atomic rename: `ln -sfn releases/<id> current.new && mv -Tf current.new current`.
- **Rollback** means pointing `current` at the previous release. It takes seconds and needs no rebuild. Migrations must be backward-compatible (expand/contract), so the previous release still runs against the new schema.
- **No opcache reset is needed** (verified 2026-11-22): after `current` was switched from r1 to r2, the very first request already served r2. LiteSpeed resolves the symlinked docroot per request (`base_path()` reports the real release path), so opcache entries are keyed per release.
- **Permissions:** deploy commands run with `umask 022`. Only `shared/.env` and the SQLite/DB files are `600`. A `600` file under `storage/app/public` gives a LiteSpeed **403** (observed in the test).

## Options considered

### A: `public_html` is a symlink to `app/current/public` (chosen, pending proof)
| Dimension | Assessment |
|---|---|
| Complexity | Low: one symlink, standard Laravel `public/` |
| Risk | LiteSpeed must follow the symlink (normally `SymLinksIfOwnerMatch`, and we own both). hPanel tools might expect a real dir. |
| Rollback | Instant |

### B: `public_html` stays a real dir with a shim `index.php` + `.htaccess` (fallback)
`index.php` requires `../app/current/vendor/autoload.php` and `../app/current/bootstrap/app.php`, and the app calls `->usePublicPath()` to point at `public_html`. Each deploy also rsyncs `public/` (build assets, `robots.txt`, `favicon`) into `public_html/`, plus a `storage` symlink.
**Pros:** needs no docroot symlink. **Cons:** assets are copied per deploy, so asset rollback needs a re-copy (old hashed files are kept, so this is safe), and a custom public path complicates Vite/Filament asset publishing.

### C: change the docroot in hPanel to a subfolder
**Unverified:** no Hostinger API operation for it was found, and hPanel support for a custom docroot on Premium is unconfirmed. Not relied on. The owner can check hPanel → Websites → Advanced during the deploy proof.

### D: whole Laravel app inside `public_html` + root `.htaccess` rewrite into `public/`
**Cons:** `.env`, `storage/` and `vendor/` sit under the docroot, so one misconfigured rewrite exposes secrets. Rejected.

## Consequences
- Easier: instant rollback, secrets and storage outside the docroot, identical layout for staging and production.
- Harder: `storage:link` must be replaced by a shell `ln -s` in the deploy script. Production cutover (Phase 5) has to move the live WordPress `public_html` aside (after a backup) before the symlink is created.
- Inode use: about 3 releases × roughly 15k files. Check the account's inode quota in hPanel before Phase 3.

## Deploy-test results (staging, 2026-11-22)
- `public_html -> app/current/public` (shell symlink) is served by LiteSpeed (`200`, `sapi: litespeed`, PHP 8.3.37, opcache on). HTTPS works (Hostinger lifetime SSL).
- The nested `public/storage -> shared/storage/app/public` symlink serves files (`200`) once the file is `644`.
- The atomic switch (`ln -sfn` + `mv -Tf`) takes effect on the next request, with no reset needed.
- `rsync --link-dest` to the previous release: an unchanged release costs about 4 MB instead of 32 MB, because unchanged files are hard-linked.
- The Hostinger original `public_html` is kept as `public_html.hostinger-default` (plus a tar backup in `~/backups/`).

## Action items
1. [x] Deploy proof (staging): layout, symlinked docroot, HTTPS and `/storage/*`, all verified.
3. [ ] Phase 5: back up WordPress (`tar` + `mysqldump` into `~/backups/`), then cut over.
