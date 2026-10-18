# Editorial annotation closure — 15 October 2026

> Historical candidate record. Preserve these verification receipts; current
> merge and deployment authority is `AGENTS.md` and
> [the shared merge policy](AGENT-MERGE-POLICY.md). Agents merge verified work;
> requested visual approval and deployment remain separate decisions.

Scope: Dalton's fifteen browser annotations on the earlier candidate and live
Reviews. Theme 3.2.112 draft revision; no production change. The screenshots
identify the requested instances. The approved Ledger navigator is preserved.

| Comments | Cause and owning change | Verification and regression guard |
| --- | --- | --- |
| 1, 3, 4, 11 | Competing section scales, a separate portal font alias, and old recovery rules forcing body type at 800 weight. Base, shell and portal CSS now use the shared display role and section token; supporting copy is regular body type. | Actual live heading font inspected: Tiempos Headline Semibold. Annotation browser fixture checks matching section roles at 390, 690, 1187 and 1440px. DESIGN.md records the contract. |
| 2 | Approved Ledger navigator. | No navigator rules or markup changed. Visual inspection retained its horizontal rhythm. |
| 3 | Header pseudo-element draws the decorative gold underline. | Removed at its shell owner. Annotation fixture checks absent generated content. |
| 4 | Embedded landing content lacks consistent inner gutters; heading boxes and typography conflict. | Portal embedding owner supplies 20–36px padding, regular supporting copy and centered display headings. Fixture measures minimum gutters. |
| 5 | Research/winners section frames and embedded header/footer borders stack; caption boxes do not share the poster axis. | Removed those owned frames; full-width centered film captions. Fixture checks frame removal and caption axis; captured public content inspected with the Ledger stylesheet included. |
| 6 | Footer places twelve links in three tall, padded columns. | Shared footer becomes three compact wrapping directory rows. Real footer renderer test covers empty, single, default, long and twelve-link configurations: 500 assertions; 44px targets retained. Default desktop candidate about 296px tall. |
| 7, 8 | Native hero inherits a bottom margin/padding in addition to the page gap, plus an excessive minimum height at tablet width. | Hero route removes inherited blank band, bounds minimum height and retains content-driven growth. Annotation fixture checks bottom gap; actual carousel suite checks long copy, all six items, phone controls and no-JavaScript output: 135 checks. |
| 9, 10, 15 | Full-width posters dominate unseparated arguments; source links float below them. | Shared pairing component caps posters at 260px, uses lightly ruled argument columns and an attached source row with 44px links. Full roles, arguments and destinations remain. Annotation fixture and Reviews first-paint suite cover the shared treatment. This is a visual interpretation for Dalton to review, not a claim that visual approval has occurred. |
| 12 | Forecast art and argument have no containing visual relationship. | Home module owner adds inset copy and subtle side/bottom rules, keeping square geometry and existing carousel behavior. |
| 13, 14 | Renderer splits four records into a companion carousel before the archive. | Actual PHP renderer now sends all non-lead records into one index, removes the duplicate header and retires unused editor labels. Renderer tests verify order and completeness across the old split boundary: 171 assertions. Saved values are retained for compatibility. |

## Delivery checks and limits

- Reviews first-paint suite: six widths across six saved-order/page scenarios;
  maximum measured delivery shift under 1px, no seed-persistence drift beyond
  subpixel rounding. Structural seed remains within the existing 12KB budget.
- Annotation fixture: 56 checks. It is a component simulation using source CSS,
  not a full WordPress environment. Footer and review markup have separate real
  PHP renderer coverage.
- The old gallery omitted the Ledger stylesheet. The revised local gallery
  includes that public stylesheet and captured public HTML, source Theme styles
  and actual critical seeds. Reviews preview markup reflects the actual renderer
  change. Carousel content is expanded for inspection. External images and fonts
  require network access. Local preview is not deployed acceptance.
- Existing PR #233 remains the only Theme candidate. Required CI must pass for
  its revised commit; Dalton's visual review, merge and manual deployment remain
  separate steps. No new dependencies, caches, data migrations or plugin edits.
- Later publication must check the actual WordPress pages once against these
  specific annotations. Repeat only checks affected by a new change or failure.
