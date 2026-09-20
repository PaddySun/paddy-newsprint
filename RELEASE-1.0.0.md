# Paddy Newsprint v1.0.0 — Newspaper-Style Chinese WordPress Block Theme

Paddysun Newsprint 1.0.0

Paddysun Newsprint 1.0.0 is a newspaper-style Chinese WordPress block theme for long-form writing and content publishing.

## What's new in 1.0.0

- Fixed the homepage card body missing issue: when a post body is a single HTML block (the hand-pasted-HTML form), the homepage aside body now falls back to classic paragraph extraction using the existing sidebar budget (8 paragraphs / 1,400 characters) and skip rules. The lead story, briefs and single post outputs are unchanged.
- New **Appearance → Ad Slots** screen with three slots: a homepage banner (between the subscribe band and the edition index, width matching the full-sheet divider), an article sidebar square (desktop-only, aligned with the floating table of contents) and a full-width banner above the article comments (all devices, width matching the article footer double rule).
- Each ad slot supports per-device customization (desktop / tablet / mobile): media-library placeholder images with per-device enable switches and independent click-through links; images stretch to fill their box and crop beyond the box ratio (`object-fit: cover`).
- Ad code is output as trusted administrator content (only `manage_options` users can write it, NUL bytes stripped, 64 KB cap, no markup sanitization so `<script>`/`<ins>` survive); failed ads (container script errors or the network status attribute reporting unfilled) are replaced by the placeholder or removed entirely according to the backend setting.
- New plain default email styles: outgoing `wp_mail` messages are wrapped in a table-layout, fully inline-styled shell with a plain-text alternative part; when another email template or rewriting plugin is detected the theme yields entirely. No customization UI and no settings.
- The floating table of contents now hugs the right edge of the content column and defaults to collapsed (pull it open with the toggle).
- **Behaviour change:** the homepage zero-JavaScript baseline now holds only while no ad code is filled. Once ad code is entered on the homepage slot, that code brings its own scripts (and the cleanup script loads with it); whether to accept this is the site owner's decision. The factory/placeholder state remains zero-JS.

## Retained capabilities

- Newspaper-style front page with masthead, side ears, briefs, lead story, sidebar and edition index.
- Local typography and bundled assets with no theme-owned external runtime requests.
- Markdown archive and REST Markdown export.
- JSON-LD / SEO output with existing SEO-plugin coexistence behavior.
- KaTeX formulas, Mermaid diagrams and highlight.js code highlighting.
- Image and video failure fallback with local artwork, alt text and replacement notices.
- llms.txt settings (appearance → llms.txt settings) with the 0.10.13 defaults unchanged.
- Minimum PHP declaration remains PHP 7.4.
- WordPress.org packaging work is not part of this release.

## License route

- The theme retains its GPL-2.0-or-later license declaration.
- For this distribution, the GPL-covered portions use the GPLv3 option permitted by GPL-2.0-or-later.
- This is not a GPL-3.0-only relicensing.
- DOMPurify uses its Apache-2.0 alternative; the original Apache-2.0 / MPL-2.0 dual-license notice remains included.
- Third-party MIT, BSD, ISC, Apache-2.0 and OFL notices are retained in the package.
- `points-on-curve 0.2.0` is distributed under its complete upstream MIT license. Its flatness attribution is retained in the theme README and distribution-license documentation.
- No new third-party resource was introduced by the 1.0.0 change set; the 0.10.12 license route is carried over unchanged (the resource manifest hash refreshes cover changed files only).

## Candidate package

- **File:** `paddy-newsprint-1.0.0.zip`
- **SHA-256:**
  `f50088e7974ee9014cb49577a3e4ef72f26537320afaecbb45aa4477c97021ed`
- **Size:** 5,804,113 bytes
- **Theme files:** 230
- **Package directory:** `paddysun-newsprint/`
- **COS download URL (reserved, pending owner upload):**
  `https://cos.paddysun.top/paddy-newsprint/rel/paddy-newsprint-1.0.0.zip`

## Verification

The candidate ZIP was verified against the public theme source, the staged release repository and four isolated WordPress environments:

- Source/stage/ZIP file bytes matched (230 members, complete `verify_zip` pass).
- ZIP integrity and member paths passed; no duplicate members, directory entries, traversal or Windows alias names.
- 48 public checks completed with no failed commands; privacy scan reported no hits; a dedicated zero-ad-code scan (`ca-pub-` / `adsbygoogle` / `data-ad-slot` / `googlesyndication`) covered 230 files with zero hits.
- PHP 7.4.33 and PHP 8.3.30 compatibility checks passed within the recorded test scope.
- WordPress 7.1 fresh installation passed on PHP 7.4.33 and PHP 8.3.30.
- WordPress 7.1 upgrade from the 0.10.13 ZIP passed on PHP 7.4.33 and PHP 8.3.30.
- All four ZIP installation/upgrade environments matched all 230 candidate files.
- No extra installed files were found.
- HTTP regression matrix: 546/546 assertions passed, including the new homepage aside-body regression case for single-HTML-block posts with a GIF featured image, and the ad-slot placeholder / filled-code / cleanup state assertions plus llms/REST/feed non-exposure of the ads option.
- Upgrade content, custom template, post metadata and theme modifications were preserved.
- JSON-LD, REST Markdown, `llms.txt`, `llms-full.txt` public/private/password visibility and cache behavior passed within the recorded scope.
- Release gate: p1, privacy and distribution_license all PASS with hash-bound evidence.
- Code security review of the 1.0.0 change set (including the ads settings, email styles and client-side checks) found no exploitable issue; the review is recorded in `docs/paddy-newsprint-1.0.0-release/security-review.md` and an additional independent pass before push is recommended.

## Important scope notes

- The package was tested in isolated synthetic WordPress environments.
- GUI ZIP upload was not claimed: installation and upgrade used the WordPress `Theme_Upgrader` API (the same core path the admin upload uses) rather than a browser file chooser.
- The package has not been deployed by this release process.
- COS upload is handled separately by the owner; the download URL above is reserved but not yet live.
- This release note does not represent WordPress.org submission or approval.
- The ad click-through URLs and any real ad network codes are configured by the site owner in the admin and never shipped with the theme.

## Evidence

- Candidate installation and upgrade results:
  `docs/paddy-newsprint-1.0.0-release/install-upgrade/final-results.json`
- HTTP regression summary:
  `docs/paddy-newsprint-1.0.0-release/install-upgrade/http-summary.json`
- Release gate with hash-bound reviews:
  `docs/paddy-newsprint-1.0.0-release/release-gate.json`
- P1 root cause and fix record:
  `docs/paddy-newsprint-1.0.0-p1/root-cause.md`
- Ad slots acceptance record (five feedback rounds):
  `docs/paddy-newsprint-1.0.0-s2/conclusion.md`
- Security review:
  `docs/paddy-newsprint-1.0.0-release/security-review.md`
