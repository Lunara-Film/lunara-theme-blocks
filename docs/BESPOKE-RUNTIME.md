# Bespoke Lunara runtime implementation

This is the implementation of Dalton's October 11 six-module package-boundary
contract, following the editorial remediation candidates in Theme PR #230 and
Ledger PR #54. It does not replace the original overlay register or erase its
unresolved provenance items. This work is a local candidate until the release
receipts below are filled and the integrating agent merges the reviewed PRs
under the current `AGENTS.md` gates.

## Direction and ownership

The premium result comes from original criticism, film-specific artwork, Tiempos,
deliberate editorial composition and responsive controls. Text and figures render
at their true values immediately. No canvas grain, cursor glow or split-word
headline entrance effects are introduced. WordPress remains the publishing
system; its editable content, saved presentation and URLs remain authoritative.

**Navigation decision:** native document navigation is the production default.
Four current anonymous browser reads (Home, Journal archive, Reviews archive and Oscars)
contain Jetpack/Blocksy executable bundles and inline initializers without an
audited replacement lifecycle. All four therefore fail the optional Swup
eligibility gate. The existing native view transitions and conservative browser
prefetch remain available. The new Swup bundle is not downloaded unless the
explicit `lunara_navigation_enabled` filter enables it. The lightweight shared
lifecycle and all adapted components work independently of that opt-in.

This is an evidence-based performance choice, not a claim that JavaScript page
swaps are already live or faster. Enabling them requires the outstanding owner
contracts and measured benefit. No Stats or other third-party feature is removed
merely to make the gate pass.

The attachment's code examples describe intent. Current source owns schemas,
renderers and deploy boundaries. In particular, Ledger uses its canonical SQL
facts Read API, not an invented `ledger_entry` CPT query, and Debrief uses the
existing three-role contract rather than invented `pair_*` fields.

## Ordered implementation checklist

| Order / effort | Root cause and concrete work | Owner | Verification and regression rule |
|---|---|---|---|
| 1 — Large, 2–3 days | Route components initialize once and retain listeners, timers, observers or carousel instances. Add a page lifecycle registry, adapt the audited Theme and Ledger public components, and package navigation only as a disabled opt-in. Await styles, update route configuration/head/body, preserve history, focus and anchors; native navigation remains the supported default. | Theme runtime; each plugin owns its island; Jetpack/Blocksy retain unaudited initializers | Repeated mount/destroy, late async results, real carousel remounts, back/forward, hash/focus, reduced motion, new route assets, unsupported script fallback. Every new public executable asset needs an explicit lifecycle contract before navigation can intercept it. The four sampled live routes are currently ineligible for Swup. |
| 2 — Large, 1–2 days | Explorer filtering rebuilds canonical HTML for every request and lacks a disposal contract. Retain SSR, filters, facet counts, pagination, citations and deep links. Publish bounded versioned JSON snapshots of the same regions through the existing cache warmer; use them opportunistically, with the normal fragment endpoint as fallback. | Ledger Read API / Page Store / Explorer | Atomic publication, concurrent-edit fencing, stale/missing/corrupt data, permissions, uploads locations, cache freshness, complete output parity, filter history and lifecycle. Failed builds must preserve usable prior data and never publish empty replacement records. |
| 3 — Medium, 1 day | Automated HTML can carry inline presentation into a carefully controlled theme. Sanitize only explicit ingestion entry points through Foundation; Dispatch reuses that contract. Keep semantic content, source identity, draft policy, metadata, idempotency and readback verification. | Foundation bridge; Dispatch Post Builder | Semantic DOM fixtures, unsafe content handling and existing ingestion/idempotency tests. No global save hook that rewrites human edits. No guessed model name or fabricated verification timestamp. |
| 4 — Small, half day | The illustrative contract differs from existing canonical Debrief and artwork APIs. Retain the implemented three-role validation, public-renderer adoption gate, image-off/custom choices, native dimensions and portrait/backdrop geometry. | Core data; IMDb Guard integrity; Theme rendering | Reuse the existing canonical contract and artwork tests. Exercise both consumers when a contract changes. Do not migrate stored copy or artwork merely to match example code. |
| 5 — Medium, half–one day | Broad asset removal can break block composition; duplicate animation delivery adds maintenance. Keep actual route-based assets, correct Home trimming to retain styles for unknown/core/third-party blocks, keep deterministic shell generation and pin the navigation bundle build. | Theme live enqueue functions and build tools | Known-section versus hybrid/preview behavior; build reproducibility; no parent/GSAP/global-style blanket deletion. Versioned assets and explicit script ownership prevent stale or uncontrolled delivery. |
| 6 — Medium, half–one day | Repeated unbounded audits waste time and still confuse local, merged and live results. Record one scoped implementation batch, required CI, then one versioned public acceptance pass after release. | Integrating agent owns verified merges; deployment is separately authorized | Failures trigger only their affected checks. No automatic score-chasing rescans. Carry original findings and open blockers forward unchanged until evidence closes them. |

Estimates describe engineering effort, not elapsed agent time, and overlap across
parallel work. Existing implemented contracts should be verified, not rebuilt.

## Release dependencies and stopping rule

1. Keep visual cleanup PRs independently reviewable. Theme and Ledger runtime
   follow-ups stack on those branches until their bases merge; Foundation and
   Dispatch changes target their current `main` branches.
2. Foundation's shared sanitizer must be available before Dispatch relies on it.
   Newer Dispatch retains a guarded fallback if Foundation has not updated.
3. Plugins deploy before Theme through the existing manual deployment path.
   Automatic deployment remains off. Agents own verified merges; deployment
   requires a separate explicit instruction under the current `AGENTS.md`.
   No production changes are authorized merely by passing a local test.
4. Confirm actual plugin/theme versions, server/CDN JSON compression and cache
   delivery, licensed-font behavior, route transitions and a complete Explorer
   filter cycle once on the released site. A `.gz` file alone does not prove
   compression. Fixture tests do not prove a production speed improvement.
5. Record baseline and candidate timing under the same conditions before
   claiming faster navigation. The enhancement must preserve or improve the
   visitor path; unsupported pages retain working native navigation.
6. Stop after the required passing acceptance evidence. Reopen only for a new
   change or observed regression. The original unattributed contour/clipping
   findings remain explicit evidence dependencies, not silently waived items.

## Candidate receipts

Candidate versions: Theme 3.2.105, Ledger 2.8.23, Foundation 1.4.1, Dispatch
3.4.1. Core and IMDb Guard are unchanged because their canonical contracts
already provide the required ownership and artwork behavior.

Focused local evidence at integration:

- Theme: lifecycle 11, delivery gate 61, optional navigation 34, actual component
  disposal/remount/failure recovery 31, Debrief lifecycle 49, Home style eligibility
  24, header 61, search 174, carousel lifecycle 57, Home carousels 118,
  Oscar Facts 26 and Reviews rails 15 checks passed. Bundle/notices are reproducible.
- The default lifecycle is 1,860 bytes gzip, bounded by a 2.5 KB compressed
  delivery test. The optional bundle is 11,839 bytes gzip and is not requested
  with the default gate. These are artifact sizes, not measured live timings.
- Foundation: DOM fallback 66 and native WordPress 7.1.3 HTML API/KSES 70
  assertions pass. Required CI passed on PHP 7.4, 8.2 and 8.3 at commit b8fb627.
  Dispatch required CI passed at e301fcc. PRs: Foundation #27, Dispatch #16.
- Ledger: publication health and generation-race checks, 19 Explorer lifecycle
  checks and 10 motion checks pass locally. The first required CI run identified
  the ceremony script's existing source-size budget as the remaining integration
  failure; its focused correction and final receipts belong to Ledger PR #55.

The consolidated final CI/commit receipts are retained in this chat's
`lunara-remediation-plan/implementation/architecture-review` artifact. Required
Theme CI and final Ledger CI are release requirements, not implied by the local
results above. Original overlay closure and deployed acceptance remain separate.
No production deployment has occurred in this task.
