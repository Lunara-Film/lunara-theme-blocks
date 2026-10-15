# Publication-wide geometry standard

Square structural corners are the default for the entire Lunara website and all
future public components. This rule is independent of which pages appear in a
review gallery. Do not introduce per-page opt-outs or blanket important resets.

| Surface | Owner / enforcement |
| --- | --- |
| Home, Journal, Reviews, Debrief, their article and detail pages | Route CSS and shared renderers; computed component tests |
| About, Contact, Editorial Policy, Privacy and other ordinary pages | Generic page shell, base scaffold and WordPress content adapter |
| Ordinary posts and category/tag/author/date archives | Shared template styles, base scaffold and content adapter |
| Journal section/topic/type and director taxonomy pages | Shared archive/template styles and native content defaults |
| Movie/person archives and dossiers | Entity styles and matching first-paint seed; browser parity check |
| Search, empty results and 404 recovery | Shared controls and recovery renderers; no-JS browser checks |
| Oscar hub, Explorer, ceremony, category, film, person, company and tracker | Ledger public CSS plus Theme integration and critical styles |
| Menus, search overlay, footer, carousels, galleries, trailer controls | Owning shared component CSS/PHP; focused/open/mobile tests |
| Existing saved block or classic content | Read-time inline radius adaptation; no database rewrite |
| New WordPress blocks and saved Site Editor overrides | theme.json, block registration and user global-style filter |
| Future public CSS | Browser-parsed policy test automatically includes new assets/css files |

Native controls and structural blocks use zero radius. Existing inline physical
and logical corner properties become zero when rendered. Other style properties,
image fit/focal points, links, copy, classes and stored post content are preserved.
The old rounded image option is retired in registration and legacy output.
Private preview/server-rendered block content follows the same adapter.

Explicit exceptions are the Lunara brand seal/crescent, relationship graph nodes,
award SVG arcs and the functional loading ring. An exception is semantic, not a
shortcut for an ordinary card, badge, avatar frame or button. New exceptions
require an explicit change to this design contract and its checks.

The policy test is a linter using the browser CSS parser, not a source-text pin.
It rejects new nonzero physical/logical radius declarations, responsive/hover
rules and radius tokens; mutation cases verify rejection. Existing computed
tests verify the resulting cascade. WordPress HTML API compatibility is also
checked locally against the real parser; portable CI uses a documented tag API
seam and tests the real normalization functions. Fixtures are not a substitute
for post-deployment WordPress acceptance.

WordPress admin/private editorial workspaces are outside the public presentation
surface. Their controls do not style website pages. Public content previews and
editor shape choices are covered because they can affect the website.
