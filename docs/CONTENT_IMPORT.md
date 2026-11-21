# Content import plan (built in Phase 3)

Source: `portfolio-export-2026-11-21.json` (git-ignored, **never committed, never echoed in logs**). It is exported by the old `cb-portfolio` plugin (`metadata.version = 1.0`).
Command: `php artisan import:legacy-portfolio {path} [--dry-run] [--assets=.private/legacy-assets]`. It runs **locally**, and the resulting DB is then seeded to staging or production with an owner-approved dump/import. It runs in one DB transaction and is **idempotent** (upsert by natural key). Tests use a fake fixture (`tests/Fixtures/legacy-export.json`).

## Source shape (checked 2026-11-22)
- `portfolio` (object): name, title, tagline, about (HTML, 3 paragraphs), email, phone, location, github_url, linkedin_url, twitter_url, website_url, resume_url, profile_image (empty), footer_text (HTML), id
- `experience[4]`: id, company, company_website, position, start_date (`"May 2024"`), end_date (`""` or `"Apr 2024"`), current (0/1), description (plain text), skills (CSV string), order_index
- `projects[6]`: id, title, description (plain, about 160 chars), image_url (`.webp` on the current WP site), live_url, github_url, technologies (CSV), year (`"2022"`), made_at (`"Personal"`), featured (0/1), order_index
- `settings.enabled`: ignored

## Mapping

### `portfolio` → `ProfileSettings` / `SeoSettings` (spatie settings)
| Source | Target | Transform |
|---|---|---|
| name | profile.name | trim |
| title | profile.title | Kept as is. The owner rewrites it for the UK/EU positioning (current: "Wordpress Developer \| Tech Support", where "WordPress" is mis-cased). |
| tagline | profile.tagline, seo.default_description | trim, ≤ 160 chars for SEO |
| about (HTML) | profile.about_md (+ `about_html`) | HTML → Markdown (`league/html-to-markdown`), then re-render through the safe renderer |
| email | profile.email | **Imported, but shown publicly only after owner review** |
| phone | — | **Not imported** (contact is links-only, which keeps PII off the public site) |
| location | profile.location_public | **Not imported verbatim.** The source is a street address. The owner enters a city/country (for example "City, Country · Working with UK & EU clients"). |
| github_url, linkedin_url, twitter_url | profile.socials[] `{network, url}` | validate URL |
| website_url | — | dropped (this is the site itself) |
| resume_url | `cv` file | copied from `{assets}/resume.pdf` (already downloaded) to the CV settings path |
| profile_image | profile.avatar | empty, so the owner uploads it |
| footer_text | — | **dropped**: it credits a third-party design that the new design does not copy |

### `experience[]` → `experiences`
| Source | Target | Transform |
|---|---|---|
| company | company | trim |
| company_website | company_url | validate URL |
| position | position | trim |
| start_date `"May 2024"` | started_on | `Carbon::createFromFormat('!M Y')` → `2024-05-01` |
| end_date / current | ended_on | `current=1` or empty → `null`, otherwise parsed as above |
| description | description_md (+ html) | plain text → Markdown paragraph |
| skills (CSV) | tags (`type=tech`) via `taggables` | split on `,`, trim, slug, firstOrCreate. Normalise case (`Wordpress` → `WordPress`) via a small alias map. |
| order_index | sort_order | int |
| — | is_published | `true` |
| id | — | natural key = (company, started_on) |

### `projects[]` → `projects`
| Source | Target | Transform |
|---|---|---|
| title | title, slug | `Str::slug`, unique |
| description | summary | trim |
| image_url | media `cover` | `addMedia({assets}/projects/{basename(image_url)})->preservingOriginal()`, with alt = title (the owner refines it). Generates WebP conversions. No network access needed. |
| live_url | live_url | validate |
| github_url | repo_url | empty → null |
| technologies (CSV) | tags `type=tech` | as for skills |
| year | year | int |
| made_at | made_at | as is ("Personal") |
| featured | is_featured | bool (5 of 6 are featured) |
| order_index | sort_order | int |
| — | status | `published` |
| — | has_case_study | `false`. The owner writes problem/approach/results later. A case study page appears once `has_case_study` is toggled on. |
| — | client_name, sector, role, metrics | null. Filled by the owner. |
| id | — | natural key = slug |

## Post-import checklist (owner)
1. Rewrite the title and tagline for the UK/EU positioning. Set the public location.
2. Upload the avatar and CV. Check the 6 project covers and their alt text.
3. Write case studies for the strongest 2–3 projects (has_case_study = on).
4. Review the tags merged by the alias map.
