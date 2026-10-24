<?php
/** Actual archive template: headings, meaningful labels, destinations, fallbacks. */
require __DIR__ . '/journal-public-fixture.php';
$checks = 0;
function jp_assert( $ok, $message ) { global $checks; ++$checks; if ( ! $ok ) { throw new RuntimeException( $message ); } }
function jp_dom( $html ) { $doc = new DOMDocument(); @$doc->loadHTML( '<?xml encoding="UTF-8">' . $html ); return new DOMXPath( $doc ); }
$html = jp_render();
$dom = jp_dom( $html );
jp_assert( 1 === $dom->query( '//h1' )->length && 'Lunara Journal' === $dom->query( '//h1' )->item(0)->textContent, 'The actual archive retains one named page heading.' );
jp_assert( 8 === $dom->query( '//h2[@class="lunara-review-grid-title"]' )->length, 'Every archive story uses the next heading level; no hidden toolbar heading is needed.' );
jp_assert( 3 === $dom->query( '//h3[@class="lunara-journal-archive-retention-title"]' )->length, 'All three continuation routes remain under the section heading.' );
jp_assert( 0 === $dom->query( '//h2[@class="lunara-footer-col-heading"]' )->length, 'The standard footer omits directory headings.' );
$footer_links = $dom->query( '//footer//nav[@aria-label="Footer navigation"]//a' );
$footer_labels = array();
foreach ( $footer_links as $link ) { $footer_labels[] = trim( $link->textContent ); }
jp_assert( array( 'Reviews', 'Journal', 'Oscars', 'Debrief', 'Contact' ) === $footer_labels, 'The Journal renders the canonical five-link footer in its approved order.' );
jp_assert( home_url( '/debrief/' ) === $footer_links->item(3)->getAttribute( 'href' ), 'The shared Debrief link reaches the public archive.' );
jp_assert( 1 === $dom->query( '//footer//a[@href="' . home_url( '/privacy-policy/' ) . '"]' )->length, 'A configured Privacy destination remains available once.' );
jp_assert( 0 === $dom->query( '//a//a' )->length, 'Credits and trailer metadata never create nested links.' );
jp_assert( 0 === $dom->query( '//*[contains(@class,"is-source")]' )->length, 'Ordinary Journal stories omit visual-source labels despite stored provenance.' );
$GLOBALS['jp_meta'][2]['journal_kicker'] = 'Exclusive';
$exclusive_dom = jp_dom( jp_render() );
jp_assert( 1 === $exclusive_dom->query( '//*[contains(@class,"is-source")]' )->length, 'An explicitly Exclusive Journal file keeps its visual-source label.' );
unset( $GLOBALS['jp_meta'][2]['journal_kicker'] );
$saved_retention = $GLOBALS['jp_config']['retention'];
$GLOBALS['jp_config']['retention'][0]['image_id'] = 101;
$GLOBALS['jp_config']['retention'][0]['image_credit'] = 'Retained photographer';
$GLOBALS['jp_config']['retention'][0]['image_source'] = 'Retained studio';
$GLOBALS['jp_config']['retention'][0]['image_source_url'] = 'https://studio.example/retention/';
jp_assert( false === strpos( jp_render(), 'Retained photographer' ), 'Archive retention keeps ordinary image attribution private.' );
$GLOBALS['jp_meta'][101]['_lunara_journal_public_credit_required'] = '1';
jp_assert( false !== strpos( jp_render(), 'Retained photographer' ), 'Retention honors an explicit attachment attribution requirement.' );
unset( $GLOBALS['jp_meta'][101]['_lunara_journal_public_credit_required'] );
$GLOBALS['jp_config']['retention'][0]['destination'] = 'latest';
$GLOBALS['jp_meta'][1]['journal_kicker'] = 'Exclusive';
jp_assert( false !== strpos( jp_render(), 'Retained photographer' ), 'A latest-file retention card can use its explicit Exclusive Journal identity.' );
$GLOBALS['jp_config']['retention'][0]['destination'] = 'custom';
$GLOBALS['jp_config']['retention'][0]['url'] = 'https://example.test/unrelated/';
jp_assert( false === strpos( jp_render(), 'Retained photographer' ), 'A custom retention destination cannot borrow the latest Journal file exception.' );
unset( $GLOBALS['jp_meta'][1]['journal_kicker'] );
$GLOBALS['jp_config']['retention'] = $saved_retention;
jp_assert( 1 === $dom->query( '//span[contains(@class,"lunara-trailer-card-badge")]' )->length, 'The trailer instance remains on its original story.' );
jp_assert( 1 === $dom->query( '//article[contains(@class,"is-text-brief")]' )->length, 'The fifth no-art story renders without a broken or fabricated image.' );
jp_assert( 0 === $dom->query( '//p[contains(@class,"kicker")]' )->length, 'Default archive eyebrow stacks are absent from the actual template.' );
jp_assert( 1 === $dom->query( '//nav[@aria-label="Sort journal archive"]//a[@aria-current="page"]' )->length, 'One named sort choice is current.' );
jp_assert( 1 === $dom->query( '//nav[contains(@class,"lunara-journal-archive-filters")]//a[@aria-current="page"]' )->length, 'The active all-items filter announces its current state.' );
if ( in_array( '--fixture', $argv ?? array(), true ) ) { echo $html; exit( 0 ); }
$GLOBALS['jp_mods']['lunara_footer_col1_heading'] = 'Keep reading';
$grouped_footer = jp_dom( jp_render() )->query( '//footer//h2' );
jp_assert( 1 === $grouped_footer->length && 'Keep reading' === $grouped_footer->item(0)->textContent, 'An optional saved footer heading retains its meaningful heading level on the Journal.' );
unset( $GLOBALS['jp_mods']['lunara_footer_col1_heading'] );
jp_assert( 1 === $dom->query( '//section[contains(@class,"is-editorial-edition")]' )->length, 'The newest main archive receives the editorial composition.' );
foreach ( array( 'date_asc', 'modified_desc' ) as $sort ) {
    $GLOBALS['jp_sort'] = $sort;
    $sorted = jp_dom( jp_render() );
    jp_assert( 0 === $sorted->query( '//*[contains(@class,"is-editorial-edition") or contains(@class,"is-lead")]' )->length, 'Changing sort removes the lead hierarchy from actual public markup.' );
    jp_assert( 8 === $sorted->query( '//h2[@class="lunara-review-grid-title"]' )->length, 'Sorting preserves all story destinations.' );
}
unset( $GLOBALS['jp_sort'] );
$GLOBALS['jp_paged'] = true;
jp_assert( 0 === jp_dom( jp_render() )->query( '//*[contains(@class,"is-editorial-edition") or contains(@class,"is-lead")]' )->length, 'Later pages render a uniform archive.' );
unset( $GLOBALS['jp_paged'] );
$GLOBALS['jp_config']['kicker'] = 'Dalton’s dispatches';
$GLOBALS['jp_config']['labels']['toolbar_kicker'] = 'Recently revisited';
$GLOBALS['jp_config']['labels']['lead_kicker'] = 'An editorial selection';
$GLOBALS['jp_config']['labels']['retention_kicker'] = 'From this story to the next';
$custom = jp_render();
foreach ( array( 'Dalton’s dispatches', 'Recently revisited', 'An editorial selection', 'From this story to the next' ) as $label ) { jp_assert( str_contains( $custom, esc_html( $label ) ), 'Saved custom label survives: ' . $label ); }
jp_assert( strpos( $custom, '</h1>' ) < strpos( $custom, 'Dalton’s dispatches' ), 'Custom framing follows the page title instead of becoming an eyebrow.' );
$GLOBALS['jp_failed_media'] = true;
jp_assert( 8 === jp_dom( jp_render() )->query( '//article[contains(@class,"is-text-brief")]' )->length, 'Every story survives failed media resolution as readable text.' );
$GLOBALS['jp_config']['section_visibility']['hero'] = false;
jp_assert( 1 === jp_dom( jp_render() )->query( '//h1' )->length, 'A hidden visual hero retains the accessible page heading.' );
$GLOBALS['jp_term'] = new WP_Term();
jp_assert( 'Signal' === jp_dom( jp_render() )->query( '//h1' )->item(0)->textContent, 'Taxonomy framing preserves the selected term identity.' );
jp_assert( 0 === jp_dom( jp_render() )->query( '//*[contains(@class,"is-editorial-edition") or contains(@class,"is-lead")]' )->length, 'Taxonomy results render a uniform archive.' );

echo "Journal public semantics: {$checks} checks passed.\n";
