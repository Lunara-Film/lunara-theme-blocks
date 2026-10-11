# Lunara Film: public presentation contract

Lunara is a film publication and a living Oscar ledger. Its identity comes from
specific criticism, real film imagery, the Lunara wordmark, Tiempos, midnight navy
and restrained gold. This contract applies to the existing visitor features.

## Composition

- Use one clear heading per section. Do not precede it with a second label that
  repeats its meaning. Preserve a saved editorial label when it adds information.
- Echo, Counter and Context identify three different critical relationships.
  Keep their full arguments and film links. Present them as editorial columns
  that stack on phones, with no painted container inside another painted container.
- Journal leads with headlines; dates, visual credits and useful taxonomy remain
  readable supporting information. Entries without artwork are intentional text
  compositions, not failed image placeholders.
  The newest main archive edition has one lead and two supporting stories, then
  a quieter thumbnail index on desktop. Alternate sorts, taxonomy results and
  later pages stay uniform; phone order remains the source reading order.
  Responsive image hints and synchronous route geometry must change with layout.
- Oscar records retain category, film/person, status, evidence and destinations.
  Status has words as well as color. Long names and complete records remain usable.
- Use whitespace, image proportions, typography and restrained separators for
  hierarchy. Footer navigation is a publication directory, with the same links
  and saved editing ownership.

## Surfaces and type

- Ground: `#0a1520`; reading text: near-white; accent: `#c9a961` or `#e0c481`.
  Saved valid color choices must keep their intended semantics and contrast.
- Tiempos Headline carries editorial headings; Tiempos Text carries reading.
  Licensed font files stay in `/wp-content/uploads/lunara-fonts/v1/`, outside Git.
  Font fallback must preserve readable geometry.
- Ordinary reading/card copy is normally 16–18px; functional metadata and controls
  are at least 13px. These are house quality rules, not claims about WCAG pixel
  requirements. Body text uses normal tracking and ordinary case.
- Long prose stays approximately 60–75 characters wide. Phone gutters are
  normally at least 20px, with room for safe areas and text scaling.
- No ornamental radial lighting, grain, repeating stripes, chromatic glow,
  card-within-card frames, decorative ordinal numerals or bouncing labels.
- A local dark scrim behind words on a photograph is functional. Focus outlines,
  selected-state cues, dialogs and purposeful separators remain visible.
- Links use stable geometry and recognizable underline/focus feedback. Controls
  aim for 44px touch targets. Do not animate padding, width or document layout.
- Reduced motion and failed/no JavaScript preserve content and navigation.

## Ownership and delivery

Edit the source that owns a property. Do not add another catch-all override to
the end of `style.css`. Read the PHP enqueue/emission path before editing: Studio
variables, Customizer output and first-paint styles can win over a static file.

| Surface | Canonical owners |
|---|---|
| Ground, shared typography, legacy public primitives | `style.css`, `inc/customizer.php`, `inc/design-tokens.php` |
| Public shell | `assets/css/lunara-shell.css`; generate the non-portal asset with `node tests/tools/build-shell-css.cjs` |
| Adopted Home carousel presentation | `inc/home-carousels.php`, `assets/css/lunara-home-carousels.css` |
| Home Method/forecast and legacy sections | Live renderers in `functions.php`, `assets/css/lunara-home-modules.css`, `inc/debrief.php` |
| Shared film arguments and review components | `inc/debrief.php`, `assets/css/lunara-review-components.css` |
| Journal archive | `archive-journal.php`, `inc/journal-archive-studio.php`, `assets/css/lunara-journal-archive.css`, `inc/journal-archive-critical.php` |
| Footer and shared public inline output | `inc/frontend.php`, `assets/css/lunara-public-guardrails.css` |
| Menu and search | `inc/header-command.php`, `inc/live-search.php`, scoped public CSS |
| Oscar records and explorer | Ledger plugin templates and public CSS; theme `assets/css/oscars.css` owns integration only |

`inc/` normally supplies the live function before guarded duplicates in
`functions.php`, but Home forecast/Method and Journal provenance have live
exceptions. Trace definitions rather than assuming every duplicate is dead.

Do not change stored article copy, selected records, source attribution, URLs or
image-off decisions to satisfy an aesthetic detector. Config changes must round
trip blank and custom values and preserve revision/restore behavior. A cached
payload schema change must follow the cache-version rule in `AGENTS.md`.

## Bounded acceptance

1. Map the change to the retained finding IDs and affected shared owner. Fix all
   its consumers together. Detector matches are leads, not automatic defects.
2. During implementation run only relevant behavior tests after a meaningful
   change. Tests must measure output, interaction or geometry; no text pins.
3. Run one candidate acceptance batch: required CI, desktop/phone composition,
   all carousel records, keyboard/menu/search, empty/missing-art states, saved
   presentation, first/settled paint and reduced motion. Preserve the receipts.
4. Fix the discovered failures in a batch. Confirm changed assertions and their
   affected consumers. Repeat only when new edits or unresolved failures justify
   it. Never rerun the whole site simply to obtain a different detector score.
5. Run the pinned Impeccable detector once on the completed candidate coverage.
   Record each remaining match as fixed, evidenced intentional/false positive,
   or open with its exact blocker. Never suppress an unexplained instance.
6. Dalton reviews the concrete visual result. Plugins merge/deploy before theme;
   the theme currently requires his WordPress.com deploy click after merge.
   Green tests, a PR, a merge and a verified public deployment are separate facts.

The supplied Home and Journal overlays remain the original location authority.
The original `rm`/`lg` findings were traced to installed Reader Mode and Link
Grabber extension code, with exact markup/CSS and manifest hashes retained in the
audit evidence. They remain external findings, with no theme suppression. The
reported eight-curve contour still needs exact provenance. Do not claim complete
overlay closure until that evidence is established.
