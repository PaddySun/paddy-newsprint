# Paddy Newsprint v1.1.1 — Editor Validation Fix

Paddysun Newsprint 1.1.1

Hotfix for 1.1.0: the Site Editor reported "This block contains unexpected or invalid content" when opening the new Archive (`template-blog`) and Tag Index (`template-tags`) templates.

## Root cause and fix

- The two index block patterns emitted their main output as plain HTML with **no block delimiters**. The front end renders PHP patterns by execution, so pages looked correct — but the Site Editor resolves pattern content into blocks for the canvas, and delimiter-free HTML failed block validation, marking the enclosing group with a recovery warning.
- The main branch output of both patterns is now wrapped in a `core/html` block envelope (`<!-- wp:html --> … <!-- /wp:html -->`). Front-end output is byte-identical (block comments are stripped at render); the editor now parses the pattern as a single valid HTML block. Verified by reproducing the warning on an isolated site, applying the fix, and confirming both templates open clean (zero notices, zero warnings).
- Theme update/switch now bumps the index cache version (`after_switch_theme`), so stale 1.1.0-format transients are rebuilt immediately instead of waiting out the 10-minute TTL.

## Note for sites that saved these templates in 1.1.0

If you opened and saved either template in the Site Editor during 1.1.0, WordPress stored a user-customized template copy in the database which will keep shadowing the theme file. Delete the customized copy (Appearance → Editor → Templates → look for the "customized" indicator) to fall back to the shipped template.

## Candidate package

- **File:** `paddy-newsprint-1.1.1.zip`
- **SHA-256:** `6e6224500e920436dd94a76df405b7da300b918b08051b3919c6bf9fea39b228`
- **Size:** 5,313,920 bytes
- **Theme files:** 238
- **COS download URL (reserved, pending owner upload):**
  `https://cos.paddysun.top/paddy-newsprint/rel/paddy-newsprint-1.1.1.zip`

## Verification

- Reproduced the editor warning on the isolated site (iframe-scoped query: "Block contains unexpected or invalid content / Attempt recovery" attached to the `np-blog` group); after the fix both templates open with zero notices and zero warned blocks, with the full archive (109 posts) and tag index (12 groups / 354 chips) rendering in the canvas.
- Front-end parity: rendered `/blog/` shows no block-comment residue, identical DOM structure and item count.
- Release scan (static checks, privacy rules) 0 errors; `verify_zip` byte-identical to the public source tree.
