# Paddy Newsprint v1.1.0 — Newspaper-Style Chinese WordPress Block Theme

Paddysun Newsprint 1.1.0

Paddysun Newsprint 1.1.0 is a newspaper-style Chinese WordPress block theme for long-form writing and content publishing. This release adds two site-index page templates (a year-by-year archive and a section-grouped tag index), a three-layer performance stack for them (edge-cache headers, transient result caching, batched SQL), completes i18n for all new strings, and passes an independent full-theme code review plus a penetration test.

## What's new in 1.1.0

- **Archive page template** (`template-blog`, suggested for /blog): a sections guide (up to 16 categories in a 4-column grid sorted by post count, each card with count and last-updated date), a duo column of popular tags (top 8) beside a pure-CSS monthly word-count bar chart (last 24 months, newest month right-aligned, hover tooltip with month/chars/posts), and a year-by-year full post index (year heading + numbered list with date and first three tags per entry) rendered lazily via native `content-visibility`. The stats line wraps at `/` separators and truncates long site names. Single hairline rules throughout, responsive 4→3→2→1 column steps with count caps 16/12/8/6.
- **Tag index page template** (`template-tags`, suggested for /tags): tags grouped by top-level category (count-descending within groups), tags outside any section fall into "Ungrouped"; group headings use a double rule to contrast with the archive's hairlines. Tags and their counts share one rounded translucent chip style across both pages.
- **Performance, three layers.** (1) A CDN edge-cache gateway in `inc/cache.php`: anonymous GET public views emit `Cache-Control: public, max-age=0, s-maxage=600, stale-while-revalidate=3600` plus a site-wide `Last-Modified` (If-Modified-Since → 304); logged-in, comment-author, password-cookie, any-query-string, feed/search/404/preview requests pass through; existing Cache-Control or Set-Cookie headers from other plugins take precedence; `paddysun_ns_edge_cache` filter disables the layer. (2) The two index patterns cache their whole rendered fragment as a transient (Redis when Redis Object Cache is active) with a fixed key and in-value version compared against a version option bumped by real post/page saves and category/tag changes (revision/autosave guarded). (3) Data access is fully batched: the tag index went from 212 to 8 queries cold and 1 warm; the archive from 53 to 15 cold and 2 warm (measured on 108 posts / 177 tags). The year list selects slim columns only (no post_content), builds permalinks from lightweight `WP_Post`/`WP_Term` objects with a `_prime_post_caches` fallback for `%category%` structures and a real `post_author` column for `%author%` structures (both verified live), and Polylang/WPML environments automatically fall back to WP-API data paths.
- **i18n completed for the new surfaces.** All 23 new translatable strings (including both pattern titles/descriptions) are in the regenerated `.pot` (252 entries) with English translations; en_US sites render the new pages in English out of the box. Index cache keys are locale-aware.
- **Verification.** Theme Check 20260901: 0 REQUIRED, 0 WARNING (same two known INFO items as the 1.0.2 baseline). Independent dual-agent full-theme review (PHP layer and template/asset layer, against the WordPress theme handbook and coding standards) plus a 36-probe penetration test: XSS reflection, SQL injection (error-based and timing), cache poisoning, authorization (subscriber vs. ads/llms settings pages and draft export), CSRF, and information disclosure all closed; the single genuine finding (web-root test scaffolding on the isolated site, not theme code) was remediated.
- **Engineering.** `repair-footnotes.php` moved out of the theme package (kept in this repository under `tools/`; the 1.0.2 packaged copy keeps working). PHP 7.4 compatibility fixes (`str_contains` → `strpos`), closure hooks converted to named functions, `preg_replace_callback` for translated comment labels, `wp_json_encode` failure guard, `filemtime` readability guards, ABSPATH guards on new patterns, `theme.json` customTemplates titles, and five previously empty en_US strings filled.
- **Stance.** WordPress.org directory requirements are adopted as engineering guidance; this is a self-distribution release and not a directory submission.

## Retained capabilities

Everything from 1.0.2 and earlier: newspaper front page, long-form reading rhythm, KaTeX/Mermaid/highlight.js on demand, media fallbacks, Markdown archive and REST export, llms.txt endpoints and settings, ad slots, default mail styling, full i18n, floating TOC.

## License route

Unchanged: GPL-2.0-or-later declared; this distribution exercises the GPLv3 option (DOMPurify Apache-2.0 branch). No new third-party resources were introduced; two resource-manifest hashes were refreshed for changed source files.

## Candidate package

- **File:** `paddy-newsprint-1.1.0.zip`
- **SHA-256:** `3e782330d3503467dded9a47235725152fb4a2ca868d8ec1277b21822a5cbedc`
- **Size:** 5,313,553 bytes
- **Theme files:** 238
- **Package directory:** `paddysun-newsprint/`
- **COS download URL (reserved, pending owner upload):**
  `https://cos.paddysun.top/paddy-newsprint/rel/paddy-newsprint-1.1.0.zip`

## Verification evidence (workspace repo)

- Dual-agent review and consolidated audit: `docs/paddy-newsprint-1.1.0-release/audit-110-summary.md` and full agent reports
- Penetration test: `docs/paddy-newsprint-1.1.0-release/pentest-110-report.md`
- Theme Check output: `docs/paddy-newsprint-1.1.0-release/theme-check-output.txt`
- Manual test checklist (owner-executed, passed): `docs/paddy-newsprint-1.1.0-release/manual-test-checklist.md`

## Important scope notes

- The package was tested in isolated synthetic WordPress environments with imported content volume; no real site, credential, database or configuration was read or written.
- This release note does not represent WordPress.org submission or approval.
- CDN edge caching requires three console rules on the CDN side (follow origin Cache-Control, ignore query strings for cache keys, bypass for logged-in/comment cookies) and a "do not cache responses with Set-Cookie" behavior (EdgeOne default).

## Upgrade notes

- After upgrading, assign the new templates: create (or reuse) pages for /blog and /tags and select "档案馆页" / "版签总目页" in the page settings template dropdown.
- No data migration is required; both index pages build from existing posts, categories and tags.
- 1.0.2 footnote self-healing behavior is unchanged.
