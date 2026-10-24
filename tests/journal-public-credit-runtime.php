<?php
/** Real Journal renderers: stored provenance is private unless explicitly opted in. */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['credit_meta'] = array();
$GLOBALS['credit_captions'] = array( 101 => 'A director on set.', 102 => 'Image: Legacy agency / Source: example.test' );
$GLOBALS['credit_checks'] = 0;
function credit_check( $ok, $message ) { ++$GLOBALS['credit_checks']; if ( ! $ok ) { throw new RuntimeException( $message ); } }
function absint( $value ) { return abs( (int) $value ); }
function __( $text ) { return $text; }
function _n( $one, $many, $n ) { return 1 === $n ? $one : $many; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_html( $text ); }
function esc_url_raw( $text ) { return (string) $text; }
function esc_html__( $text ) { return esc_html( $text ); }
function esc_html_e( $text ) { echo esc_html( $text ); }
function esc_attr_e( $text ) { echo esc_attr( $text ); }
function sanitize_key( $text ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $text ) ); }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_html_class( $text ) { return sanitize_key( $text ); }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function wp_kses_post( $text ) { return $text; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['credit_meta'][$id][$key] ?? ''; }
function get_post_type( $id ) { return 9 === (int) $id ? 'review' : ( $id >= 100 ? 'attachment' : 'journal' ); }
function get_post_thumbnail_id( $id ) { return $id ? 101 : 0; }
function wp_get_attachment_caption( $id ) { return $GLOBALS['credit_captions'][$id] ?? ''; }
function wp_attachment_is_image( $id ) { return $id >= 100; }
function get_the_title( $id ) { return 'Still ' . $id; }
function wp_get_attachment_image( $id, $size, $icon, $attrs ) { return '<img src="still-' . absint( $id ) . '.jpg" alt="A film still">'; }
function lunara_journal_archive_studio_gallery_media_markup( $item ) { return wp_get_attachment_image( $item['attachment_id'], 'large', false, array() ); }

function credit_load( $file, $name ) {
    $tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/' . $file ) );
    foreach ( $tokens as $i => $token ) {
        if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) { continue; }
        $j = $i + 1;
        while ( isset( $tokens[$j] ) && ( ! is_array( $tokens[$j] ) || T_STRING !== $tokens[$j][0] ) ) { ++$j; }
        if ( ( $tokens[$j][1] ?? '' ) !== $name ) { continue; }
        $code = ''; $depth = 0; $opened = false;
        for ( $k = $i; $k < count( $tokens ); ++$k ) {
            $part = is_array( $tokens[$k] ) ? $tokens[$k][1] : $tokens[$k]; $code .= $part;
            if ( '{' === $part || ( is_array( $tokens[$k] ) && in_array( $tokens[$k][0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) { ++$depth; $opened = true; }
            elseif ( '}' === $part && 0 === --$depth && $opened ) { eval( $code ); return; }
        }
    }
    throw new RuntimeException( 'Missing renderer ' . $name );
}
foreach ( array( 'lunara_journal_field_has_value', 'lunara_get_journal_field_value', 'lunara_journal_should_show_public_credit', 'lunara_get_journal_image_source_pair' ) as $name ) { credit_load( 'inc/journal-family.php', $name ); }
foreach ( array( 'lunara_get_journal_hero_credit_data', 'lunara_render_journal_hero_credit', 'lunara_get_journal_carousel_ids', 'lunara_get_journal_carousel_credit_html', 'lunara_render_journal_image_carousel' ) as $name ) { credit_load( 'single-journal.php', $name ); }
foreach ( array( 'lunara_get_journal_first_meta_value', 'lunara_get_journal_visual_context', 'lunara_render_journal_card_provenance' ) as $name ) { credit_load( 'functions.php', $name ); }
credit_load( 'inc/journal-archive-studio.php', 'lunara_journal_archive_studio_render_gallery' );
function credit_hero( $id ) { ob_start(); lunara_render_journal_hero_credit( $id ); return ob_get_clean(); }
function credit_card( $id ) { ob_start(); lunara_render_journal_card_provenance( $id ); return ob_get_clean(); }
$GLOBALS['credit_meta'][1] = array( 'journal_image_credit' => 'Deadline', 'journal_image_source_url' => 'https://deadline.com/still.jpg', '_lunara_featured_image_credit' => 'Legacy credit', '_lunara_featured_image_source_name' => 'Legacy source', '_lunara_featured_image_source_url' => 'https://legacy.example/still.jpg' );
$GLOBALS['credit_meta'][101] = array( '_lunara_image_credit' => 'Photographer', '_lunara_image_source_name' => 'Film studio', '_lunara_image_source_url' => 'https://studio.example/still.jpg' );
$original_meta = serialize( $GLOBALS['credit_meta'] );
credit_check( '' === credit_hero( 1 ) && '' === credit_card( 1 ) && '' === lunara_get_journal_carousel_credit_html( 101, 1 ), 'Existing canonical/legacy provenance must not create public labels by default.' );
credit_check( 'Deadline' === lunara_get_journal_hero_credit_data( 1 )['credit'], 'Private provenance remains available.' );
foreach ( array( 'A nonexclusive story', 'Exclusive interview coverage', 'Not Exclusive', '' ) as $kicker ) {
    $GLOBALS['credit_meta'][1]['journal_kicker'] = $kicker;
    credit_check( ! lunara_journal_should_show_public_credit( 1, 101 ), 'Only an exact Exclusive label opts in.' );
}
foreach ( array( 'Exclusive', ' exclusive ', 'EXCLUSIVE' ) as $kicker ) {
    $GLOBALS['credit_meta'][1]['journal_kicker'] = $kicker;
    credit_check( false !== strpos( credit_hero( 1 ), 'Image: Deadline' ) && false !== strpos( credit_card( 1 ), 'Visual source:' ), 'Explicit Exclusive files keep their public attribution.' );
}
unset( $GLOBALS['credit_meta'][1]['journal_kicker'] );
$GLOBALS['credit_meta'][1]['_lunara_journal_kicker'] = 'Exclusive';
credit_check( lunara_journal_should_show_public_credit( 1 ), 'An explicit legacy Exclusive kicker remains compatible.' );
$GLOBALS['credit_meta'][1]['journal_kicker'] = 'News';
credit_check( ! lunara_journal_should_show_public_credit( 1 ), 'Canonical nonexclusive kicker overrides old Exclusive metadata.' );
unset( $GLOBALS['credit_meta'][1]['journal_kicker'], $GLOBALS['credit_meta'][1]['_lunara_journal_kicker'] );
foreach ( array( '0', 0, false, 'false', 'yes', 'true', array( 1 ) ) as $flag ) {
    $GLOBALS['credit_meta'][101]['_lunara_journal_public_credit_required'] = $flag;
    credit_check( ! lunara_journal_should_show_public_credit( 1, 101 ), 'Untyped/truthy metadata must not opt a file in.' );
}
foreach ( array( true, 1, '1' ) as $flag ) {
    $GLOBALS['credit_meta'][101]['_lunara_journal_public_credit_required'] = $flag;
    credit_check( lunara_journal_should_show_public_credit( 0, 101 ) && false !== strpos( credit_hero( 1 ), 'Image: Deadline' ), 'Explicit required-credit attachment opt-in preserves attribution without an ambient post.' );
}
credit_check( ! lunara_journal_should_show_public_credit( 9, 101 ), 'Non-Journal posts stay outside the policy.' );
unset( $GLOBALS['credit_meta'][101]['_lunara_journal_public_credit_required'] );
foreach ( array( true, 1, '1' ) as $flag ) {
    $GLOBALS['credit_meta'][1]['_lunara_journal_public_credit_required'] = $flag;
    credit_check( lunara_journal_should_show_public_credit( 1 ) && false !== strpos( credit_hero( 1 ), 'Source:' ), 'Explicit post attribution requirements preserve their public labels.' );
}
unset( $GLOBALS['credit_meta'][1]['_lunara_journal_public_credit_required'] );
$GLOBALS['post'] = (object) array( 'ID' => 1 );
$GLOBALS['credit_meta'][1]['journal_kicker'] = 'Exclusive';
credit_check( ! lunara_journal_should_show_public_credit() && '' === lunara_get_journal_carousel_credit_html( 101 ), 'A global loop post must not leak an Exclusive decision into archive-only media.' );
$exclusive_carousel = lunara_render_journal_image_carousel( 1, array( 101, 102 ) );
credit_check( false !== strpos( $exclusive_carousel, 'Image: Photographer' ) && false !== strpos( $exclusive_carousel, 'Image: Legacy agency' ), 'Exclusive carousel retains generated and legacy-caption attribution.' );
unset( $GLOBALS['credit_meta'][1]['journal_kicker'] );
$carousel = lunara_render_journal_image_carousel( 1, array( 101, 102 ) );
credit_check( 2 === substr_count( $carousel, '<img ' ) && false !== strpos( $carousel, 'A director on set.' ) && false === strpos( $carousel, 'Image:' ) && false === strpos( $carousel, 'Source:' ), 'Ordinary carousel keeps images and descriptions without credit-only imported captions or generated source labels.' );
credit_check( 1 === substr_count( $carousel, '<figcaption>' ), 'A hidden credit-only caption leaves no empty wrapper.' );
$GLOBALS['credit_meta'][1]['_lunara_journal_carousel_ids'] = array( 101, 102 );
$card = credit_card( 1 );
credit_check( false !== strpos( $card, '2 stills' ) && false === strpos( $card, 'Visual source:' ), 'Descriptive still-count badges survive without source badges.' );
unset( $GLOBALS['credit_meta'][1]['_lunara_journal_carousel_ids'] );
$gallery = array( 'title' => 'Frames', 'kicker' => '', 'copy' => '', 'items' => array( array( 'order' => 1, 'attachment_id' => 101, 'caption' => 'Descriptive frame', 'link_url' => 'https://example.test/read/', 'credit' => 'Photographer', 'source' => 'Studio', 'source_url' => 'https://studio.example/source/', 'focal_x' => 50, 'focal_y' => 50 ) ) );
$html = lunara_journal_archive_studio_render_gallery( $gallery );
credit_check( false !== strpos( $html, 'Descriptive frame' ) && false !== strpos( $html, 'https://example.test/read/' ) && false === strpos( $html, 'Photographer' ) && false === strpos( $html, 'https://studio.example/source/' ), 'Archive-only gallery keeps caption/destination while provenance stays private.' );
$gallery['items'][0]['caption'] = '';
credit_check( false === strpos( lunara_journal_archive_studio_render_gallery( $gallery ), '<figcaption>' ), 'No gallery caption or public attribution leaves no figcaption.' );
$gallery['items'][0]['caption'] = 'Source: legacy outlet';
credit_check( false === strpos( lunara_journal_archive_studio_render_gallery( $gallery ), '<figcaption>' ), 'Legacy credit-only archive captions stay private by default.' );
$GLOBALS['credit_meta'][101]['_lunara_journal_public_credit_required'] = true;
$required_gallery = lunara_journal_archive_studio_render_gallery( $gallery );
credit_check( false !== strpos( $required_gallery, 'Photographer' ) && false !== strpos( $required_gallery, 'https://studio.example/source/' ), 'Archive gallery honors explicit attachment attribution requirements.' );
unset( $GLOBALS['credit_meta'][101]['_lunara_journal_public_credit_required'] );
$saved_credit = $GLOBALS['credit_meta'][1]['journal_image_credit'];
$GLOBALS['credit_meta'][1]['journal_kicker'] = 'Exclusive';
$GLOBALS['credit_meta'][1]['journal_image_credit'] = '<script>alert(1)</script> Photographer & Partner';
$escaped_hero = credit_hero( 1 );
credit_check( false === strpos( $escaped_hero, '<script>' ) && false !== strpos( $escaped_hero, 'Photographer &amp; Partner' ), 'An opted-in hero still sanitizes and escapes attribution text.' );
$GLOBALS['credit_meta'][1]['journal_image_credit'] = $saved_credit;
unset( $GLOBALS['credit_meta'][1]['journal_kicker'] );
credit_check( $original_meta === serialize( $GLOBALS['credit_meta'] ), 'Rendering must not remove or rewrite stored provenance.' );
echo 'Journal public credit policy: ' . $GLOBALS['credit_checks'] . " checks passed.\n";
