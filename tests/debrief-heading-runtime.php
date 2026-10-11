<?php
/** Exercise the real canonical and fallback Debrief public renderers. */
ob_start();
require __DIR__ . '/fixtures/debrief-public-renderer-harness.php';
ob_end_clean();
function debrief_heading_assert( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
foreach ( array( $canonical, $poster_fallback ) as $rendered ) {
    $document = new DOMDocument();
    @$document->loadHTML( '<?xml encoding="UTF-8"><main><h1>Review title</h1>' . $rendered['signature_html'] . $rendered['pairings_html'] . '</main>' );
    $xpath = new DOMXPath( $document );
    debrief_heading_assert( 1 === $xpath->query( '//h1' )->length, 'The review owns the only h1.' );
    debrief_heading_assert( 1 === $xpath->query( '//h2[@class="lunara-debrief-heading"]' )->length, 'Debrief is a direct section of the review.' );
    debrief_heading_assert( 1 === $xpath->query( '//h2[@class="lunara-pair-cards-title"]' )->length, 'Pairings have their own h2 section.' );
    debrief_heading_assert( 3 === $xpath->query( '//h3[@class="lunara-pair-card-title"]' )->length, 'Each of the three films is subordinate to the pairing section.' );
    debrief_heading_assert( 0 === $xpath->query( '//h4' )->length, 'The generated heading outline has no artificial h4 jump.' );
}
echo "Debrief heading hierarchy: 10 checks passed.\n";
