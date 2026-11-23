# ADR-0007: Admin authentication and 2FA

**Status:** Accepted (owner, 2026-11-22)
**Date:** 2026-11-22
**Deciders:** Owner

## Context
- There is a single admin (the owner). The admin holds client PII and financial records.
- The public site has no forms and no user accounts.
- Filament 5 ships built-in multi-factor auth (`AppAuthentication`, TOTP) with recovery codes, and can require it (`isRequired: true`). Checked via context7 on 2026-11-22.

## Decision
- **Panel:** `->login()` and `->profile()` only. **No** `registration()`, **no** `passwordReset()`. The owner recovers access with recovery codes or the artisan command `app:admin-reset` (run over SSH after owner approval).
- **MFA:** `->multiFactorAuthentication([AppAuthentication::make()->recoverable()], isRequired: true)`. The `users` table gets `app_authentication_secret` and `app_authentication_recovery_codes` (both `encrypted` casts).
- **Access gate:** `User implements FilamentUser`. `canAccessPanel()` returns true only when `is_admin` is set. The admin user is created by `php artisan app:create-admin` (an interactive prompt, so the password never appears in shell history or seeders).
- **Brute force:** Filament's login rate limit (5 attempts/min/IP) plus an app `RateLimiter` on the login POST (10/hour/IP, then a lockout notice). Log failed logins to a `security` log channel.
- **Admin path:** `ADMIN_PATH` env, default `admin`. Production may use an unguessable value. This is defence in depth, not security.
- **Sessions:** `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `http_only`, `same_site=lax`, `SESSION_LIFETIME=120`, `SESSION_ENCRYPT=true`, regenerated on login. The **public routes do not start a session or set cookies** (a separate `public` middleware group). This keeps pages response-cacheable and cookieless (ADR-0008).
- **Authorization:** a Policy for every Filament resource model (owner-only today, but explicit), with Enums for statuses.
- **Optional:** hPanel **IP Manager** can block abusive IPs. Staging (`dev.`) gets HTTP basic auth via `.htaccess` + `.htpasswd` in `shared/`, so it stays private.

## Options considered
| Option | Verdict |
|---|---|
| **Filament built-in MFA (TOTP + recovery codes, required)** | **Chosen.** First-party, no extra package. |
| Filament Breezy (2FA + passkeys) | A good plugin, but redundant now that MFA is core. Revisit if passkeys are wanted. |
| Laravel Fortify 2FA | Separate UI from Filament. Rejected. |
| Email OTP only | Weaker than TOTP, and depends on mail. Rejected as the primary method. |

## Consequences
- Easier: strong login with no extra packages.
- Harder: losing both the TOTP device and the recovery codes requires SSH access to reset. Store the recovery codes in a password manager.
