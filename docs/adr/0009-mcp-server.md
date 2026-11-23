# ADR-0009: MCP server with OAuth 2.1 (Passport) on shared hosting

**Status:** Accepted (owner, 2026-11-23)
**Date:** 2026-11-23
**Deciders:** Owner
**Threat model:** [`docs/security/mcp-threat-model.md`](../security/mcp-threat-model.md)

## Context
The owner wants AI assistants (Claude Code, Claude.ai, Codex, ChatGPT) to read and edit the site's content through the Model Context Protocol, with the owner approving each assistant in the browser. Constraints:

- **Hosting** (Hostinger Premium): PHP only, no long-running processes, cron every minute, `exec`/`symlink` disabled, MariaDB at `127.0.0.1`.
- **Admin:** sits at a private path (`ADMIN_PATH`) behind password + required TOTP 2FA (ADR-0007).
- **Decided by the owner (2026-11-22):**
  - official `laravel/mcp`, Streamable HTTP at `/mcp`;
  - OAuth 2.1 via Passport with discovery and dynamic client registration;
  - nine scopes;
  - tools reuse the app's Actions, Policies and validation, and never touch code, files, shell, config or raw SQL;
  - Markdown in and out;
  - soft deletes only;
  - invoices draft-only;
  - profile/SEO settings only;
  - image upload from file data only;
  - kill switch, audit log, per-token rate limit.

### Versions checked (context7 + `composer require --dry-run`, 2026-11-23)
| Package | Version | Fit |
|---|---|---|
| `laravel/mcp` | 1.2.1 | `illuminate/* ^13.0`, PHP ^8.2. **Stateless:** session IDs and the `MCP-Session-Id` header were removed, so each HTTP request is handled independently. Plain JSON responses (SSE only if a tool streams). `Mcp::oauthRoutes()` registers RFC 9728 / RFC 8414 metadata and RFC 7591 dynamic registration, and adds the `mcp:use` scope to Passport. |
| `laravel/passport` | 13.12.0 | Laravel 13 OK. Public clients + PKCE (nullable secret). The User model must implement `Laravel\Passport\Contracts\OAuthenticatable`. Pulls `league/oauth2-server` 9.5, `lcobucci/jwt` 5.6, `phpseclib` 4. No `exec` at runtime. |

## Decision

### 1. Transport and endpoint
- **Endpoint:** `POST /mcp` via `Mcp::web('/mcp', PortfolioServer::class)`.
- **Middleware**, in order:
  1. `EnsureMcpEnabled` (off = **503**, `Retry-After`);
  2. `auth:api` (Passport token guard; no token = **401** with `WWW-Authenticate: Bearer resource_metadata=…`, which `laravel/mcp` adds);
  3. `throttle:mcp` (per token);
  4. `EnsureMcpToolScope` (see 3);
  5. `RecordMcpCall` (audit, see 6).
- **Stateless:** no session, cookies or CSRF on `/mcp`. Responses are plain JSON; tools don't stream, so nothing holds a connection open. This fits a PHP-only host with no workers.
- **Page cache:** none on `/mcp`. It runs outside the `public` middleware group, so the page cache never sees it. `X-LiteSpeed-Cache-Control: no-cache` and `X-Robots-Tag: noindex` are set.

### 2. OAuth 2.1 with Passport
- **Grant:** authorization code + **PKCE S256 required**, public clients (no secret). Implicit, password and client-credentials grants are off.
- **Discovery:** `Mcp::oauthRoutes()` →
  - `/.well-known/oauth-protected-resource[/mcp]`
  - `/.well-known/oauth-authorization-server`
  - `POST /oauth/register` (dynamic client registration)
- **Registration** is open (the MCP clients need it), but a registered client can do nothing until the owner approves it on the consent screen.
  - Rate limit: 10 per hour per IP.
  - Redirect URIs must be `https://` or loopback `http://127.0.0.1|localhost` (for Claude Code / Codex CLIs).
  - Clients never approved are deleted after 7 days (scheduler).
  - The Settings → MCP tab has a "Closed" option that turns registration off.
- **Consent screen** (canvas "Admin-MCP-Consent"). Passport's own routes are disabled (`Passport::ignoreRoutes()`) and re-registered under `/oauth` with our middleware:
  - `GET /oauth/authorize`, `POST /oauth/authorize` (approve) and `DELETE /oauth/authorize` (deny) run behind `web` + **the admin guard + is_admin + `EnsureMultiFactorAuthenticationIsEnabled`**. Logged-out visitors go to the private admin login (`login` route → `/{ADMIN_PATH}/login`) and come back to the consent screen after password + 2FA.
  - The screen shows the app name, registration time, redirect URI, a "New app" badge and the requested scopes as checkboxes, **all ticked by default** (owner, 2026-11-23).
  - **Unticking narrows the grant:** our approve controller reduces the stored authorization request's scopes to the ticked subset before Passport issues the code.
  - Frame protection: `X-Frame-Options: DENY`.
- **Tokens:**
  - Access tokens last **1 hour**. Refresh tokens last **30 days** and rotate on use (league default).
  - Re-authorising the same app with the same scopes is auto-approved (Passport default). Any new scope or new app shows the consent screen.
  - `passport:purge` runs daily.
- **Keys:** generated once with `php artisan passport:keys` into the shared `storage/` (persists across releases, mode 600, included in backups) — an owner-approved server step. They're never committed or sent to CI.
- **Connected apps** (admin, canvas "Admin-Connected-Apps"): lists each client with its scopes, approval time, last use and status. **Revoke** revokes all its access and refresh tokens at once; the app must ask again.

### 3. Scopes and enforcement
| Scope | Allows |
|---|---|
| `content:read` | List, search and read posts, projects, services, experience, categories, tags (Markdown fields) |
| `content:write` | Create and edit those (Markdown); set status, including publish |
| `content:trash` | Move to trash / restore (never permanent delete) |
| `seo:write` | Edit per-item SEO title/description and SEO settings |
| `media:upload` | Upload an image and attach it as cover, gallery or inline |
| `invoices:read` | Read invoices and clients |
| `invoices:draft` | Create and edit **draft** invoices (lines, discount, VAT rate, notes) |
| `settings:read` | Read profile and SEO settings |
| `settings:write` | Edit profile and SEO settings only |

Plus `mcp:use`, which `laravel/mcp` registers. Every token must carry it.

**Enforcement happens on the server on every call, never just by hiding tools:**
- `tools/list` only lists tools whose scope the token has (`shouldRegister`).
- `EnsureMcpToolScope` reads the JSON-RPC body. A `tools/call` for a tool whose scope the token lacks gets **HTTP 403** with `WWW-Authenticate: Bearer error="insufficient_scope", scope="…"` (RFC 6750, MCP step-up authorization) and is audited as denied.
- Each tool also authorises through the existing **Policies**, with the token's user (the owner).

**Never exposed through MCP:**
- payment/bank details, invoicing settings and security/2FA settings;
- issuing, marking paid/cancelled or emailing invoices;
- permanent deletes;
- users, redirects, files, shell, config and raw SQL.

### 4. Tools reuse the app's rules
- **Content:** writes go through new `App\Actions\Content\*` actions (`SavePost`, `SaveProject`, …). Their validation rules are shared with the Filament forms (moved into one place per resource). Existing model behaviour still applies: slug generation and normalisation (`HasSlug`), safe Markdown rendering on save (`RendersMarkdown`: CommonMark, `html_input: escape`, `allow_unsafe_links: false`), and page-cache flush (observer).
- **Invoices:** existing `RecalculateInvoice`, `InvoiceCalculator` and the `InvoiceStatus::availableActions()` matrix (only `Edit` on drafts). Clients: read only.
- **Settings:** the existing settings classes; `ProfileSettings` and `SeoSettings` only.
- **Trash:** `SoftDeletes` is added to Service, Tag and Category (Post, Project and Client have it). The admin gets a trash filter with restore and permanent delete. Public queries never show trashed items. MCP can only trash and restore.
- **Markdown in and out:** tools accept and return `*_md` fields. `*_html` is never accepted from MCP.

### 5. Images (`media:upload`)
- Input is base64 file data only. There is no URL fetching, so no SSRF.
- At most 8 MB decoded.
- The MIME type is sniffed with `finfo` and must be jpeg, png or webp, whatever the declared type.
- At most 6000 × 6000 px.
- The file is decoded and **re-encoded with GD** (strips EXIF, GPS and trailing payloads), then stored through the media library (same conversions as the admin: WebP responsive sizes).
- The tool returns the media id, public URL and a Markdown snippet (`![alt](url)`). Alt text is required.

### 6. Safety controls
- **Kill switch:** `McpSettings::enabled` (Settings → MCP tab, canvas "Admin-Settings-MCP") plus env `MCP_ENABLED` as a hard off. When off, `/mcp` returns 503 and the OAuth endpoints refuse new authorisations.
- **Rate limit:** **60 tool calls per minute per token** and 1,000 per day. Throttled calls get 429 and are audited.
- **Audit log** (`mcp_audit_logs`, admin page "MCP → Audit log", canvas "Admin-MCP-Audit"):
  - **Logged for every `tools/call`, including denied and throttled calls:** time, client, token id (never the token), tool, scope, target type/id, a before/after summary (changed field names plus short previews; never secrets), result (ok / denied / throttled / error), IP and user agent.
  - **Append-only:** no update or delete through the app.
  - **Retention:** 90 days (owner, 2026-11-23), pruned daily.
- **Headers:** `/mcp` and `/oauth/*` send `Cache-Control: no-store` and `X-Robots-Tag: noindex`.

### 7. Tests (required)
- A guest calling `/mcp` gets **401** with the resource-metadata header.
- A token without the tool's scope gets **403** `insufficient_scope`, and the call is audited.
- With the kill switch off, calls get **503**.
- A trashed item is not public: 404 on its page, absent from lists, sitemap and feed.
- Draft-only invoices: editing an issued invoice through MCP is refused; issue/pay/email tools don't exist.
- Consent requires the admin login + 2FA; a non-admin is refused; unticking a scope narrows the token.
- Image upload: wrong type, oversize and polyglot files are rejected; EXIF is stripped.
- Route inventory: `/mcp`, `/oauth/*` and `/.well-known/*` are added to the allowed list **on purpose**. The route walk covers the consent and admin MCP screens.

### 8. Delivery (slices, security review on each)
- **3.9a:** Passport + `laravel/mcp`, discovery, registration, consent (admin + 2FA), kill switch, rate limit, scope middleware, audit recording, read tools (`content:read`, `invoices:read`, `settings:read`). Then: exact steps to connect Claude Code to `https://dev.<domain>/mcp` and verify the browser approval end to end.
- **3.9b:** content/SEO write, trash (soft deletes + admin trash UI), image upload.
- **3.9c:** draft invoice tools, audit log UI, Settings → MCP tab, Connected apps (revoke).

## Options considered
| Option | Verdict |
|---|---|
| **`laravel/mcp` + Passport (chosen)** | Official, Laravel 13 ready, stateless HTTP fits shared hosting, discovery + registration built in. Larger dependency set (oauth2-server, JWT, phpseclib) and RSA keys to manage. |
| Personal access tokens only (no OAuth) | Simpler, but Claude.ai/ChatGPT connectors expect OAuth discovery, and there would be no per-app consent or scopes in the browser. Rejected by the owner's requirements. |
| `laravel/mcp` + Sanctum | No OAuth authorization server, so no discovery or dynamic registration. Rejected. |
| Third-party MCP gateway | Sends content and tokens through another service, against the privacy rules. Rejected. |

## Consequences
- **Easier:** assistants can draft and edit content in Markdown with the same validation, slugs and safe rendering as the admin. Every call is attributable and reviewable, and one switch turns it all off.
- **Harder:**
  - RSA key management and backups;
  - a public registration endpoint to rate-limit and clean up;
  - more routes on the inventory;
  - Passport upgrades to track.
- **To revisit:** the rate-limit numbers once real use is known.

## Action items (after approval)
1. Owner answers recorded (2026-11-23): rate limit 60/min and 1,000/day per app; audit retention 90 days; same-scope re-authorisation auto-approves; registration open (each app approved by the owner); all scopes ticked by default on the consent screen (unticking still narrows the grant).
2. 3.9a as described; owner-approved server step to generate Passport keys on staging.
3. Security review on every slice; document the connect and revoke steps (see the README).
