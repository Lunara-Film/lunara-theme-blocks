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
jp_assert( 3 === $dom->query( '//h2[@class="lunara-footer-col-heading"]' )->length, 'Footer navigation groups retain meaningful headings without an h4 jump.' );
jp_assert( 0 === $dom->query( '//a//a' )->length, 'Credits and trailer metadata never create nested links.' );
jp_assert( 5 === $dom->query( '//*[contains(@class,"is-source")]' )->length, 'All five supplied visual-source instances remain visible.' );
jp_assert( 1 === $dom->query( '//span[contains(@class,"lunara-trailer-card-badge")]' )->length, 'The trailer instance remains on its original story.' );
jp_assert( 1 === $dom->query( '//article[contains(@class,"is-text-brief")]' )->length, 'The fifth no-art story renders without a broken or fabricated image.' );
jp_assert( 0 === $dom->query( '//p[contains(@class,"kicker")]' )->length, 'Default archive eyebrow stacks are absent from the actual template.' );
jp_assert( 1 === $dom->query( '//nav[@aria-label="Sort journal archive"]//a[@aria-current="page"]' )->length, 'One named sort choice is current.' );
jp_assert( 1 === $dom->query( '//nav[contains(@class,"lunara-journal-archive-filters")]//a[@aria-current="page"]' )->length, 'The active all-items filter announces its current state.' );
if ( in_array( '--fixture', $argv ?? array(), true ) ) { echo $html; exit( 0 ); }
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
echo "Journal public semantics: {$checks} checks passed.\n";
