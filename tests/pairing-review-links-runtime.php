<?php
/** Real shared, canonical and curated renderers with an isolated review lookup. */
require __DIR__ . '/debrief-heading-runtime.php';

function lunara_get_review_permalink_by_imdb_title_id( $tt, $exclude = 0 ) {
    $GLOBALS['pair_review_lookups'][] = array( $tt, $exclude );
    return $GLOBALS['pair_review_urls'][ $tt ] ?? '';
}
debrief_heading_load_function( 'lunara_pair_review_link' );
$GLOBALS['pair_review_urls'] = array( 'tt0000301' => 'https://example.com/reviews/paired-film/' );
$data = lunara_parse_pair_it_with_value( 'Relational film | tt0000301', 301 );
$data['tt'] = 'tt0000301';
$data['title_href_type'] = 'entity';
$data['title_href'] = 'https://example.com/movies/paired-film/';
$data['imdb_href'] = 'https://www.imdb.com/title/tt0000301/';
$checks = 0;

foreach ( array( 'matched', 'unmatched' ) as $scenario ) {
    if ( 'unmatched' === $scenario ) { $GLOBALS['pair_review_urls'] = array(); }
    $rows = array();
    foreach ( array( 'theme_echo', 'counter_program', 'career_context' ) as $role ) {
        $GLOBALS['heading_relations'][ $role ] = $data;
        $rows[] = array( 'slug' => $role, 'label' => $role, 'data' => $data );
    }
    $outputs = array(
        'Home and Reviews' => lunara_render_pair_it_with_cards( 301 ),
        'public Debrief' => lunara_debrief_render_pair_card_group( 301, $rows ),
        'curated block' => lunara_pairing_showcase_build_card( $data, 'Theme Echo', 'theme', '', '', 301 ),
    );
    foreach ( $outputs as $surface => $html ) {
        $dom = new DOMDocument();
        @$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
        $xpath = new DOMXPath( $dom );
        $footers = $xpath->query( '//*[contains(concat(" ",normalize-space(@class)," ")," lunara-pair-card-chips ")]' );
        foreach ( $footers as $footer ) {
            $reviews = $xpath->query( './/a[contains(@class,"lunara-pair-card-chip--review")]', $footer );
            debrief_heading_assert( ( 'matched' === $scenario ? 1 : 0 ) === $reviews->length, $surface . ': only resolved published reviews get a source-row link.' ); ++$checks;
            debrief_heading_assert( 1 === $xpath->query( './/a[contains(@class,"lunara-pair-card-chip--imdb")]', $footer )->length, $surface . ': IMDb remains beside the review destination.' ); ++$checks;
            if ( $reviews->length ) {
                $link = $reviews->item( 0 );
                debrief_heading_assert( 'Read Lunara Film Review' === $link->textContent && 'https://example.com/reviews/paired-film/' === $link->getAttribute( 'href' ), $surface . ': clear label and correct review URL.' ); ++$checks;
                debrief_heading_assert( ! $link->hasAttribute( 'target' ) && ! $link->hasAttribute( 'rel' ), $surface . ': internal review uses ordinary same-tab navigation.' ); ++$checks;
            }
        }
    }
}
// A legacy resolved review URL can have a custom permalink structure.
$data['title_href_type'] = 'review';
$data['title_href'] = 'https://example.com/criticism/custom-path/';
$GLOBALS['pair_review_lookups'] = array();
debrief_heading_assert( false !== strpos( lunara_pair_review_link( $data, 301 ), 'https://example.com/criticism/custom-path/' ), 'Reuse the already resolved review destination.' ); ++$checks;
debrief_heading_assert( array() === $GLOBALS['pair_review_lookups'], 'A resolved review does not incur a duplicate database lookup.' ); ++$checks;
echo "Pairing review destinations: {$checks} checks passed.\n";
