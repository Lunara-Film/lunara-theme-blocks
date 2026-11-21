# Parking list

Ideas that are not defects. Each needs three lines and Dalton's yes before any
agent builds it; features batch into at most one release a quarter. Newest
first.

## Living pillar desk — Dalton, 2026-11-21

- Visitor gains: Journal, Reviews, and the Oscars read as one desk that is moving, with the newest fact and a freshness line on each pillar, on the archives and the Oscars portal as well as the Home doors.
- Build cost: one shared nav in the theme, using the posts and ledger facts the site already renders, plus the Home dateline's "Updated today" when news or a review landed today.
- Keep cost: the desk has to stay on the existing type, gold, and surface navy, and the three pages have to keep showing the same three facts.
- Status: Dalton asked for this direction on 2026-11-21, so it is being built now. The quarterly batch rule still stands: Theme 3.2.161, the three-doors masthead, already landed in this quarter (2026-11-07). Holding on `cursor/pillar-desk-0864`. Not merged and not live.

## Journal cadence of two or three held drafts a day — Dalton, 2026-11-21

- Visitor gains: once the voice is signed off and an editor approves the drafts, the Journal stays current with two or three pieces a day across news, reviews, and the Oscars.
- Build cost: a clock and a publication hold in Journal Foundation, and topic selection plus a local composer in Dispatch. Drafts only. No model key is required for the scaffold.
- Keep cost: the hold stays on until the voice is refined, the daily cap stays at three, and a later model call has to drop into the same draft path instead of publishing on its own.
- Status: Dalton asked for the cadence on 2026-11-21 and said the voice still needs refinement, so publication stays held. Holding on `cursor/journal-cadence-hold-0864` and `cursor/dispatch-cadence-drafts-0864`. Not merged and not live. The same quarterly note as the pillar desk applies: a feature release already landed this quarter, and this proceeds because he asked for it now.

## Private Journal app and daily publishing — Dalton, 2026-10-23

- Visitor gains: a consistently active Journal, with at least one post intended for every day; Dalton gains a shared, phone-friendly path from sourced film stories to edited, verified publication.
- Build cost: extend the existing private Journal Desk and Foundation with a film-story feed, shared pitch decisions, publication coverage, and explicitly approved reserve/timed delivery; reuse Dispatch and the existing guarded publishing route.
- Keep cost: maintain bounded feed filtering and delivery behavior tests, approved reserve freshness, and an independently verified server scheduler; no second content database or browser-held publishing credentials.
- Status: implementation explicitly authorized by Dalton's “OK, let’s get after it begin create it go go go.” Foundation 1.5.0 is in progress on `codex/journal-story-wire`. Publication settings and deployment remain separate release checks.

## Supabase Oscars Ledger shortcode — Dalton, 2026-10-15

- Visitor gains: a searchable list of all 12,138 Oscars records (ceremonies 1–98), with ceremony, winners-only and IMDb ID filters, on any page that carries `[lunara_oscars_supabase]`.
- Build cost: built. Standalone plugin `lunara-oscars-supabase` 2.0.0 (a zip, not in a repo), tested in a local WordPress on PHP 8.3 against the live Supabase data. Left: one upload, one activation, one page.
- Keep cost: a second copy of the Oscars data (Supabase `oscars_records`) beside the Ledger's own data, which the yearly import must also update; one third-party script (supabase-js 2.45.4 from jsDelivr) and one external service in the visitor path; a yearly bump of `LUNARA_OSCARS_SB_LATEST_CEREMONY`.
- Status: proposed. Requires Dalton's explicit feature approval; merging the PR that records this proposal does not authorize implementation or deployment.

## Pairing review availability in existing editors — Dalton, 2026-10-15

- Visitor gains: editors can recognize existing criticism and keep accurate companion-film review links.
- Build cost: one shared authenticated read-only indicator for canonical/legacy/curated editors and the existing Site Studio Method inspector; focused PHP/browser checks.
- Keep cost: reuse the public IMDb-to-review resolver and current canonical pairing fields; no new saved state, public scripts or external service.
- Status: explicitly authorized in this chat. Site Studio remains integral; Dalton clarified this is a reminder to preserve the workflow, not a separate Studio redesign.

## Bespoke runtime and publishing boundaries — Dalton, 2026-10-11

- Visitor gains: immediate readable editorial pages, reusable public navigation and a cached Oscars Explorer with complete server-rendered fallbacks.
- Build cost: coordinated Theme, Ledger, Foundation and Dispatch changes; approximately 5–8 engineering days including integration and release acceptance.
- Keep cost: owned component lifecycle, versioned cache contracts and focused behavior tests; one bounded release acceptance pass, with repeat work only for changed code or actual failures.
- Status: authorized by Dalton's request to begin the supplied six-module architecture plan in this chat. Preserve the Impeccable editorial cleanup. Omit canvas grain, cursor effects and headline entrance delays; do not execute the attachment's illustrative SQL/schema or global sanitization hooks literally.

## Rotating-ceremonies hero on `/oscars/` — Dalton, 2026-10-08

- Visitor gains: the portal opens on a different ceremony each visit instead of
  always the latest Best Picture.
- Build cost: a data change in `page-oscars.php` (the hero band and card take
  any backdrop and title); about half a session with a test.
- Keep cost: none beyond the yearly Oscars import.
- Status: parked. Dalton's call whether it is the last release before the freeze.
