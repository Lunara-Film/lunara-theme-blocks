# Lunara Film: public presentation contract

Lunara is a film publication and a living Oscar ledger. Its identity comes from
specific criticism, real film imagery, the Lunara wordmark, Tiempos, midnight navy
and restrained gold. This contract applies to the existing visitor features.

## Editorial controls

The Home Oscar Forecast uses a centered previous/next and playback control row.
Do not render one pagination marker per forecast record; all records remain
reachable through the arrows, touch scrolling and keyboard navigation.

Lunara Site Studio is a first-class daily workspace. Public presentation changes
must preserve its existing controls, saved settings, preview, Apply and history.
Keep relevant content-editor destinations accessible from Studio; do not create
competing settings stores or silently retire controls during visual refinements.
Pairing editors show read-only published-review availability by complete IMDb
identity. Unknown identity and failed lookup are distinct from no published
review. Site Studio's featured-review inspector reports all three saved roles
and links to the owning Review editor. This does not alter pairing content.

## Composition

- Center each page frame, identity/title group, reading column and section heading
  on a shared axis with equal outer gutters. Card rows wrap symmetrically; a
  partial last row is centered. Do not reserve an empty side rail or split a
  masthead into unequal columns. Group actions beneath the centered heading.
  Keep long prose and tabular records readable within those centered frames.
  Check actual box centers at phone and desktop sizes, including odd card counts.
- Use one clear heading per section. Do not precede it with a second label that
  repeats its meaning. Preserve a saved editorial label when it adds information.
- Always display the full pairing titles: Theme Echo, Counter Program and Career
  Context. They identify three different critical relationships; never shorten
  their visible titles to Echo, Counter or Context, including on phones. Allow
  natural wrapping without truncating the labels. Keep their full arguments and
  film links. Home and archive summaries use editorial columns. A full Review
  gives each pairing its own row: poster beside the complete argument, stacking
  at 640px and below. Neither layout uses a painted container inside another.
- Single Reviews own a centered 760px reading frame and below-article navigation.
  Pairing rows use a centered 860px frame. Route CSS owns that geometry; late
  Studio output may change supported presentation choices without restoring a
  side rail. Additional CSS must not be required to hold this layout together.
- Journal leads with headlines; dates, visual credits and useful taxonomy remain
  readable supporting information. Entries without artwork are intentional text
  compositions, not failed image placeholders.
  The newest main archive edition has one centered lead followed by a balanced
  two-column story index on desktop. Alternate sorts, taxonomy results and later
  pages stay uniform; phone order remains the source reading order.
  Responsive image hints and synchronous route geometry must change with layout.
- Oscar records retain category, film/person, status, evidence and destinations.
  Status has words as well as color. Long names and complete records remain usable.
- Section headings use near-white ink; gold identifies links and small accents.
  Supporting theses use reading type, not a second competing display headline.
  Archive sorting uses a visible underline for selection, retaining full touch
  targets and focus outlines. The Oscars portal has a centered masthead followed
  by its supporting Best Picture record, with no decorative light shafts.
- Use whitespace, image proportions, typography and restrained separators for
  hierarchy. Footer navigation is a publication directory, with the same links
  and saved editing ownership.
- Section headings share `--lunara-type-section`, the display face, 600 weight
  and 1.18 leading. Reading copy uses the body face at regular weight. Embedded
  Ledger headings inherit those roles; do not introduce another Tiempos alias
  or force reading copy to display weight. Preserve the Ledger navigator.
- Home and archive Debrief columns use bounded portrait artwork, lightly ruled
  argument columns and an attached source-link row. When a published Lunara
  review resolves for the paired film, include `Read Lunara Review` alongside
  IMDb in that row; do not substitute a dossier or an unrelated title match.
  Preserve all three full
  role names and complete arguments. Forecast image, identity and argument
  should form one clear unit without a second painted card.
- Reviews has one continuous index after its lead. Do not split the next four
  records into a companion carousel or reintroduce retired companion labels.
- Public sliders use previous/next arrows and Pause/Play for automatic playback; never render dot, notch or dash pagination. Keep controls in a compact centered row, square 44px arrow targets and a 76px playback button, with the same navy/gold treatment. Preserve swipe, keyboard access, user pause and reduced-motion behavior. Non-automatic galleries have arrows only. Site Studio previews use the public renderer; saved legacy showDots values cannot reinstate pagination.
- The footer uses compact wrapping directory rows, not tall navigation towers.
  Keep every saved link, readable group labels, 44px targets and symmetric rows.

## Slider reading pace

- Every enabled public slider uses one shared reading interval: 12 seconds by
  default. Site Studio → Global Design → Slider reading time owns the setting,
  in whole seconds from 8 to 30. Preview, Apply, Discard and History use the
  canonical Global Design option and the normal private-preview path.
- Existing per-slider autoplay on/off choices remain effective. Legacy positive
  interval values only indicate autoplay is enabled; they cannot override the
  shared pace. Retired timing controls must not return in new editors.
- Keep explicit Pause/Play, pointer/focus pause, reduced-motion opt-out and
  manual arrows/swipe. Pause remains in effect until the visitor resumes it.
- Render the shared head metadata synchronously, before any carousel mounts.
  Theme owns this contract for plugin-provided or older saved slider markup.

## Surfaces and type

- Public frames and controls have square corners in every responsive and
  interaction state: artwork, cards, menus, dialogs, buttons, inputs, filters,
  badges and pagination. Update each owning declaration and late PHP emitter;
  do not append a global radius override. Saved legacy surface-radius and media
  rounded attributes cannot restore curves. Their obsolete editor controls retire
  without deleting stored content. The Lunara brand seal, its crescent mark and
  relationship-diagram nodes/arcs retain their meaningful circular geometry.
  The third-party carousel loading ring remains a functional progress indicator.
- Verify rendered corners alongside existing browser layout and keyboard tests.
  A CSS edit alone does not prove that saved settings or critical CSS agree.
- This is the site-wide standard, including generic pages, ordinary posts,
  taxonomy/author/date/search results and future content, not a set of selected
  gallery layouts. `theme.json` and the base scaffold own native block defaults;
  `inc/public-geometry.php` adapts legacy inline corners and Site Editor values
  at read time without changing saved articles or other visual settings. Public
  CSS policy checks discover new stylesheets automatically in Theme and Ledger.
  See `docs/PUBLIC-GEOMETRY.md` for the complete ownership and coverage map.
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

Treat each correction as a complete replacement across its active consumers.
Trace the owning source, shared renderers, defaults, saved-content variants,
editor controls and preview builders. Remove superseded visible wording,
redundant active output and competing definitions as part of the fix. Give each
shared behavior a clear owner. Keep necessary legacy-input compatibility at the
read boundary; it must not reintroduce old public wording. Refresh only affected
previews and checks, followed by required CI on the final commit.

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
| Single Review geometry and saved presentation | `assets/css/lunara-review-single.css`, Review Studio emitter in `inc/frontend.php` |
| Journal archive | `archive-journal.php`, `inc/journal-archive-studio.php`, `assets/css/lunara-journal-archive.css`, `inc/journal-archive-critical.php` |
| Footer and shared public inline output | `inc/frontend.php`, `assets/css/lunara-public-guardrails.css` |
| Menu and search | `inc/header-command.php`, `inc/live-search.php`, scoped public CSS |
| Oscars landing page | `page-oscars.php`, `assets/css/lunara-oscars-portal.css`, `inc/oscars-portal-critical.php`; shared shell must not restore the retired split masthead |
| Oscar records and explorer | Ledger plugin templates and public CSS; theme `assets/css/oscars.css` owns integration only |

The Oscars shell owns page framing and shared controls; it must not restate the
Ledger's profile, record-row, filmography or ceremony composition. Dossier Studio
owns saved media fit, focal point, aspect, responsive size limits and density.
Verify those late-emitted choices together with the real Ledger styles and the
early structural seed. Standalone plugin fixtures cannot prove that cascade.

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
