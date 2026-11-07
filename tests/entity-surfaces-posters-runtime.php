<?php
/**
 * Theme 3.2.156: the film and talent records (/film/, /talent/ and their
 * dossiers) print pictures sized to their frames, and the dossier backdrop is
 * a picture rather than an inline background.
 *
 * Run: php tests/entity-surfaces-posters-runtime.php
 *
 * Measured on live 3.2.154 at 390px (2x): ten 170px index posters fetched
 * 683-768px crops (666 KB) because the cards carried WordPress's default
 * sizes; uploads with no intermediate sizes printed no candidates at all;
 * the dossier poster fetched a 780px file for a 218px frame; the backdrop
 * (when a published dossier carries one) was an inline background of the
 * TMDB original. Real functions are extracted from the theme; WordPress is
 * stubbed to WordPress's own behaviour (srcset of the registered sizes,
 * "auto, " in front of a lazy image's sizes).
 */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$checks = 0;
function entity_assert( $condition, $message ) { global $checks; ++$checks; if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }

function esc_url( $url ) { return str_replace( '&', '&#038;', (string) $url ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path = '' ) { return 'https://lunarafilm.com/' . ltrim( (string) $path, '/' ); }
function remove_query_arg( $keys, $url ) {
	$parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $query );
	foreach ( (array) $keys as $key ) { unset( $query[ $key ] ); }
	return strtok( $url, '?' ) . ( $query ? '?' . http_build_query( $query ) : '' );
}
function add_query_arg( $args, $value = null, $url = null ) {
	if ( ! is_array( $args ) ) { $args = array( $args => $value ); } else { $url = $value; }
	$parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $query );
	return strtok( $url, '?' ) . '?' . http_build_query( array_merge( $query, $args ) );
}
function maybe_unserialize( $value ) { return $value; }
function get_permalink( $id ) { return 'https://lunarafilm.com/film/record-' . (int) $id . '/'; }
function get_the_title( $id ) { return 'Record ' . (int) $id; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['entity_meta'][ (int) $id ][ $key ] ?? ''; }
function has_post_thumbnail( $id = null ) { return ! empty( $GLOBALS['entity_thumbs'][ (int) $id ] ); }
function get_post_thumbnail_id( $id = null ) { return (int) ( $GLOBALS['entity_thumbs'][ (int) $id ] ?? 0 ); }
function lunara_attachment_id_for_url( $url ) { return 0; }
// Attachments: a 2000x3000 poster, a 500x750 upload, a 683x1024 portrait, and one without measurements.
$GLOBALS['entity_attachments'] = array(
	101 => array( 'width' => 2000, 'height' => 3000, 'file' => '2026/03/tt0000001-poster.jpg' ),
	102 => array( 'width' => 500, 'height' => 750, 'file' => '2026/07/tt0000002-poster.jpg' ),
	103 => array( 'width' => 683, 'height' => 1024, 'file' => '2026/06/nm0000003-profile.jpg' ),
	104 => array( 'file' => '2026/07/unmeasured.jpg' ),
);
function wp_get_attachment_metadata( $id ) { return $GLOBALS['entity_attachments'][ (int) $id ] ?? false; }
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
	$meta = wp_get_attachment_metadata( $id );
	if ( ! $meta ) { return false; }
	if ( empty( $meta['width'] ) ) { return 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/' . $meta['file'] . '?quality=82&ssl=1'; } // an unmeasured upload carries no fit= on the CDN
	$box = array( 'full' => array( $meta['width'], $meta['height'] ), 'large' => array( 683, 1024 ), 'medium_large' => array( 768, 1152 ), 'medium' => array( 200, 300 ) )[ $size ] ?? array( 768, 1152 );
	return 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/' . $meta['file'] . '?fit=' . $box[0] . '%2C' . $box[1] . '&quality=82&ssl=1';
}
// wp_get_attachment_image as WordPress builds it: width/height for the size, a srcset of the registered
// sizes narrower than the original plus the original, a caller's src and srcset win, and "auto, " goes
// in front of a lazy image's sizes.
function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) {
	$meta = wp_get_attachment_metadata( $id );
	if ( ! $meta ) { return ''; }
	$box  = array( 'large' => array( 683, 1024 ), 'medium_large' => array( 768, 1152 ) )[ $size ] ?? array( 768, 1152 );
	$attr = array_merge( array( 'src' => wp_get_attachment_image_url( $id, $size ), 'class' => 'attachment-' . $size . ' size-' . $size . ' wp-post-image', 'alt' => '', 'decoding' => 'async' ), (array) $attr );
	if ( empty( $attr['srcset'] ) && ! empty( $meta['width'] ) ) {
		$widths = array_unique( array_merge( array_filter( array( 200, 683, 768 ), function ( $w ) use ( $meta ) { return $w < (int) $meta['width']; } ), array( (int) $meta['width'] ) ) );
		$attr['srcset'] = implode( ', ', array_map( function ( $w ) use ( $meta ) { return 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/' . $meta['file'] . '?w=' . $w . '&ssl=1 ' . $w . 'w'; }, $widths ) );
	}
	if ( empty( $attr['srcset'] ) ) { unset( $attr['sizes'] ); }
	if ( ( $attr['loading'] ?? '' ) === 'lazy' && ! empty( $attr['sizes'] ) && 0 !== strpos( $attr['sizes'], 'auto' ) ) { $attr['sizes'] = 'auto, ' . $attr['sizes']; }
	$html = '<img width="' . $box[0] . '" height="' . $box[1] . '"';
	foreach ( $attr as $key => $value ) { $html .= ' ' . $key . '="' . esc_attr( $value ) . '"'; }
	return $html . ' />';
}
function get_the_post_thumbnail( $id, $size = 'post-thumbnail', $attr = array() ) { return wp_get_attachment_image( get_post_thumbnail_id( $id ), $size, false, $attr ); }

function entity_extract( $file, $name ) {
	$tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/' . $file ) );
	foreach ( $tokens as $i => $token ) {
		if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) { continue; }
		$j = $i + 1; while ( isset( $tokens[ $j ] ) && ( ! is_array( $tokens[ $j ] ) || T_STRING !== $tokens[ $j ][0] ) ) { ++$j; }
		if ( ( $tokens[ $j ][1] ?? '' ) !== $name ) { continue; }
		$code = ''; $depth = 0; $opened = false;
		for ( $k = $i; $k < count( $tokens ); ++$k ) {
			$part = is_array( $tokens[ $k ] ) ? $tokens[ $k ][1] : $tokens[ $k ]; $code .= $part;
			if ( '{' === $part || ( is_array( $tokens[ $k ] ) && in_array( $tokens[ $k ][0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) { ++$depth; $opened = true; }
			elseif ( '}' === $part && 0 === --$depth && $opened ) { eval( $code ); return; }
		}
	}
	throw new RuntimeException( 'Missing function ' . $name );
}
foreach ( array( 'lunara_resize_wpcom_image_url', 'lunara_image_cdn_source', 'lunara_image_cdn_crop_url', 'lunara_image_cdn_width_url', 'lunara_image_source_size_from_url', 'lunara_image_upload_size', 'lunara_image_cdn_crop_limit', 'lunara_image_cdn_crop_srcset', 'lunara_image_cdn_width_srcset', 'lunara_image_sizes_for' ) as $name ) { entity_extract( 'inc/setup.php', $name ); }
entity_extract( 'inc/oscars-data.php', 'lunara_oscars_attachment_size' );
foreach ( array( 'lunara_entity_poster_img', 'lunara_entity_backdrop_img', 'lunara_entity_render_film_card', 'lunara_entity_render_person_card' ) as $name ) { entity_extract( 'inc/entity-surfaces.php', $name ); }

function attr( $html, $name ) { return preg_match( '/\s' . $name . '="([^"]*)"/', $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : null; }
function widths( $html ) { preg_match_all( '/\s(\d+)w(?:,|$)/', attr( $html, 'srcset' ) ?? '', $m ); $w = array_map( 'intval', $m[1] ); sort( $w ); return $w; }

$GLOBALS['entity_meta']   = array( 1 => array( 'release_year' => '2026' ), 3 => array( 'roles' => array( 'director' ) ) );
$GLOBALS['entity_thumbs'] = array( 1 => 101, 2 => 102, 3 => 103, 4 => 104, 5 => 0 );
$card_widths = array( 170, 200, 256, 340, 400, 512, 680 );
$hero_widths = array( 160, 220, 250, 320, 440, 500 );

// ---- A film card: 2:3 crops at the frame's widths, lazy with auto in front of the measured sizes ----
$card = lunara_entity_render_film_card( 1 );
entity_assert( false !== strpos( $card, 'lunara-entity-card--film' ) && false !== strpos( $card, 'Record 1' ) && false !== strpos( $card, '2026' ), 'The film card keeps its title, year and link.' );
entity_assert( attr( $card, 'loading' ) === 'lazy' && attr( $card, 'decoding' ) === 'async', 'An index poster is lazy.' );
entity_assert( attr( $card, 'sizes' ) === 'auto, ' . lunara_image_sizes_for( 'entity-card' ), 'A lazy index poster keeps auto in front of the measured card sizes (got "' . attr( $card, 'sizes' ) . '").' );
entity_assert( widths( $card ) === $card_widths, 'A 2000px poster offers every 2:3 crop width from 170 to 680 (got ' . implode( '/', widths( $card ) ) . ').' );
entity_assert( false !== strpos( attr( $card, 'srcset' ), 'resize=170%2C255' ) && false !== strpos( attr( $card, 'srcset' ), 'resize=680%2C1020' ), 'Each candidate is a crop of the original at the frame ratio.' );
entity_assert( false !== strpos( attr( $card, 'src' ), 'resize=340%2C510' ), 'The src falls back to the 340px crop.' );
entity_assert( false === strpos( $card, 'background-image' ), 'A card paints no inline background.' );

// ---- The cap: a 500px upload stops at its own width; an unmeasured one keeps WordPress's srcset ----
$small = lunara_entity_render_film_card( 2 );
entity_assert( widths( $small ) === array( 170, 200, 256, 340, 400, 500 ), 'A 500x750 upload offers crops to 400w and its own 500px crop, never a 512 or 680px upscale (got ' . implode( '/', widths( $small ) ) . ').' );
$unmeasured = lunara_entity_render_film_card( 4 );
entity_assert( attr( $unmeasured, 'sizes' ) === null && widths( $unmeasured ) === array(), 'An upload without measurements offers no candidates rather than crops the CDN would scale up, and carries no sizes for them.' );
$plate = lunara_entity_render_film_card( 5 );
entity_assert( false !== strpos( $plate, 'lunara-entity-card-plate' ) && false === strpos( $plate, '<img' ), 'A record with no poster keeps its typographic plate.' );

// ---- A talent card: the portrait takes the same 2:3 crops ----
$person = lunara_entity_render_person_card( 3 );
entity_assert( false !== strpos( $person, 'is-portrait' ) && widths( $person ) === array( 170, 200, 256, 340, 400, 512, 680 ), 'A 683x1024 portrait in the 2:3 card offers crops to 680px (683 wide, 1024 x 2/3 = 682 tall), none wider (got ' . implode( '/', widths( $person ) ) . ').' );
entity_assert( attr( $person, 'sizes' ) === 'auto, ' . lunara_image_sizes_for( 'entity-card' ), 'The talent card says the same card sizes.' );

// ---- The dossier hero: eager, no auto, the hero sizes ----
$hero = lunara_entity_poster_img( 1, 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high' ), 'entity-hero', $hero_widths, 250 );
entity_assert( attr( $hero, 'loading' ) === 'eager' && attr( $hero, 'fetchpriority' ) === 'high', 'The hero poster is eager with high priority.' );
entity_assert( attr( $hero, 'sizes' ) === lunara_image_sizes_for( 'entity-hero' ) && 0 !== strpos( (string) attr( $hero, 'sizes' ), 'auto' ), 'An eager hero carries the measured hero sizes with no auto (got "' . attr( $hero, 'sizes' ) . '").' );
entity_assert( widths( $hero ) === $hero_widths && false !== strpos( attr( $hero, 'src' ), 'resize=250%2C375' ), 'The hero offers crops from 160 to 500px and falls back to the 250px crop.' );
entity_assert( '' === lunara_entity_poster_img( 5, 'large', array(), 'entity-hero', $hero_widths, 250 ), 'A record with no poster prints no hero picture.' );

// ---- The backdrop: a proxied picture with widths, never the TMDB original ----
$backdrop = lunara_entity_backdrop_img( 'https://image.tmdb.org/t/p/original/abc123.jpg' );
entity_assert( 0 === strpos( $backdrop, '<img ' ) && false === strpos( $backdrop, 'background' ), 'The backdrop is a picture.' );
entity_assert( widths( $backdrop ) === array( 640, 960, 1280, 1600, 1920 ) && attr( $backdrop, 'sizes' ) === '100vw', 'The backdrop offers 640-1920px widths of the proxied original for the full-width band.' );
entity_assert( false !== strpos( attr( $backdrop, 'src' ), 'i0.wp.com/image.tmdb.org/t/p/original/abc123.jpg' ) && false !== strpos( attr( $backdrop, 'src' ), 'w=1280' ), 'The src is the 1280px proxied file, not the original.' );
entity_assert( attr( $backdrop, 'loading' ) === 'eager' && attr( $backdrop, 'fetchpriority' ) === 'low' && attr( $backdrop, 'alt' ) === '', 'The backdrop loads with the hero at low priority and is decorative.' );
entity_assert( '' === lunara_entity_backdrop_img( '' ), 'No backdrop URL, no picture.' );

// ---- Source facts: the templates print no inline backgrounds ----
foreach ( array( 'single-movie.php', 'single-person.php', 'inc/entity-surfaces.php' ) as $file ) {
	entity_assert( false === strpos( (string) file_get_contents( dirname( __DIR__ ) . '/' . $file ), 'background-image' ), $file . ' prints no inline background-image.' );
}

echo "entity-surfaces-posters-runtime: {$checks} checks passed.\n";
