<?php
/** Exercise the real canonical and fallback Debrief public renderers. */
ob_start();
require __DIR__ . '/fixtures/debrief-public-renderer-harness.php';
ob_end_clean();
$heading_checks = 0;
function debrief_heading_assert( $ok, $message ) { global $heading_checks; ++$heading_checks; if ( ! $ok ) { throw new RuntimeException( $message ); } }
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
// Load the real shared renderer and legacy shortcode without repeating bootstrap hooks.
function debrief_heading_load_function( $name, $alias = '' ) {
    $tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/inc/debrief.php' ) );
    foreach ( $tokens as $i => $token ) {
        if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) { continue; }
        $j = $i + 1;
        while ( isset( $tokens[ $j ] ) && ( ! is_array( $tokens[ $j ] ) || T_STRING !== $tokens[ $j ][0] ) ) { ++$j; }
        if ( ( $tokens[ $j ][1] ?? '' ) !== $name ) { continue; }
        $code = ''; $depth = 0; $opened = false;
        for ( $k = $i; $k < count( $tokens ); ++$k ) {
            $part = is_array( $tokens[ $k ] ) ? $tokens[ $k ][1] : $tokens[ $k ];
            $code .= $k === $j && '' !== $alias ? $alias : $part;
            if ( '{' === $part || ( is_array( $tokens[ $k ] ) && in_array( $tokens[ $k ][0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) { ++$depth; $opened = true; }
            elseif ( '}' === $part && 0 === --$depth && $opened ) { eval( $code ); return; }
        }
    }
    throw new RuntimeException( 'Missing real renderer: ' . $name );
}
function lunara_pair_relational_data( $post_id, $role ) { return $GLOBALS['heading_relations'][ $role ] ?? null; }
debrief_heading_load_function( 'lunara_get_career_context_meta' );
debrief_heading_load_function( 'lunara_render_pair_it_with_cards' );
debrief_heading_load_function( 'lunara_debrief_shortcode', 'debrief_heading_real_shortcode' );
lunara_test_reset( 301 );
lunara_test_prime_legacy_meta( 301 );
$shared_legacy = lunara_render_pair_it_with_cards( 301 );
foreach ( array( 'theme_echo', 'counter_program', 'career_context' ) as $role ) {
    $GLOBALS['heading_relations'][ $role ] = lunara_parse_pair_it_with_value( 'Relational film | tt0000301', 301 );
}
$shared_relational = lunara_render_pair_it_with_cards( 301 );
$expected_roles = array( 'Theme Echo', 'Counter Program', 'Career Context' );
foreach ( array(
    'canonical' => $canonical['pairings_html'],
    'canonical without a poster' => $poster_fallback['pairings_html'],
    'public legacy fallback' => $production['pairings_html'],
    'Home and Reviews shared legacy cards' => $shared_legacy,
    'Home and Reviews shared relational cards' => $shared_relational,
    'legacy shortcode' => debrief_heading_real_shortcode( array() ),
) as $surface => $html ) {
    $document = new DOMDocument();
    @$document->loadHTML( '<?xml encoding="UTF-8">' . $html );
    $xpath = new DOMXPath( $document );
    $labels = array();
    foreach ( $xpath->query( '//*[@class="lunara-pair-card-role" or @class="lunara-debrief-pair-type"]' ) as $label ) { $labels[] = trim( $label->textContent ); }
    debrief_heading_assert( $expected_roles === $labels, $surface . ' names all three complete pairing roles in their existing order.' );
}
// A curated block may occur on Journal, Home, or any ordinary page.
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
require dirname( __DIR__ ) . '/inc/pairing-showcase.php';
foreach ( array(
    array( 'Echo', 'Counter', 'Context' ),
    array( 'Theme Echo', 'Counter-Program', 'Career Context' ),
    $expected_roles,
) as $stored_labels ) {
    $pairings = array();
    foreach ( array_merge( $stored_labels, array( 'Double Feature: Echoes & Arguments' ) ) as $label ) {
        $pairings[] = array( 'label' => $label, 'title' => 'The Film', 'year' => '2001', 'imdb' => 'tt0000301' );
    }
    $curated_html = lunara_render_pairing_showcase( array( 'source' => 'curated', 'pairings' => $pairings ) );
    $document = new DOMDocument();
    @$document->loadHTML( '<?xml encoding="UTF-8">' . $curated_html );
    $xpath = new DOMXPath( $document );
    $labels = array();
    foreach ( $xpath->query( '//*[@class="lunara-pair-card-role"]' ) as $label ) { $labels[] = trim( $label->textContent ); }
    debrief_heading_assert( array_merge( $expected_roles, array( 'Double Feature: Echoes & Arguments' ) ) === $labels, 'Curated showcase expands exact known aliases and preserves a custom role title.' );
    debrief_heading_assert( 4 === $xpath->query( '//*[@class="lunara-pair-card-title-text"]' )->length, 'Every curated film remains rendered after the role-name correction.' );
}
echo "Debrief heading hierarchy and full role titles: {$heading_checks} checks passed.\n";
