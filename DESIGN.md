# Lunara Film: public presentation contract

Lunara Film is a film publication and a living Oscar ledger. Its identity comes
from specific criticism, real film imagery, the Lunara Film wordmark, Tiempos,
midnight navy and restrained gold. This contract applies to the existing visitor
features.

## Identity

- The name is always "Lunara Film" ("LUNARA FILM" in capitals), never "Lunara"
  alone, in any copy a person can see: public pages, admin screens, Site
  Studio, the Control Desk, block editor names and descriptions, aria-labels,
  alt text, titles, meta, JSON-LD, feeds, emails, notices and errors. Dalton,
  2026-10-23: it ends the confusion about the name and brands us as FILM.
  Product names follow it as written: Lunara Film Reviews, the Lunara Film
  Journal, The Lunara Film Oscar Ledger, The Lunara Film Debrief, Lunara Film
  Site Studio, the Lunara Film Control Desk. Never "Lunara Film Film".
- Identifiers stay as they are: function, class, block, option and theme-mod
  names, CSS classes and properties, slugs, URLs, the `lunara-film` text domain
  and font-family names. Code does not rewrite saved settings or stored
  content; a stored legacy value is matched at the read boundary and never
  printed, and anything else stored is changed in Site Studio or the editor.
- CI enforces the rule: `tests/brand-name-runtime.php` reads every PHP string
  and template text, every JavaScript string, CSS strings and `theme.json`,
  and fails on a bare "Lunara". Its allowlist is short, and each entry says
  why it is code, not copy.

## Editorial controls

The Home Oscar Forecast uses a centered previous/next and playback control row.
Do not render one pagination marker per forecast record; all records remain
reachable through the arrows, touch scrolling and keyboard navigation.

Lunara Film Site Studio is a first-class daily workspace. Public presentation changes
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
- The Home masthead reads as a printed program: a dateline (place, month,
  review count), the mark and dek, then "Now Showing": the lead
  story's landscape still, film and take, beside two companion reviews with
  their stills, then the three section doors. Image, label and headline form
  one unit, not a painted card. The former live signal bar and the Journal and
  Ledger signal cards are retired; those sections keep their own modules.
  The wordmark in the masthead is a modest centered mark (at most about 104px
  tall on desktop, 72px on phones, never wider than 560px), not a second
  header; the Brand console logo sizes can shrink it, never enlarge it, and
  the dek always sits below it. On inner pages the site header shows the
  wordmark at its own proportions, contained, never cropped.
- Home is a cover: one wordmark on screen, the masthead's. The site header
  on Home hides its brand and slims to a 64px bar (56px on phones) with
  search and the menu, and the masthead prints no second Search. On desktop,
  where the header stays fixed, its brand returns, at 44px, once the
  masthead's wordmark has scrolled under the bar, and hides again when the
  wordmark is back on screen; on phones the header scrolls away instead. The
  masthead spends as little height as it can before the lead story: the
  dateline on one centered line with middle dots, the mark at its cap, the
  dek, then Now Showing. The lead still starts within 300px of the top of
  the page on desktop and 280px on phones; the Studio paddings can tighten
  that, never loosen it. The hero carousel opener is not a cover (the header
  keeps its wordmark), and `lunara_home_cover_enabled` can turn the cover off.
- Every Home section after the masthead opens on one gold rule with the same
  breathing room: its own one-pixel hairline, or the rule of the panel it
  sits on or follows (below). Its title is set in the signature face at
  regular weight. One rule per seam: no second border from a section's own
  styles and none under the masthead. The Oscar Ledger band keeps its own
  full-bleed rules. The saved Section Order decides on phones as it does on
  desktop; the inline order rules the header prints follow the same map.
  The program's rules (the section hairline, the Running Order and Ledger
  band rules, the lane-head rule) are gold by their own token,
  `--lunara-home-rule`, never the site-wide `--lunara-border` the Customizer
  sets. Every lane head (Journal, Oscar Picks, Oscar Facts, Latest Reviews)
  keeps the Composition rule and the shared section typography and takes the
  program's voice: a saved kicker in the gold spaced caps of the Stills that
  Rhyme kicker, the summary in muted reading type, the action beneath in the
  Running Order's gold italic link. No kicker is added where the heading
  already names the lane, and no second rule runs under a head.
- Home alternates its ground with panels (Dalton, 2026-10-26: the Ledger
  band's colour, in alternating panels). Counting only the sections that
  render, in the saved order, the first section after the opener sits on a
  panel, and panels then alternate with the ground. The Oscar Ledger band is
  always a panel. Two panels never touch, so the section before the band
  stays on the ground. A panel is the band's surface navy
  (`--lunara-bg-secondary`, #0f1d2e) out to both viewport edges, with the
  program's gold rule along its top and bottom edges and equal padding
  inside; the module inside keeps its own frame and gutters. A ground section
  after a panel opens on that panel's rule, with no hairline of its own. The
  last panel runs down to the footer's rule and drops its own, so the footer
  seam keeps one rule too. A plate painted the surface colour takes the
  ground colour inside a panel: no plate disappears into the surface under
  it. `lunara_home_panels_enabled` turns the panels off. Both compositions
  are marked the same way: the section blocks saved in the Home page, which
  the live Home uses, and the Customizer registry. Panels follow the order a
  reader sees, the saved Section Order; a block that is not a Home section
  (an editor's paragraph) prints before the sections and takes no part.
- The Home Debrief showcase (the pairing-desk slot, built as Stills that
  Rhyme) is The Lunara Film Debrief on Home, and Dalton edits it: its kicker,
  headline and supporting line come from the Lunara Film Debrief editor in
  Site Studio, the same fields that head the Pairing Desk on the Reviews
  page, so one edit changes both places. Blank fields fall back to the
  Debrief defaults: "The Lunara Film Debrief", "Every review ends with three
  more films.", and Dalton's line, "Theme Echo, Counter Program, and Career
  Context close every Lunara Film review — the next three movies after the
  credits, each selected individually by me." It reads from the editor's
  Featured Review first (Dalton, 2026-10-26), then the lead story, then the
  newest reviews, and renders the first review that carries all three
  pairings, so the pick in the Debrief editor is what Home features
  (`lunara_home_stills_that_rhyme_review_source` = `lead` puts the lead
  story first).
  The program masthead is Home's opener. The hero carousel is a stored
  alternative (`lunara_home_opener_style` = `cinematic`), not the default, and
  whichever opener renders, the lead story appears once: no second hero below.
- Home runs in the program's order: the masthead, the Debrief showcase, the
  Journal lane, the Oscar Ledger intermission, The Running Order, then the
  Oscar lanes (Dalton, 2026-10-26: the Journal second). The saved Section
  Order (a Studio preset, or a custom order) decides that order in PHP; no
  stylesheet reorders the sections. In block mode the section blocks print
  in that order too, whatever order the Home page stores them in. Phones
  read the same order as desktop.
- The showcase fills the pairing-desk slot with the Debrief as the
  Debrief is: the reviewed film and its three. The editor's kicker, headline
  and line, the source review, then the reviewed
  film's face centered over the three pairings' faces in one row, each under
  its full role name — Theme Echo, Counter Program, Career Context — with
  "Film, Year." and the Method question. Posters (2:3) are the default face,
  three across at every width; on phones up to 480px the row spans the screen
  less its 16px gutters with an 8px gap and the pairing captions read at
  16px, so a long one-word title stays whole from 360px up. Stills (16:9) are the option, taken only when
  the reviewed film and all three pairings have one; the row then stacks on
  phones. A film with no art is a typographic plate on the surface colour,
  never dropped and never an empty frame. The set is never cut to one pair.
  Without a review that carries all three, the legacy Pair It With desk
  renders.
- A film's poster comes from what the site already holds first (a Lunara Film
  review of the film, the movie entity), then the Oscars Ledger, then TMDB. A
  failed lookup is never remembered as "no poster".
- The Running Order: a gold letterspaced kicker, the GT Sectra line
  "Every review, newest first." and an "All N reviews" link over a wall of 2:3
  poster frames, newest first, skipping the three reviews Now Showing carries.
  Each poster is one link: film in GT Sectra Display at 18px (17px on
  phones), take in Tiempos Text italic at 16px, score beneath. A review
  without a poster is a typographic card on the surface color, never an
  empty frame. Six across from 1200px, four from 760px, two on phones, so
  the text has room (Dalton, 2026-10-26: "the text needs to be much more
  legible"); the wall shares the masthead's frame and edges. No ordinal
  numerals.
- The Oscar Ledger band is a full-bleed intermission on the surface navy with
  one gold rule above and one below: the kicker, a GT Sectra headline carrying
  the live ceremony count as digits, a Tiempos record line of Ledger totals,
  and a square gold-ruled door to the portal, beside a strip of five 2:3 Best
  Picture posters, newest ceremony first, each captioned and linking to its
  ceremony. Copy stands beside the strip from 1000px and above it below;
  phones show three posters. A winner without a poster is skipped, never
  framed empty; under three posters the band renders without the strip.
- A Review with landscape artwork opens with that still at the Review Studio
  hero width, above the centered title group, with a one-line caption. Poster
  artwork stays in the reading column. Credits read as one centered line under
  the title ("Directed by …"), never as a side rail. Each pairing row states
  its Method question beneath the role name.
- Single Reviews own a centered 760px reading frame and below-article navigation.
  Pairing rows use a centered 860px frame. Route CSS owns that geometry; late
  Studio output may change supported presentation choices without restoring a
  side rail. Additional CSS must not be required to hold this layout together.
- Journal leads with headlines; dates and useful taxonomy remain readable
  supporting information. Automatic image/source overlays and visual-source
  badges are absent by default across Journal articles, cards and galleries,
  including older entries. The existing Journal Kicker set exactly to
  "Exclusive" enables public visual credit for that post. Explicit required
  attribution metadata also remains effective. Never infer exclusivity from an
  outlet name, source headline or a passing word in article copy. Keep image
  and source provenance privately for verification; preserve descriptive
  captions, image alt text and editorial links in the article. Entries without artwork are intentional text
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
  hierarchy. Footer navigation is a brief closing strip, with Site Studio owning
  its editable links and signature.
- Section headings share `--lunara-type-section`, the display face, 600 weight
  and 1.18 leading. Reading copy uses the body face at regular weight. Embedded
  Ledger headings inherit those roles; do not introduce another Tiempos alias
  or force reading copy to display weight. Preserve the Ledger navigator.
- Home and archive Debrief columns use bounded portrait artwork, lightly ruled
  argument columns and an attached source-link row. When a published
  Lunara Film review resolves for the paired film, include
  `Read Lunara Film Review` alongside IMDb in that row; do not substitute a
  dossier or an unrelated title match.
  Preserve all three full
  role names and complete arguments. Forecast image, identity and argument
  should form one clear unit without a second painted card.
- Reviews has one continuous index after its lead. Do not split the next four
  records into a companion carousel or reintroduce retired companion labels.
- Public sliders use previous/next arrows and Pause/Play for automatic playback; never render dot, notch or dash pagination. Keep controls in a compact centered row, square 44px arrow targets and a 76px playback button, with the same navy/gold treatment. Preserve swipe, keyboard access, user pause and reduced-motion behavior. Non-automatic galleries have arrows only. Site Studio previews use the public renderer; saved legacy showDots values cannot reinstate pagination.
- The standard footer contains Reviews, Journal, Oscars, Debrief and Contact in
  that order, followed by a small copyright signature. It has no default image
  logo, tagline or group headings. Use one centered link row on desktop and
  centered rows of three and two at 480px and below, retaining 44px targets.
  Site Studio owns labels, destinations, visibility, order, Preview/Apply and
  History. Custom saved lists and optional branding/headings remain editable;
  groups without links and empty branding emit no space. With blank headings,
  concatenate saved groups into one list, preserving every enabled destination.
  Configured Privacy appears once, in navigation or beside the signature.
  The shared guardrails stylesheet owns this layout; route emitters and base
  styles must not add footer padding or reconstruct the retired directory.

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
  without deleting stored content. The Lunara Film brand seal, its crescent mark and
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
- Ground: `#0a1520`; Home's panels and the Ledger band: `#0f1d2e`; reading
  text: near-white; accent: `#c9a961` or `#e0c481`.
  Saved valid color choices must keep their intended semantics and contrast.
- Tiempos Headline carries editorial headings; Tiempos Text carries reading.
  GT Sectra Display (the Display optical size) carries the film name in a
  Review title, Review pull quotes and Pair It With film titles (Dalton,
  2026-10-21). The take after the title's dash reads in Tiempos Text italic.
  Licensed font files stay in `/wp-content/uploads/lunara-fonts/v1/`, outside Git.
  Font fallback must preserve readable geometry.
- Ordinary reading/card copy is normally 16–18px; functional metadata and controls
  are at least 13px. These are house quality rules, not claims about WCAG pixel
  requirements. Body text uses normal tracking and ordinary case.
- Review text on Home is reading copy: a take or a Method question reads at
  16px or more in the near-white take colour (`--lunara-home-take`, about 13:1
  on the ground), never in muted grey, and a film's name in a caption at 17px
  or more.
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
