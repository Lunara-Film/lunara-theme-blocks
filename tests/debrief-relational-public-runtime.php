<?php
/** Exercise saved relational choices through the real production Debrief path. */
ob_start();
require __DIR__ . '/fixtures/debrief-public-renderer-harness.php';
ob_end_clean();

$relational_checks = 0;
function relational_assert( $ok, $message ) {
    ++$GLOBALS['relational_checks'];
    if ( ! $ok ) { throw new RuntimeException( $message ); }
}

// WordPress seams for the real fixed-field adapter; no canonical records or
// approved status are synthesized for these production compatibility cases.
function get_post( $post_id ) { return $GLOBALS['relational_posts'][ (int) $post_id ] ?? null; }
function get_permalink( $post_id ) { return 'https://example.com/movies/movie-' . (int) $post_id . '/'; }
function has_post_thumbnail( $post_id ) { return ! empty( $GLOBALS['relational_posters'][ (int) $post_id ] ); }
function get_the_post_thumbnail( $post_id, $size, $attrs ) { return wp_get_attachment_image( $GLOBALS['relational_posters'][ (int) $post_id ], $size, false, $attrs ); }

function relational_load_adapter() {
    $tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/inc/debrief.php' ) );
    foreach ( $tokens as $i => $token ) {
        if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) { continue; }
        $j = $i + 1;
        while ( isset( $tokens[ $j ] ) && ( ! is_array( $tokens[ $j ] ) || T_STRING !== $tokens[ $j ][0] ) ) { ++$j; }
        if ( 'lunara_pair_relational_data' !== ( $tokens[ $j ][1] ?? '' ) ) { continue; }
        $code = ''; $depth = 0; $opened = false;
        for ( $k = $i; $k < count( $tokens ); ++$k ) {
            $part = is_array( $tokens[ $k ] ) ? $tokens[ $k ][1] : $tokens[ $k ];
            $code .= $part;
            if ( '{' === $part || ( is_array( $tokens[ $k ] ) && in_array( $tokens[ $k ][0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) { ++$depth; $opened = true; }
            elseif ( '}' === $part && 0 === --$depth && $opened ) { eval( $code ); return; }
        }
    }
    throw new RuntimeException( 'Missing real relational adapter.' );
}
relational_load_adapter();

function relational_seed( $review_id, $with_legacy = false ) {
    lunara_test_reset( $review_id );
    $GLOBALS['lunara_test']['environment'] = 'production';
    $GLOBALS['lunara_test']['home_url'] = 'https://example.com/';
    $GLOBALS['lunara_test']['filter_override'] = true;
    // This Review has no source poster; the selected companion movies below do.
    $GLOBALS['lunara_test']['legacy_media'] = '';
    if ( $with_legacy ) { lunara_test_prime_legacy_meta( $review_id ); }
    else { $GLOBALS['lunara_test']['legacy_block'] = ''; }
    $GLOBALS['relational_posts'] = array();
    $GLOBALS['relational_posters'] = array();
    foreach ( array( 'theme_echo', 'counter_program', 'career_context' ) as $index => $role ) {
        $movie_id = 1201 + $index;
        $GLOBALS['relational_posts'][ $movie_id ] = (object) array( 'ID' => $movie_id, 'post_type' => 'movie', 'post_status' => 'publish' );
        $GLOBALS['relational_posters'][ $movie_id ] = 2201 + $index;
        $GLOBALS['lunara_test']['titles'][ $movie_id ] = array( 'Saved Echo & Image', 'Saved Counter Film', 'Saved Career Film' )[ $index ];
        $GLOBALS['lunara_test']['meta'][ $movie_id ] = array( 'release_year' => (string) ( 2001 + $index ), 'imdb_title_id' => 'tt000' . $movie_id );
        $GLOBALS['lunara_test']['meta'][ $review_id ][ $role . '_movie' ] = $movie_id;
        $GLOBALS['lunara_test']['meta'][ $review_id ][ $role . '_note' ] = array(
            'The saved idea carries forward without choosing another film.',
            'The saved counterpoint keeps <script>alert(1)</script> as escaped editorial text.',
            'The saved career context remains exactly as written.',
        )[ $index ];
    }
}

relational_seed( 410 );
$before = $GLOBALS['lunara_test']['meta'];
$complete = lunara_get_review_debrief_render_parts( 410 );
relational_assert( 'legacy' === $complete['source'] && $complete['has_content'], 'Complete fixed relational fields render in production without enabling canonical output.' );
relational_assert( '' === $complete['signature_html'] && '' !== $complete['pairings_html'], 'Saved pairings render even when the legacy shortcode has no signature fields.' );
relational_assert( '' === $complete['media_html'], 'The relational-only fixture does not fabricate reviewed-film artwork.' );
relational_assert( 0 === $GLOBALS['lunara_test']['core_calls'] && 0 === $GLOBALS['lunara_test']['validation_calls'] && array() === $GLOBALS['lunara_test']['resolver_calls'], 'Compatibility output does not consult or promote a canonical record.' );
relational_assert( 0 === $GLOBALS['lunara_test']['filter_calls'] && ! lunara_debrief_public_renderer_enabled(), 'The production guard still refuses the canonical enable filter.' );
relational_assert( 0 === $GLOBALS['lunara_test']['parser_calls'], 'Complete relational choices are not reparsed or mixed with legacy text.' );
relational_assert( 0 === $GLOBALS['lunara_test']['remote_calls'], 'Saved local posters and choices require no remote calls.' );
$document = new DOMDocument();
@$document->loadHTML( '<?xml encoding="UTF-8">' . $complete['pairings_html'] );
$xpath = new DOMXPath( $document );
$labels = array(); $titles = array(); $notes = array(); $links = array();
foreach ( $xpath->query( '//*[@class="lunara-pair-card-role"]' ) as $node ) { $labels[] = $node->textContent; }
foreach ( $xpath->query( '//*[@class="lunara-pair-card-title-text"]' ) as $node ) { $titles[] = $node->textContent; }
foreach ( $xpath->query( '//*[@class="lunara-pair-card-note"]' ) as $node ) { $notes[] = $node->textContent; }
foreach ( $xpath->query( '//a[@class="lunara-pair-card-title-link"]' ) as $node ) { $links[] = $node->getAttribute( 'href' ); }
relational_assert( array( 'Theme Echo', 'Counter Program', 'Career Context' ) === $labels, 'The three exact roles retain their editorial order.' );
relational_assert( array( 'Saved Echo & Image', 'Saved Counter Film', 'Saved Career Film' ) === $titles, 'Selected published movie titles render, with escaping intact.' );
relational_assert( array_values( array_intersect_key( $before[410], array_flip( array( 'theme_echo_note', 'counter_program_note', 'career_context_note' ) ) ) ) === $notes, 'Every saved explanation survives exactly without rewriting.' );
relational_assert( array( 'https://example.com/movies/movie-1201/', 'https://example.com/movies/movie-1202/', 'https://example.com/movies/movie-1203/' ) === $links, 'Each title links to its selected movie dossier.' );
relational_assert( 3 === $xpath->query( '//*[@class="lunara-pair-card-year"]' )->length && 3 === $xpath->query( '//a[contains(@class,"--imdb")]' )->length && 3 === $xpath->query( '//img' )->length, 'Saved years, IMDb links and local posters reach all three cards.' );
relational_assert( 0 === $xpath->query( '//script' )->length, 'Editorial text cannot inject executable markup.' );
relational_assert( $before === $GLOBALS['lunara_test']['meta'], 'Public compatibility rendering writes no metadata or status.' );
$complete['expected_notes'] = $notes;

relational_seed( 411, true );
$preferred = lunara_get_review_debrief_render_parts( 411 );
relational_assert( false !== strpos( $preferred['pairings_html'], 'Saved Echo &amp; Image' ) && false === strpos( $preferred['pairings_html'], 'LEGACY THEME' ) && 0 === $GLOBALS['lunara_test']['parser_calls'], 'A complete saved relational trio wins as one group over conflicting legacy choices.' );

$mutations = array(
    'partial trio' => static function ( $id ) { unset( $GLOBALS['lunara_test']['meta'][ $id ]['career_context_movie'] ); },
    'missing note' => static function ( $id ) { $GLOBALS['lunara_test']['meta'][ $id ]['career_context_note'] = '  '; },
    'draft movie' => static function () { $GLOBALS['relational_posts'][1203]->post_status = 'draft'; },
    'private movie' => static function () { $GLOBALS['relational_posts'][1203]->post_status = 'private'; },
    'deleted movie' => static function () { unset( $GLOBALS['relational_posts'][1203] ); },
    'wrong post type' => static function () { $GLOBALS['relational_posts'][1203]->post_type = 'person'; },
    'missing title' => static function () { $GLOBALS['lunara_test']['titles'][1203] = ''; },
    'explicit zero choice' => static function ( $id ) { $GLOBALS['lunara_test']['meta'][ $id ]['career_context_movie'] = '0'; },
    'malformed movie id' => static function ( $id ) { $GLOBALS['lunara_test']['meta'][ $id ]['career_context_movie'] = '1203invalid'; },
    'array movie id' => static function ( $id ) { $GLOBALS['lunara_test']['meta'][ $id ]['career_context_movie'] = array( 1203 ); },
    'array note' => static function ( $id ) { $GLOBALS['lunara_test']['meta'][ $id ]['career_context_note'] = array( 'A note must not become Array.' ); },
);
foreach ( $mutations as $index => $mutate ) {
    $id = 420 + array_search( $index, array_keys( $mutations ), true );
    relational_seed( $id, true );
    $mutate( $id );
    $before = $GLOBALS['lunara_test']['meta'];
    $fallback = lunara_get_review_debrief_render_parts( $id );
    relational_assert( 'legacy' === $fallback['source'] && $fallback['has_content'], $index . ': existing legacy module remains available.' );
    relational_assert( 3 === $GLOBALS['lunara_test']['parser_calls'] && false !== strpos( $fallback['pairings_html'], 'LEGACY THEME' ) && false !== strpos( $fallback['pairings_html'], 'LEGACY COUNTER' ) && false !== strpos( $fallback['pairings_html'], 'LEGACY CAREER' ), $index . ': all three legacy roles replace the rejected relational group.' );
    relational_assert( false === strpos( $fallback['pairings_html'], 'Saved Echo' ) && false === strpos( $fallback['pairings_html'], 'Saved Counter' ), $index . ': already resolved relational roles never leak into fallback.' );
    relational_assert( $before === $GLOBALS['lunara_test']['meta'], $index . ': fallback never repairs, rewrites or promotes saved values.' );
}

relational_seed( 450 );
unset( $GLOBALS['lunara_test']['meta'][450]['career_context_movie'] );
$empty = lunara_get_review_debrief_render_parts( 450 );
relational_assert( 'none' === $empty['source'] && ! $empty['has_content'] && '' === $empty['pairings_html'], 'Incomplete relational choices with no legacy content produce no partial module.' );

relational_seed( 451 );
$GLOBALS['relational_posters'] = array();
$without_art = lunara_get_review_debrief_render_parts( 451 );
relational_assert( $without_art['has_content'] && 3 === substr_count( $without_art['pairings_html'], 'lunara-pair-card-poster--plate' ), 'Missing optional posters preserve all three selected films and arguments as title plates.' );
relational_assert( 0 === $GLOBALS['lunara_test']['remote_calls'], 'Missing optional artwork does not invent remote calls in this local adapter fixture.' );
$without_art['expected_notes'] = $notes;
$without_art['expected_missing_poster'] = true;

if ( in_array( '--fixture', $argv ?? array(), true ) ) {
    echo json_encode( array( $complete, $without_art ), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
} else {
    echo "Relational public Debrief: {$relational_checks} checks passed.\n";
}
