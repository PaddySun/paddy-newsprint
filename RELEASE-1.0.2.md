# Paddy Newsprint v1.0.2 — Newspaper-Style Chinese WordPress Block Theme

Paddysun Newsprint 1.0.2

Paddysun Newsprint 1.0.2 is a newspaper-style Chinese WordPress block theme for long-form writing and content publishing. This is a defect-fix release driven by two production-reported issues: the Markdown footnote pipeline losing/multiplying footnotes, and shields.io-style inline badges breaking paragraph layout.

## What's new in 1.0.2

- **Footnote pipeline fixed (two production symptoms).** Definition lines (`[^id]: text`) were previously recognized only in the standalone-paragraph block form. Definitions written as list items (`- [^id]: text` — the common paste form) were silently dropped: they never reached the footnotes meta (the end-of-post list rendered empty numbered stubs), the definition label itself was wrongly converted into a duplicate reference marker, and the definition text stayed stranded in the body as plain text. Re-pasting the same Markdown multiplied the damage on every edit. The pipeline now extracts definitions from paragraph, list-item and bare `<p>` forms, no longer converts colon-following labels into refs, and removes emptied list containers.
- **Damaged legacy posts self-heal.** Posts already damaged by the old behavior (duplicate refs + stranded definition text) are repaired automatically on their next save: a tight fingerprint (a reference marker at the very start of a list item / paragraph followed by a colon — producible only by the old defect) is restored into a definition line and extracted. Verified end-to-end in the release matrix: a fixture seeded damaged under 1.0.1, upgraded to 1.0.2, and healed by one re-save on both PHP 7.4 and 8.3.
- **`repair-footnotes.php` CLI tool shipped** (ABSPATH- and CLI-guarded): class-A damage heals via a triggered re-save; posts whose refs are intact but whose footnotes meta was lost (the /5492 signature) are rebuilt from the post's source Markdown (`原文 MD`) definitions, including re-adding the missing `wp:footnotes` block. Idempotent, supports `--dry` and explicit IDs.
- **Inline badges fixed.** Images placed inside paragraphs/list text (shields.io-style badges) were forced block-level by the theme's global figure rules — breaking the line, misaligning the baseline and preventing side-by-side placement. Inline images in text blocks now keep inline flow (vertical-align middle, natural size, natural margins). Standalone image blocks keep the existing figure protocol (block-level, centered, captions).
- **Documented trade-off:** a reference immediately followed by a half-width colon in prose (`…[^2]: …`) now stays literal instead of converting — this form is indistinguishable from a definition line and was the exact damage vector. Full-width colon (`：`) is unaffected. Code-block `[^x]` literals remain untouched.

## Retained capabilities

- Newspaper-style front page with masthead, side ears, briefs, lead story, sidebar and edition index.
- Full i18n per the 1.0.1 release: gettext-wrapped strings, bundled `.pot` template and English pack (en_US sites out-of-the-box English, including both admin settings screens).
- Local typography and bundled assets with no theme-owned external runtime requests.
- Markdown archive and REST Markdown export; JSON-LD / SEO output; KaTeX, Mermaid, highlight.js.
- llms.txt settings and ad slots with the 1.0.1 behavior unchanged.
- Minimum PHP declaration remains PHP 7.4.
- Actual WordPress.org submission remains the site owner's action.

## Upgrade notes for affected posts

- Posts with duplicated refs and stranded definition text (/5457 signature): after upgrading, edit and save each affected post once — it heals automatically.
- Posts whose footnotes list vanished while refs look intact (/5492 signature): run `wp eval-file wp-content/themes/paddysun-newsprint/repair-footnotes.php <post_id>` (the post's `原文 MD` must contain the `[^id]:` definitions), or run the tool without IDs to scan everything. Add `--dry` to preview.

## License route

- The theme retains its GPL-2.0-or-later license declaration; this distribution uses the GPLv3 option permitted by GPL-2.0-or-later. DOMPurify uses its Apache-2.0 alternative; `points-on-curve 0.2.0` is distributed under its complete upstream MIT license.
- No new third-party resource was introduced by the 1.0.2 change set; two resource-manifest hashes were refreshed (content-filters.php, readme.txt).

## Candidate package

- **File:** `paddy-newsprint-1.0.2.zip`
- **SHA-256:**
  `3e70b6531bc5a54493783023281bd0621f8ca1a2fe9bcc8e152e8b3ac73fdf95`
- **Size:** 5,336,955 bytes
- **Theme files:** 234 (233 + the CLI repair tool)
- **Package directory:** `paddysun-newsprint/`
- **COS download URL (reserved, pending owner upload):**
  `https://cos.paddysun.top/paddy-newsprint/rel/paddy-newsprint-1.0.2.zip`

## Verification

The candidate ZIP was verified against the public theme source, the staged release repository and four isolated WordPress environments:

- Source/stage/ZIP file bytes matched (234 members, complete `verify_zip` pass).
- 48 public checks completed with no failed commands; privacy scan no hits; zero-ad-code scan PASS over all files.
- Theme Check 20260901 full run against the 1.0.2 source: 9,988 tests passed, 0 REQUIRED, 0 WARNING; the two known INFO items were dispositioned (intentional CC license link; single text-domain matching the slug).
- Footnote fix suite: 15/15 checks passed on the synthetic site (list-form/paragraph-form extraction, damaged-content heal, second-save idempotency, code-block false positives, colon trade-off).
- Inline-badge fix verified by rendered-DOM check and browser screenshot: two badges inside one paragraph stay inline, vertically centered, at natural size.
- WordPress 7.1 fresh installation passed on PHP 7.4.33 and PHP 8.3.30; upgrade from the 1.0.1 ZIP passed on both runtimes.
- All four environments matched all 234 candidate files; upgrade content, custom template, post metadata and theme modifications preserved.
- New long-term regression fixtures in the install/upgrade matrix: a footnote fixture (two body refs + a list-form definition) and an inline-badge fixture, asserted on every site; on the upgrade environments the footnote fixture is seeded damaged under 1.0.1 (3 refs) and asserted healed to 2 refs after the upgrade + one re-save — the damage→upgrade→heal chain is now part of the permanent matrix.
- HTTP regression matrix: 594/594 assertions passed (148+149+148+149), including the new footnote/badge cases.
- Release gate: p1 (fix change set), privacy and distribution_license all PASS with hash-bound evidence.
- Code security review of the 1.0.2 change set found no exploitable issue (linear regexes, no new input paths; the shipped CLI tool is ABSPATH/CLI-guarded); recorded in `docs/paddy-newsprint-1.0.2-release/security-review.md`; an independent pass before push is recommended.

## Important scope notes

- The package was tested in isolated synthetic WordPress environments; no real site, credential, database or configuration was read or written.
- Installation/upgrade used the WordPress `Theme_Upgrader` API rather than a browser file chooser.
- This release note does not represent WordPress.org submission or approval.
- The owner hand-tests this candidate (1.0.1 install + production content import + upgrade, and a fresh-site authoring flow) before any push.

## Evidence

- Fix diagnosis and verification record: `docs/paddy-newsprint-1.0.2-fixes/fixes-record.md`
- Footnote fix suite output: `docs/paddy-newsprint-1.0.2-fixes/fn-repro2-output.txt`
- Theme Check output: `docs/paddy-newsprint-1.0.2-release/theme-check-output.txt`
- Install/upgrade results: `docs/paddy-newsprint-1.0.2-release/install-upgrade/final-results.json`
- HTTP regression summary: `docs/paddy-newsprint-1.0.2-release/install-upgrade/http-summary.json`
- Release gate: `docs/paddy-newsprint-1.0.2-release/release-gate.json`
- Security review: `docs/paddy-newsprint-1.0.2-release/security-review.md`
