# Single Review layout ownership

The September 21, 2026 Customizer block headed `LUNARA review layout fix` is a
temporary layout patch. The Theme candidate absorbs its behavior so it can be
removed from Additional CSS at release.

## Canonical behavior

| Behavior | Owner |
|---|---|
| Centered 760px title/reading frame and below-article rail; zero body top padding and hero bottom margin | `assets/css/lunara-review-single.css` |
| Two equal record cards and compatible legacy navigation actions above 640px; one column at 640px and below | Same Review route stylesheet |
| Explicit visible record count so hidden metadata cannot leave a lone card in the left track | `single-review.php` and the Review route stylesheet |
| No duplicate body separator; genuinely empty content paragraphs hidden | Same Review route stylesheet |
| Centered 860px pairing section; one complete film argument per row; 170px poster beside the text | Same Review route stylesheet |
| Phone pairing stack, full-width poster capped at 340px high | Same Review route stylesheet |
| Saved Review presentation choices without restoring a side rail | `lunara_output_review_single_studio_css()` in `inc/frontend.php` |
| Full Theme Echo, Counter Program and Career Context labels, complete notes and links | Existing canonical and legacy Debrief renderers; known short saved aliases expand at the display boundary |

The shared pairing stylesheet still owns Home/archive/block defaults. Do not
change those other compositions to solve a single Review defect. Do not restore
the retired high-specificity phone counterpatch or append another catch-all
override. `:empty` intentionally does not remove whitespace-only paragraphs or
paragraphs containing authored elements.

The current Review template emits conditional Oscar Ledger and Film Dossier
links below the article. The old Browse Reviews / Director Archive action row
is no longer emitted by this template; its existing class contract remains
compatible for legacy Review markup. Do not add a second navigation row just to
reproduce obsolete markup. The phone artwork cap uses `cover`, so a portrait
may crop when its full 2:3 frame would exceed 340px in height.

## Release and retirement

1. Require passing CI on the final Theme candidate and approve the visual result.
   Follow the existing plugin-before-Theme deployment order.
2. Read and preserve the current saved Additional CSS before editing it. The
   local public-HTML snapshot is evidence of delivered CSS, not a substitute for
   the saved editor value. Reconcile any change since that snapshot.
3. Deploy and verify the intended Theme build before removing the old patch.
4. Remove only the block beginning with the September 21 `LUNARA review layout
   fix` comment, through its associated `@media (max-width:640px)` closing brace.
   That includes the later "One rule under the header, not two" correction.
   Preserve all unrelated Additional CSS exactly. Do not clear the whole field.
5. Confirm the named block is absent from public output and the actual Review
   remains centered at phone and desktop widths, with full pairing titles and
   complete arguments, correct navigation and no extra separator. Confirm the
   deployed build in the same bounded acceptance pass.

The redundant 20px hero bottom margin in the old block is immediately overridden
by its later 0 value. Only the effective zero-margin behavior belongs in the
Theme. Removal is a release action; a local source correction does not itself
change or delete the saved Customizer CSS.

## Enduring verification

The existing article layout fixture must execute the real late Review Studio
emitter as well as linked styles. Browser checks cover Review widths including
640/641px, absent/one/two rail records, artwork and missing-art cases, full role
names and complete arguments, with no Customizer rescue CSS. A bounded overlap
check retains the old block to verify the deploy-before-removal transition.
Existing saved presentation/media controls keep their behavioral coverage.

Refresh affected Review previews and run required CI on the final source. Do
not repeat the whole-site audit or unrelated capture sets for this route fix.
