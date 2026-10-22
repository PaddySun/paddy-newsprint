# Paddy Newsprint v1.1.2 — Comment-Aware Conditional Request Fix

Paddysun Newsprint 1.1.2

Hotfix for the 1.1.x CDN edge-cache layer: **new comments on a published post were invisible to returning visitors**, who kept receiving a `304 Not Modified` and the page snapshot from the moment the post was published (reported live as "there is no comment section").

## Root cause and fix

- The `Last-Modified` validator emitted by the edge-cache gateway took only the site-wide maximum of `post_modified_gmt`. Comments never touch their parent post row, so after publishing, the validator stood still forever — every conditional request (`If-Modified-Since`) was answered 304 by the theme, pinning browsers (and any validator-honoring CDN path) to the publish-time snapshot regardless of new comments.
- The validator now incorporates, in addition to the posts maximum:
  1. the newest **approved comment** time (`MAX(comment_date_gmt)`),
  2. a monotonic **content stamp** option (`paddysun_ns_stamp`) advanced by post saves, comment insert/edit/status changes, and taxonomy changes — so the validator can never regress even if the newest comment is later deleted.
- Freshness policy: posts published within the last 24 hours now get `s-maxage=60, stale-while-revalidate=300` (comment flow on fresh posts becomes visible within a minute); all other views keep `s-maxage=600, stale-while-revalidate=3600`. The window is filterable via `paddysun_ns_fresh_window`.
- Option writes gained an `add_option` fallback for persistence layers where `update_option` cannot create a missing option row.

## Self-healing for affected browsers

No manual action needed. After upgrading, the validator is already newer than any stale snapshot's `If-Modified-Since`, so the next refresh returns `200` with the full current page (comments included).

## Candidate package

- **File:** `paddy-newsprint-1.1.2.zip`
- **SHA-256:** `0bd592e7da63c8a19305e3a349a598b250b5caa0a8e2e8d19d0331760a58111e`
- **Size:** 5,315,134 bytes
- **Theme files:** 238
- **COS download URL (reserved, pending owner upload):**
  `https://cos.paddysun.top/paddy-newsprint/rel/paddy-newsprint-1.1.2.zip`

## Verification

- Live reproduction on the isolated site (1.1.1 code): GET captured `Last-Modified`, a new approved comment was inserted, conditional GET with the captured validator returned **304** — defect reproduced.
- With the 1.1.2 gateway: the same conditional GET returns **200** with the comment present in the body, and the validator advances with every new comment (`11:46:29 → 11:46:50` across two inserts).
- Freshness policy toggles both ways: with a fresh post present the header reads `s-maxage=60, stale-while-revalidate=300`; after removal it returns to `s-maxage=600, stale-while-revalidate=3600`.
- Release scan 0 errors; `verify_zip` byte-identical to the public source tree; version markers (style.css / readme.txt / functions.php) agree at 1.1.2.
