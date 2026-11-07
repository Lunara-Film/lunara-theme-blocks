<?php
/** Render Review metadata through the real public read boundary and renderers. */
ob_start();
require __DIR__ . '/fixtures/debrief-public-renderer-harness.php';
ob_end_clean();

$meta_checks = 0;
function review_meta_assert( $ok, $message ) {
    ++$GLOBALS['meta_checks'];
    if ( ! $ok ) {
        throw new RuntimeException( $message );
    }
}

// Load the actual renderers without booting unrelated admin hooks.
function review_meta_load_function( $file, $name, $alias = '' ) {
    $tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/' . $file ) );
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

function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, $args ); }
function sanitize_html_class( $value ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ); }
class Lunara_Review_Image_Studio {
    public static function resolve_slot( $post_id, $slot ) {
        return $GLOBALS['meta_image_slot'];
    }
}

review_meta_load_function( 'inc/review-rendering.php', 'lunara_get_review_card_meta' );
review_meta_load_function( 'inc/review-rendering.php', 'lunara_get_review_visual_slot_data' );
review_meta_load_function( 'inc/review-rendering.php', 'lunara_render_review_visual_slot' );
review_meta_load_function( 'inc/debrief.php', 'lunara_debrief_shortcode', 'review_meta_real_shortcode' );

lunara_test_reset( 33224 );
$caption_key = '_lunara_review_hero_banner_caption';
$reference   = 'field_lunara_review_hero_banner_caption';
foreach ( array(
    array( 'Legacy caption', 'Different ACF caption', 'Legacy caption' ),
    array( 'The label field_lunara_review_hero_banner_caption appears in the scene.', '', 'The label field_lunara_review_hero_banner_caption appears in the scene.' ),
    array( $reference, 'A real image caption.', 'A real image caption.' ),
    array( $reference, '', '' ),
    array( $reference, $reference, '' ),
    array( $reference, array( 'not', 'scalar' ), '' ),
    array( array( 'not', 'scalar' ), 'A fallback must not override malformed legacy data.', '' ),
    array( '0', 'Different ACF caption', '0' ),
    array( false, 'Different ACF caption', '' ),
    array( '  Legacy caption  ', 'Different ACF caption', '  Legacy caption  ' ),
    array( 'field_lunara_other_caption', 'Different ACF caption', 'field_lunara_other_caption' ),
) as $index => $case ) {
    $GLOBALS['lunara_test']['meta'][33224] = array( $caption_key => $case[0], 'lunara_review_hero_banner_caption' => $case[1] );
    review_meta_assert( $case[2] === lunara_get_review_display_meta( 33224, $caption_key ), 'Scalar/reference caption case ' . $index . ' keeps only the correct display value.' );
}

$GLOBALS['meta_image_slot'] = array( 'mode' => 'custom', 'url' => 'https://example.com/still.jpg' );
$GLOBALS['lunara_test']['meta'][33224] = array(
    $caption_key => $reference,
    'lunara_review_hero_banner_caption' => '',
    '_lunara_year' => 'field_lunara_year',
    'lunara_year' => '',
    '_lunara_where' => 'field_lunara_where',
    'lunara_where' => '',
    '_lunara_theme_echo' => 'field_lunara_theme_echo',
    '_lunara_counter_program' => 'field_lunara_counter_program',
    '_lunara_career_context' => 'field_lunara_career_context',
);
$saved_meta = $GLOBALS['lunara_test']['meta'];
$slot = lunara_get_review_visual_slot_data( 33224, 'hero_banner' );
$hero = lunara_render_review_visual_slot( 33224, 'hero_banner', array( 'context' => 'hero', 'loading' => 'eager' ) );
review_meta_assert( '' === $slot['caption'] && false === strpos( $slot['alt'], 'field_' ), 'Empty actual ACF caption does not become a caption or image alt.' );
review_meta_assert( false === strpos( $hero, '<figcaption' ) && false === strpos( $hero, 'field_' ), 'The rendered hero has no technical field label or empty caption container.' );
review_meta_assert( false !== strpos( $hero, 'still.jpg' ) && false !== strpos( $hero, 'fetchpriority="high"' ), 'Caption suppression preserves the selected hero and eager delivery.' );
review_meta_assert( '' === lunara_get_review_card_meta( 33224 ), 'Archive and title metadata omit the empty year reference.' );
review_meta_assert( '' === review_meta_real_shortcode( array() ), 'The real legacy shortcode omits a module made entirely of empty ACF references.' );
review_meta_assert( '' === lunara_debrief_render_legacy_pairing_cards( 33224 ), 'The public legacy pairing renderer does not build film links from reference names.' );
$signature = lunara_debrief_render_canonical_signature( 33224, array( 'year' => '2026' ) );
review_meta_assert( false === strpos( $signature, 'field_' ) && false !== strpos( $signature, '2026' ), 'The canonical signature can use its existing film-year fallback instead of a reference.' );
review_meta_assert( $saved_meta === $GLOBALS['lunara_test']['meta'], 'Public rendering never changes either metadata format.' );

$GLOBALS['lunara_test']['meta'][33224]['lunara_review_hero_banner_caption'] = 'Jessie & Bullseye <em>in the frame</em>.';
$hero = lunara_render_review_visual_slot( 33224, 'hero_banner' );
review_meta_assert( false !== strpos( $hero, '<figcaption' ) && false !== strpos( $hero, 'Jessie &amp; Bullseye &lt;em&gt;in the frame&lt;/em&gt;.' ), 'An actual ACF caption renders with the same escaping as a legacy caption.' );
$GLOBALS['lunara_test']['meta'][33224]['lunara_year'] = '2026';
$GLOBALS['lunara_test']['meta'][33224]['lunara_where'] = 'Cinema';
$GLOBALS['lunara_test']['meta'][33224]['lunara_theme_echo'] = 'A Real Film (2001) | tt0000101';
review_meta_assert( '2026' === lunara_get_review_card_meta( 33224 ), 'The archive/title helper reads the actual ACF year.' );
$legacy = review_meta_real_shortcode( array() );
review_meta_assert( false === strpos( $legacy, 'field_' ) && false !== strpos( $legacy, '2026' ) && false !== strpos( $legacy, 'Cinema' ), 'The real legacy signature reads actual ACF values.' );
$pairs = lunara_debrief_render_legacy_pairing_cards( 33224 );
review_meta_assert( false !== strpos( $pairs, 'A Real Film' ) && false === strpos( $pairs, 'field_' ), 'An actual ACF pairing still renders through the existing legacy path.' );
$GLOBALS['meta_image_slot']['mode'] = 'off';
review_meta_assert( '' === lunara_render_review_visual_slot( 33224, 'hero_banner' ), 'The Image Studio off setting remains authoritative.' );

echo "Review display metadata: {$meta_checks} checks passed.\n";
