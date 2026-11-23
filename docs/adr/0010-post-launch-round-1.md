# ADR-0010: Post-launch improvements, round 1

**Status:** Accepted (owner, 2026-11-24). Each slice is approved separately.
**Date:** 2026-11-24
**Deciders:** Owner

## Context
After launch, the owner listed admin and public issues plus two new features (SEO/GEO/AEO audit, analytics and Search Console). This ADR records the findings and the answers to the open questions; slice details live in each PR.

## Findings
- **Dark-mode contrast:** an axe run (`color-contrast` and `color-contrast-enhanced`) on the public pages and eight admin screens in dark mode found **no AA failures**, but every secondary text style sits at 5.3–7.0:1, below AAA (7:1): dates, table headers, sidebar group labels, breadcrumbs, helper text, tabs, filter labels, upload hints, footer, code captions and the editor's syntax colours. Small text at that level reads as faint.
- **Profile page:** Filament's page does require the current password and a confirmation, but both fields stay hidden until a new password is typed, and an email change only asks for the current password on submit. There is no 2FA step and no verification of the new address.
- **Client modal:** the form is already shared (`ClientForm`). Only the modal width differs: `Large` on the Clients page, the default on the invoice's "create client".
- **Markdown editor:** Filament 5's `MarkdownEditor` has no preview mode and a fixed height.
- **Analytics / Search Console:** neither is wired. The CSP already allows the Cloudflare beacon, but no page loads it.

## Decisions
1. **Dark palette lifted to AAA:** secondary text `#94A3B8` → `#B4C0D1` (≈ 9.7:1), labels and dates `#8291A8` → `#A3B1C6` (≈ 8.2:1). Filament greys, the sidebar's active item, the editor's syntax colours and the MCP sign-in pages are raised to match, and everything is ≥ 7:1 on page, panel and card surfaces. The public light theme is unchanged. In light mode the admin's labels and table headers had used the dark grey (3.3:1 on white); they now use the public light-theme greys `#5B6B80` / `#475569`. `DarkContrastTest` (axe `color-contrast-enhanced`) guards it.
2. **Edit page header:** the actions stay on the right. A long title is truncated to one line with an ellipsis, and the full title shows on hover.
3. **One client modal:** a shared client modal (form + width) is used by the Clients list (create, edit) and the invoice client field (create).
4. **Dashboard:** "Paid" and "To be paid" (Sent + Overdue, with the overdue part shown) per currency, replacing "Outstanding". "Paid" has a period filter (This month / This year / Last year / All time; default This year). "To be paid" is always everything open. All four currencies always show a card (owner, design approved 2026-11-24). Periods follow UK time and count by payment date. "Mark as paid" asks for the payment date (default today, not in the future, not before the issue date); an earlier day is stored at midday UK time.
5. **Account security:** separate Email and Password sections (layout approved 2026-11-24). Both changes need the current password **and** an authenticator code; new passwords need 12+ characters and must not be in known breach lists. Details in the ADR-0007 amendment.
   - Password: current password, new password and confirmation are always visible. Other sessions are signed out after a change.
   - Email: current password and a 2FA code are required. The change takes effect only after the link sent to the new address is clicked, and the old address gets a notice with a block link (Filament's email change verification).
6. **Markdown fields:** the height can be resized by dragging (or with the arrow keys) and is remembered per field in the browser; defaults are 480 px for post bodies and 240 px elsewhere. Each field gets Write / Preview tabs, with the preview rendered on the server by the site's own Markdown renderer and stylesheet in a sandboxed frame (design approved 2026-11-24).
7. **Analytics:** Cloudflare Web Analytics beacon (cookieless, no banner), with the token set in Settings → SEO and loaded only in production.
8. **Search Console:** verified as a domain property through a DNS TXT record on the hosting DNS (added only with the owner's approval), then the sitemap is submitted in Search Console.
9. **SEO / GEO / AEO:** audit first (Lighthouse SEO, structured-data validation, crawl of the live site, sitemap/robots/canonicals, internal links, AI-crawler access and answer-ready content), then a report with recommendations and a keyword map for posts and case studies. Changes are built only after the owner approves the report. Keyword targets use Search Console data once it has collected some.

## Order
Contrast → header and client modal → dashboard → account security → Markdown editor → analytics and Search Console → SEO audit report → SEO changes. New or changed screens are drawn in the design canvas and approved before UI code (CLAUDE.md).
