# Lunara repository design and release alignment

Effective 2026-10-15; merge authority updated 2026-10-17. This document records
ownership; it does not authorize a deployment. The identical AGENTS.md in the
seven active website repositories is the working agreement. All ten Lunara
repositories follow [agent merge authority](AGENT-MERGE-POLICY.md); the three
secondary repositories have scoped agreements. Theme DESIGN.md and
PUBLIC-GEOMETRY.md are the single
public design contract. Resolve these files in the Theme checkout or
[Theme repository](https://github.com/Lunara-Film/lunara-theme-blocks).

## Active website repositories

| Repository | Public responsibility | Private boundary |
| --- | --- | --- |
| lunara-theme-blocks | Page composition, typography/tokens, shell, native blocks, saved-content adaptation and shared public geometry | Editor configuration UI; public-content previews still obey the contract |
| lunara-plugin-oscars-ledger | Oscars hub, Explorer, ceremony, category, entity and tracker presentation; its public CSS policy and computed browser checks | Ledger administration and data tools |
| lunara-plugin-dispatch | Journal feed (grid/list/rail), spotlight and lanes; its public CSS policy and rendered block geometry checks | Ingestion, provider jobs and editorial settings |
| lunara-plugin-journal-foundation | Journal model and validation; emitted visitor content follows Theme presentation | Journal Desk, Control Tower and control-plane administration |
| lunara-plugin-core | Canonical content/services used by Theme; any new public output follows Theme | Review Workspace, Image Studio, Debrief Studio, import and editorial administration |
| lunara-plugin-imdb-guard | Identity/link validation; any future public output follows Theme | Administrative status/settings output |
| lunara-plugin-ai-assistant-classic | Content suggestions for publication use semantic markup and existing Theme styling | Classic Editor meta box, private suggestions and settings |

Private UI is exempt from public square geometry. The exemption follows the
rendering destination, not a filename: a public preview or generated article
cannot use it to bring rounded panels or a second design system onto the site.
No private-tool restyling is included in this alignment.

AI Assistant Classic contributors must preserve semantic headings, paragraphs,
lists and links. Generated public content must inherit Theme typography and
layout; do not ship style tags, inline layout/radius rules, custom font imports
or new layout stylesheets with a suggestion. Existing provider settings and
stored prompts are not rewritten by this documentation change. The contract
is a contribution/review requirement, not a claim of automatic model-output
sanitization or a guarantee about previously generated articles.

## Manual release stages

1. Prepare the PR and final candidate evidence. Reuse unchanged accepted checks;
   repeat affected checks after a substantive change, then required repository CI.
2. The integrating agent merges the completed, verified PR under the shared
   merge policy, without another routine merge approval. Confirm the exact
   repository/base/head and passing checks. A merge is not a deployment. Keep
   automatic deployment off.
3. Record the merged commit and explicit release instruction. Inspect the
   existing deployment connection/destination instead of creating another one.
4. Deploy runtime plugin changes before the Theme through the existing manual
   deployment path. Documentation/test-only alignment does not need a version
   bump or a separate production deployment.
5. Confirm deployment completion and exact live identity, then check affected
   public behavior. Local fixtures and green CI do not prove live acceptance.
6. A rollback requires the reviewed revert to be merged and deployed as well.

These are review/merge/deploy/acceptance stages, not a request to create a
staging site. No cache clears, production settings changes or auto-deploy
reenablement are part of this alignment.

## Secondary repository classification

Read-only source, workflow and GitHub deployment-record audit on 2026-10-15:

| Repository and inspected main commit | Classification | Policy decision |
| --- | --- | --- |
| [academy-awards-sql](https://github.com/Lunara-Film/academy-awards-sql/tree/985bcb870ff413d406903cb5b354f57583d3bd3a) | Data-only PostgreSQL schema, records and views. README explicitly separates website/Ledger; no frontend files or deploy workflow in the tree. | No frontend policy or runtime change needed. Consuming presentation repositories own styling. |
| [lunara-engine](https://github.com/Lunara-Film/lunara-engine/tree/e3c0bafd3a412eb830dd2157b5ac4f167339f0ec) | Standalone Streamlit recommendation UI with CSV data and TMDB calls. It is a visual application, not backend-only. No WordPress integration or deploy workflow found. | Not an established lunarafilm.com presentation owner. No frontend changes applied. Verify hosting/public audience before adopting it into the active website contract. |
| [lunara-world-site](https://github.com/Lunara-Film/lunara-world-site/tree/d7219dbb29c3e053fa2704abc6817685bf858ad3) | Earlier combined WordPress theme and Oscars plugin. Contains frontend templates/CSS. README still calls them active; its workspace pointers refer to March-era sources. Workflow performs lint only. | Legacy visual source outside the current seven-repository release set; actual host connection is unverified. Do not label it backend-only, delete/archive it, deploy it or retrofit its frontend as part of this alignment. Verify host routing before any future reuse. |

All three GitHub deployment queries returned empty arrays. That proves only
that GitHub exposed no deployment records, not that a third-party host is
disconnected. Host-side activity for Engine/World remains unverified and is
recorded rather than guessed. They are not counted as newly validated public
consumers. No secondary repository or hosting configuration was mutated in that
audit. The 2026-10-17 policy update adds scoped contributor agreements to all
three secondary repositories without changing their runtime, hosting or data.
World also labels obsolete deployment instructions as historical. Merge
authority does not add these repositories to the active WordPress release set.

## Regression ownership

Theme and Ledger retain their existing public CSS policy and computed browser
checks. Dispatch adds a browser-parsed CSS policy for all files under assets/css
(including future files) plus real block-renderer fixtures at phone/desktop
widths and focus/hover states. The dependency is test-only and excluded from
deployment. Every new public output owner must add relevant behavior coverage;
documentation-only repositories do not receive placeholder tests. The new
Dispatch fixture exposed a long-headline overflow at the narrow spotlight
column; Dispatch 3.4.2 adds wrapping in its owning title rule and retains the
viewport check. This small CSS fix is a runtime release; the agreement changes
in the other repositories are documentation-only.

Historical session logs remain historical evidence. Current AGENTS.md and this
contract supersede old merge-equals-live statements without rewriting history.
