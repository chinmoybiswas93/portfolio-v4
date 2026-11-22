# Threat model: MCP server and OAuth (slice 3.9)

Companion to [ADR-0009](../adr/0009-mcp-server.md). Method: assets, actors and entry points, then STRIDE per entry point, with each threat mapped to its mitigation and the test that proves it. **Status:** accepted with ADR-0009, 2026-11-23.

## Assets
| Asset | Why it matters |
|---|---|
| Site content (posts, projects, services, experience, tags, categories) | Public reputation; defacement or spam |
| Client and invoice data (names, emails, addresses, tax IDs, amounts) | Personal and financial data (UK GDPR) |
| Payment details, invoicing and security settings | Fraud (changed bank details), account takeover; **never exposed to MCP** |
| OAuth tokens and Passport private key | Whoever holds them acts as the owner within the granted scopes |
| Admin session and 2FA | Gate for consent; full admin power |
| Audit log | Evidence of what assistants did |

## Actors
- **Owner:** the only admin, approving apps in the browser.
- **Legitimate assistants:** Claude Code, Claude.ai, Codex, ChatGPT, acting on the owner's instructions.
- **External attacker:** unauthenticated, on the internet.
- **Malicious or compromised client:** a registered app, or a stolen token.
- **Prompt-injected assistant:** a legitimate app steered by content it reads (a post, an email, a web page).

## Entry points
1. `POST /mcp`
2. `/.well-known/oauth-*` (metadata)
3. `POST /oauth/register` (dynamic registration)
4. `GET|POST|DELETE /oauth/authorize` (consent, behind admin login + 2FA)
5. `POST /oauth/token` (code exchange, refresh)
6. Admin MCP screens (audit log, connected apps, settings tab)

## STRIDE

### Spoofing
| Threat | Mitigation | Test |
|---|---|---|
| Calling `/mcp` without authorisation | Passport bearer token required; 401 with resource metadata | Guest → 401 |
| A fake app that looks like "Claude" tricks the owner on the consent screen | The consent screen shows the app name *as registered*, registration time, a "New app" badge, the exact redirect URI and "Only approve apps you started connecting yourself just now". An app can do nothing until approved. | Consent view shows the redirect URI and badge |
| Stealing a code by changing the redirect URI | Exact redirect-URI match to the registered value; **PKCE S256 required**; `https://` or loopback only | Wrong URI / no PKCE → error |
| Consent without the owner | Consent routes need the admin guard + `is_admin` + **2FA** (the middleware is added explicitly; Filament only protects its own pages) | Guest → login; non-admin → 403; admin without 2FA → 2FA set-up |
| Brute force on the token endpoint | Throttled; codes are one-time and short-lived (10 min) | — |

### Tampering
| Threat | Mitigation | Test |
|---|---|---|
| Destructive edits by a prompt-injected assistant | Scopes are chosen per app at consent (they can be unticked). Deletes go to the trash only. Every change is audited with a before/after summary and can be restored. | Trash/restore; no permanent-delete tool |
| HTML or script injected into posts | Markdown only; `*_html` is never accepted; CommonMark with `html_input: escape` and `allow_unsafe_links: false`; strict CSP on public pages | `<script>` in Markdown is escaped; `javascript:` links dropped |
| Changing bank details to divert payments | Payment and invoicing settings are not exposed to MCP at all | No tool exists; `settings:write` covers only profile/SEO |
| Altering issued invoices | Draft-only edits through the `availableActions()` matrix; issue, pay, cancel and email stay admin-only | Edit of an issued invoice → refused |
| Malicious image (polyglot, EXIF, oversized) | File data only; `finfo` sniff (jpeg/png/webp); 8 MB and 6000 px limits; decoded and re-encoded with GD | Polyglot/oversize rejected; EXIF gone |
| Editing the audit log | Append-only; no MCP access; no admin edit/delete | — |

### Repudiation
| Threat | Mitigation |
|---|---|
| "The assistant did that, not me" / an unknown change | Every `tools/call` (ok, denied, throttled, error) logged with client, token id, tool, scope, target, before/after summary, time, IP, user agent; viewable in admin |

### Information disclosure
| Threat | Mitigation | Test |
|---|---|---|
| Client and invoice data read by an app that only needed content | `invoices:read` is a separate scope, not ticked by default on the consent screen | Content-only token → 403 on invoice tools |
| Tokens or secrets in logs | The audit stores the token **id**, never the token. Authorization headers are never logged. Summaries skip secret and encrypted fields. | — |
| Draft or trashed content reaching the public site through MCP | Publishing is explicit (`status`); trashed items are excluded by the default scope everywhere public | Trashed item → 404, absent from sitemap and feed |
| SSRF through image URLs | No URL fetching anywhere in MCP | — |
| Private admin path exposed by metadata | OAuth metadata only lists `/oauth/*` endpoints; the login redirect happens server-side | — |

### Denial of service
| Threat | Mitigation |
|---|---|
| Flood of tool calls | 60/min and 1,000/day per token → 429 (audited) |
| Registration spam | 10/hour/IP; clients never approved are deleted after 7 days; registration can be closed in Settings |
| Huge payloads | Request body limit; image 8 MB; field length limits from the validation rules |
| A misbehaving assistant | Kill switch (503) in Settings and env; revoke the app |

### Elevation of privilege
| Threat | Mitigation | Test |
|---|---|---|
| Using a tool beyond the granted scope | Checked on the server **per call** (middleware + Policy), not just hidden from the tool list | Missing scope → 403 `insufficient_scope` |
| Reaching code, files, the database or the shell | No such tools exist; tools call application Actions only | Tool inventory test |
| A refresh token living on after revoke | Revoke revokes access + refresh tokens; refresh tokens rotate | Revoked app → 401 on next call |
| MCP becoming a back door into admin | MCP tokens use the `api` guard only; they never create an admin session; admin screens need the `web` guard + 2FA | Bearer token on an admin URL → login redirect |

## Prompt injection: residual risk
An assistant with write scopes can be tricked by text it reads into making unwanted edits. This can't be fully prevented on the server. It's contained by:
- least-privilege scopes per app, chosen at consent;
- no access to money or security settings;
- trash instead of delete;
- drafts only for invoices;
- a full audit trail;
- the kill switch.

Recommendation: approve write scopes only for apps you actively use, and review the audit log after large edits.

## Owner decisions (2026-11-23)
1. **Rate limit:** 60 calls/min and 1,000/day per app.
2. **Audit retention:** 90 days.
3. **Re-authorisation:** the same app with the same scopes is auto-approved; a new app or new scopes show the consent screen; revoking forces a fresh approval.
4. **Registration:** open, with each app approved by the owner. A "Closed" switch is available in Settings.
5. **Default ticks:** all scopes are ticked by default on the consent screen. Unticking narrows the grant.
   - **Residual risk this adds:** least privilege now depends on the owner unticking at approval time. An app approved with every tick can trash content, edit SEO/settings and read client data. This is mitigated by the "New app" badge, the visible scope list, the audit log, revoke and the kill switch. **Recommendation:** untick invoices and settings for apps that only write posts.
