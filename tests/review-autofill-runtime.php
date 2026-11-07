<?php
/** Exercise the actual content autofill function against saved and submitted values. */
define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/inc/review-display-meta.php';
$autofill_meta = array();
$autofill_content = "<!-- A Film (2026) tt12345678 -->\nScore: 3.5 / 5\nWhere to Watch: Cinema\nTheme Echo: A Companion (2001) tt0000001\n";
$autofill_type = 'review';
$autofill_can_edit = true;
$autofill_writes = array();
function get_post_type( $id ) { return $GLOBALS['autofill_type']; }
function current_user_can( $capability, $id ) { return $GLOBALS['autofill_can_edit']; }
function get_post_field( $field, $id ) { return $GLOBALS['autofill_content']; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['autofill_meta'][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['autofill_meta'][ $key ] = $value; $GLOBALS['autofill_writes'][] = $key; return true; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }

// Extract the production function so this fixture does not initialize unrelated hooks.
$tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/inc/debrief.php' ) );
foreach ( $tokens as $i => $token ) {
    if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) { continue; }
    $j = $i + 1;
    while ( isset( $tokens[ $j ] ) && ( ! is_array( $tokens[ $j ] ) || T_STRING !== $tokens[ $j ][0] ) ) { ++$j; }
    if ( 'lunara_autofill_review_meta_from_content' !== ( $tokens[ $j ][1] ?? '' ) ) { continue; }
    $code = ''; $depth = 0; $opened = false;
    for ( $k = $i; $k < count( $tokens ); ++$k ) {
        $part = is_array( $tokens[ $k ] ) ? $tokens[ $k ][1] : $tokens[ $k ];
        $code .= $part;
        if ( '{' === $part ) { ++$depth; $opened = true; }
        elseif ( '}' === $part && $opened && 0 === --$depth ) { eval( $code ); break 2; }
    }
}
if ( ! function_exists( 'lunara_autofill_review_meta_from_content' ) ) { throw new RuntimeException( 'Actual autofill function missing.' ); }
$autofill_checks = 0;
function autofill_assert( $ok, $message ) { ++$GLOBALS['autofill_checks']; if ( ! $ok ) { throw new RuntimeException( $message ); } }
function autofill_reset( $meta = array(), $post = array() ) { $GLOBALS['autofill_meta'] = $meta; $GLOBALS['autofill_writes'] = array(); $_POST = $post; }

autofill_reset();
lunara_autofill_review_meta_from_content( 10 );
autofill_assert( '2026' === get_post_meta( 10, '_lunara_year' ), 'Absent year fills from the saved header.' );
autofill_assert( '3.5' === get_post_meta( 10, '_lunara_score' ), 'Absent score fills from saved content.' );
autofill_assert( 'Cinema' === get_post_meta( 10, '_lunara_where' ), 'Absent viewing information fills from content.' );

autofill_reset( array( '_lunara_year' => '1999', '_lunara_score' => '0', '_lunara_where' => 'An editorial viewing note' ) );
lunara_autofill_review_meta_from_content( 10 );
autofill_assert( '1999' === get_post_meta( 10, '_lunara_year' ), 'An existing year is never replaced by a different content year.' );
autofill_assert( '0' === get_post_meta( 10, '_lunara_score' ), 'An existing zero is not treated as empty.' );
autofill_assert( 'An editorial viewing note' === get_post_meta( 10, '_lunara_where' ), 'A saved viewing override remains authoritative.' );

autofill_reset( array( '_lunara_year' => 'field_lunara_year', 'lunara_year' => '2005' ) );
lunara_autofill_review_meta_from_content( 10 );
autofill_assert( 'field_lunara_year' === get_post_meta( 10, '_lunara_year' ) && ! in_array( '_lunara_year', $GLOBALS['autofill_writes'], true ), 'A populated old ACF value is preserved without rewriting its reference.' );
autofill_reset( array( '_lunara_year' => 'field_lunara_year', 'lunara_year' => '' ) );
lunara_autofill_review_meta_from_content( 10 );
autofill_assert( '2026' === get_post_meta( 10, '_lunara_year' ), 'A reference with no actual value does not block a genuine content fill.' );

foreach ( array( array( 'lunara_year' => '' ), array( 'acf' => array( 'field_lunara_year' => '' ) ) ) as $clear_post ) {
    autofill_reset( array( '_lunara_year' => '' ), $clear_post );
    lunara_autofill_review_meta_from_content( 10 );
    autofill_assert( '' === get_post_meta( 10, '_lunara_year' ) && ! in_array( '_lunara_year', $GLOBALS['autofill_writes'], true ), 'An explicit submitted clear is never refilled by the content parser.' );
}
autofill_reset( array( '_lunara_year' => '', '__lunara_year' => 'field_lunara_year' ) );
lunara_autofill_review_meta_from_content( 10 );
autofill_assert( '' === get_post_meta( 10, '_lunara_year' ), 'A canonical ACF clear remains cleared on a later unrelated save.' );
autofill_reset( array( '_lunara_where' => array( 'title' => 'A saved link', 'url' => 'https://example.test/watch' ) ) );
lunara_autofill_review_meta_from_content( 10 );
autofill_assert( is_array( get_post_meta( 10, '_lunara_where' ) ), 'Unrecognized structured saved data is preserved for its owning converter.' );

foreach ( array( 'wrong-type', 'no-permission' ) as $case ) {
    autofill_reset();
    $GLOBALS['autofill_type'] = 'wrong-type' === $case ? 'post' : 'review';
    $GLOBALS['autofill_can_edit'] = 'no-permission' !== $case;
    lunara_autofill_review_meta_from_content( 10 );
    autofill_assert( array() === $GLOBALS['autofill_writes'], 'Unrelated posts and unauthorized requests never autofill.' );
}
echo "Review content autofill: {$autofill_checks} checks passed.\n";
