# Paddy Newsprint v1.0.1 — Newspaper-Style Chinese WordPress Block Theme

Paddysun Newsprint 1.0.1

Paddysun Newsprint 1.0.1 is a newspaper-style Chinese WordPress block theme for long-form writing and content publishing. This release makes the theme fully internationalized to the WordPress.org theme-review i18n requirement: all user-visible PHP strings are translatable, and an English translation pack ships with the theme, so English (en_US) sites present an English interface out of the box — including both admin settings screens — while Chinese sites keep the exact 1.0.0 output.

## What's new in 1.0.1

- **Internationalization to the wp.org i18n requirement.** Every user-visible PHP string is wrapped in gettext functions under the single `paddysun-newsprint` text domain (previously hardcoded surfaces now covered: the llms.txt / llms-full.txt output, the llms settings hints, the button-wall alt texts, article date formats, and the ad-slot media-frame title). The HTML block templates keep their static text under the review requirement's explicit "temporary exception" for HTML template files.
- **Bundled English translation.** The theme ships `languages/paddysun-newsprint.pot` (229 entries) plus `en_US.po/.mo` (218 translated entries, newspaper register: Latest Briefs, Letters, Search the paper…, Copy MD…). Loading uses the WordPress core standard path only — `load_theme_textdomain()` plus the bundled `{locale}.mo`; the theme contains **no custom language-pack loader code**. Runtime testing on WP 7.1 confirmed that en_US sites load the bundled pack through the standard mechanism.
- **English out of the box, including the admin.** On en_US sites the front end and both admin screens (Appearance → llms.txt settings, Appearance → Ad Slots) render in English; on zh_CN and other locales the output is unchanged from 1.0.0.
- **Fixed: llms.txt category grouping.** The grouping key compared the category display name against the Chinese default name; on renamed or non-Chinese sites the default category leaked through as its own llms.txt group header (e.g. "Uncategorized"). Grouping now compares the category slug, aligned with the theme's existing slug-based checks. This is the release's single intended behavior change.
- **Screenshot recompressed** at the same 1200×900 size (712 KB → 219 KB), clearing the Theme Check file-size warning.
- `Domain Path: /languages` added to the style.css header; readme.txt changelog updated.

## Retained capabilities

- Newspaper-style front page with masthead, side ears, briefs, lead story, sidebar and edition index.
- Local typography and bundled assets with no theme-owned external runtime requests.
- Markdown archive and REST Markdown export.
- JSON-LD / SEO output with existing SEO-plugin coexistence behavior.
- KaTeX formulas, Mermaid diagrams and highlight.js code highlighting.
- Image and video failure fallback with local artwork, alt text and replacement notices.
- llms.txt settings and ad slots with the 1.0.0 defaults and behavior unchanged.
- Minimum PHP declaration remains PHP 7.4.
- Actual WordPress.org submission remains the site owner's action; this release performs the compliance engineering only.

## Known i18n scope notes (owner decision, 2026-09-20)

- Source strings remain Chinese (the owner elected to keep the Chinese source language rather than flipping to English source); the English pack provides en coverage per the requirement "all text strings must be translatable using gettext".
- theme.json palette/typography names and pattern file titles are editor-facing metadata that the gettext layer cannot reach; they display in Chinese in the site editor on all locales.
- JavaScript fallback strings (used only if a script's localized data is missing) remain the 1.0.0 Chinese literals; the normal path supplies localized strings through `wp_localize_script`.

## License route

- The theme retains its GPL-2.0-or-later license declaration.
- For this distribution, the GPL-covered portions use the GPLv3 option permitted by GPL-2.0-or-later.
- DOMPurify uses its Apache-2.0 alternative; the original dual-license notice remains included.
- `points-on-curve 0.2.0` is distributed under its complete upstream MIT license.
- No new third-party resource was introduced by the 1.0.1 change set; the 0.10.12 license route is carried over (three manifest hash refreshes cover the changed files only; translation files are first-party data).

## Candidate package

- **File:** `paddy-newsprint-1.0.1.zip`
- **SHA-256:**
  `43cf0e3fcae8373ff42d34cc7ca0871f574ad38facd67ed953fb5e6f546cf3a0`
- **Size:** 5,332,591 bytes
- **Theme files:** 233 (230 + pot/po/mo translation files)
- **Package directory:** `paddysun-newsprint/`
- **COS download URL (reserved, pending owner upload):**
  `https://cos.paddysun.top/paddy-newsprint/rel/paddy-newsprint-1.0.1.zip`

## Verification

The candidate ZIP was verified against the public theme source, the staged release repository and four isolated WordPress environments:

- Source/stage/ZIP file bytes matched (233 members, complete `verify_zip` pass).
- ZIP integrity and member paths passed; no duplicate members, directory entries, traversal or Windows alias names.
- 48 public checks completed with no failed commands; privacy scan reported no hits; the zero-ad-code scan covered all 233 files with zero hits.
- Theme Check 20260901 (the wordpress.org automated review tool) ran the full suite against the theme source: 9,642 tests passed with 0 REQUIRED and 0 WARNING results; two INFO items were dispositioned (an intentional CC license link in the button-wall pattern; single text-domain matching the slug).
- WordPress 7.1 fresh installation passed on PHP 7.4.33 and PHP 8.3.30.
- WordPress 7.1 upgrade from the 1.0.0 ZIP passed on PHP 7.4.33 and PHP 8.3.30.
- All four ZIP installation/upgrade environments matched all 233 candidate files; no extra installed files.
- HTTP regression matrix: 558/558 assertions passed, including new assertions that the bundled English pack loads on the en_US default sites (Latest Briefs / Copy MD rendered; Chinese source strings absent) and that the llms-full refusal body renders in English.
- Upgrade content, custom template, post metadata and theme modifications were preserved.
- Bilingual byte-regression on a separate synthetic site: zh_CN output byte-identical to the 1.0.0 candidate on 8 of 9 normalized page captures; the ninth (llms.txt) differs only by the intended category-grouping fix.
- JSON-LD, REST Markdown, `llms.txt`, `llms-full.txt` public/private/password visibility and cache behavior passed within the recorded scope.
- Release gate: p1 (i18n change set), privacy and distribution_license all PASS with hash-bound evidence.
- Code security review of the 1.0.1 change set found no exploitable issue; the review is recorded in `docs/paddy-newsprint-1.0.1-release/security-review.md` and an additional independent pass before push is recommended.

## Important scope notes

- The package was tested in isolated synthetic WordPress environments.
- GUI ZIP upload was not claimed: installation and upgrade used the WordPress `Theme_Upgrader` API rather than a browser file chooser.
- The package has not been deployed by this release process.
- COS upload is handled separately by the owner; the download URL above is reserved but not yet live.
- This release note does not represent WordPress.org submission or approval. Before submitting, the owner should set the readme.txt `Contributors:` field to the real wordpress.org username and point the Theme URI at the directory page.

## Evidence

- Candidate installation and upgrade results:
  `docs/paddy-newsprint-1.0.1-release/install-upgrade/final-results.json`
- HTTP regression summary:
  `docs/paddy-newsprint-1.0.1-release/install-upgrade/http-summary.json`
- Release gate with hash-bound reviews:
  `docs/paddy-newsprint-1.0.1-release/release-gate.json`
- i18n phase records (en_US behavior test, gettext wrap, pack build, bilingual regression):
  `docs/paddy-newsprint-1.0.1-i18n/phase0-phase1-record.md`
  `docs/paddy-newsprint-1.0.1-i18n/phase2-i18n-record.md`
- Theme Check record and outputs:
  `docs/paddy-newsprint-1.0.1-theme-check/theme-check-record.md`
- WordPress.org required-checklist mapping:
  `docs/paddy-newsprint-1.0.1-wporg-compliance.md`
- Security review:
  `docs/paddy-newsprint-1.0.1-release/security-review.md`
