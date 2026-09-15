# Paddy Newsprint v0.10.13 — Newspaper-Style Chinese WordPress Block Theme

Paddysun Newsprint 0.10.13

Paddysun Newsprint 0.10.13 is a newspaper-style Chinese WordPress block theme for long-form writing and content publishing.

## What's new in 0.10.13

- New **Appearance → llms.txt Settings** screen: independent switches for `/llms.txt` and `/llms-full.txt`.
- The `/llms-full.txt` switch only appears while the `/llms.txt` index is enabled; disabling either route returns a 403 refusal (`Cache-Control: private, no-store`) and stops advertising it.
- Three content sources for `/llms.txt`: fully generated (site name, description and latest posts grouped by category), partially manual (generated list plus hand-written introduction) and fully manual.
- Full-export controls aligned with the feed reading settings: post count (1–500, default 100) and full text versus excerpt only.
- `rel="describedby"` broadcast following the llms.txt v2 convention, emitted both in the document head and as an HTTP `Link` response header.
- **Behaviour change:** `/llms-full.txt` now defaults to **disabled** and must be enabled on the settings screen after upgrading. `/llms.txt` stays enabled by default and no longer advertises the disabled full-export route. With settings restored to the 0.10.12 equivalent (index on, full on, generated, 100 posts, full text) both routes are byte-identical to 0.10.12.

## Retained capabilities

- Newspaper-style front page with masthead, side ears, briefs, lead story, sidebar and edition index.
- Local typography and bundled assets with no theme-owned external runtime requests.
- Markdown archive and REST Markdown export.
- JSON-LD / SEO output with existing SEO-plugin coexistence behavior.
- KaTeX formulas, Mermaid diagrams and highlight.js code highlighting.
- Image and video failure fallback with local artwork, alt text and replacement notices.
- Minimum PHP declaration remains PHP 7.4.
- WordPress.org packaging work is not part of this release.

## License route

- The theme retains its GPL-2.0-or-later license declaration.
- For this distribution, the GPL-covered portions use the GPLv3 option permitted by GPL-2.0-or-later.
- This is not a GPL-3.0-only relicensing.
- DOMPurify uses its Apache-2.0 alternative; the original Apache-2.0 / MPL-2.0 dual-license notice remains included.
- Third-party MIT, BSD, ISC, Apache-2.0 and OFL notices are retained in the package.
- `points-on-curve 0.2.0` is distributed under its complete upstream MIT license. Its flatness attribution is retained in the theme README and distribution-license documentation.
- No new third-party resource was introduced by the 0.10.13 change set; the 0.10.12 license route is carried over unchanged.

## Candidate package

- **File:** `paddy-newsprint-0.10.13.zip`
- **SHA-256:**
  `94b6c4d238faf0a2d9c5999641ca38c7dcb977e729daf08282faf65fdcce11f0`
- **Size:** 5,785,156 bytes
- **Theme files:** 224
- **Package directory:** `paddysun-newsprint/`
- **COS download URL (reserved, pending owner upload):**
  `https://cos.paddysun.top/paddy-newsprint/rel/paddy-newsprint-0.10.13.zip`

## Verification

The candidate ZIP was verified against the public theme source, the staged release repository and four isolated WordPress environments:

- Source/stage/ZIP file bytes matched (224 members, complete `verify_zip` pass).
- ZIP integrity and member paths passed; no duplicate members, directory entries, traversal or Windows alias names.
- 42 public checks completed with no failed commands; privacy scan reported no hits.
- PHP 7.4.33 and PHP 8.3.30 compatibility checks passed within the recorded test scope.
- WordPress 7.1 fresh installation passed on PHP 7.4.33 and PHP 8.3.30.
- WordPress 7.1 upgrade from the 0.10.12 ZIP passed on PHP 7.4.33 and PHP 8.3.30.
- All four ZIP installation/upgrade environments matched all 224 candidate files.
- No extra installed files were found.
- HTTP regression matrix: 442/442 assertions passed (including the new llms defaults, the `rel="describedby"` broadcast and 403 refusals with `private, no-store`).
- Upgrade content, custom template, post metadata and theme modifications were preserved.
- JSON-LD, REST Markdown, `llms.txt`, `llms-full.txt` public/private/password visibility and cache behavior passed within the recorded scope.
- Release gate: p1, privacy and distribution_license all PASS with hash-bound evidence.
- Independent code security review of the 0.10.13 runtime change set found no exploitable issue.

## Important scope notes

- The package was tested in isolated synthetic WordPress environments.
- GUI ZIP upload was not claimed: installation and upgrade used the WordPress `Theme_Upgrader` API (the same core path the admin upload uses) rather than a browser file chooser.
- The package has not been deployed by this release process.
- COS upload is handled separately by the owner; the download URL above is reserved but not yet live.
- This release note does not represent WordPress.org submission or approval.

## Evidence

- Candidate installation and upgrade results:
  `docs/paddy-newsprint-0.10.13-release/install-upgrade/final-results.json`
- HTTP regression summary:
  `docs/paddy-newsprint-0.10.13-release/install-upgrade/http-summary.json`
- Release gate with hash-bound reviews:
  `docs/paddy-newsprint-0.10.13-release/release-gate.json`
- Frozen baseline and protection set:
  `docs/paddy-newsprint-0.10.13-baseline.json`
- Static public checks (42 commands):
  `docs/paddy-newsprint-0.10.13-release/checks/summary.json`
- Owner acceptance and code security review:
  `docs/paddy-newsprint-0.10.13-llms/acceptance.md`, `docs/paddy-newsprint-0.10.13-llms/final.md`
