<?php
/**
 * Theme 3.2.152/3.2.154: the prediction board's art names its width
 * candidates. Each is a CDN crop to the 2:3 frame; none is wider than the
 * widest crop the source can give (the CDN scales a crop up otherwise); the
 * board widths ride in `sizes`; a host the CDN cannot resize is printed as
 * given. The real renderer runs through the board harness.
 *
 * Run: php tests/oscars-board-art-runtime.php
 */
$checks = 0;
function board_art_assert( $condition, $message ) { global $checks; ++$checks; if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }

function home_url( $path = '' ) { return 'https://lunarafilm.com/' . ltrim( (string) $path, '/' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function remove_query_arg( $keys, $url ) { $parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $query ); foreach ( (array) $keys as $key ) { unset( $query[ $key ] ); } return strtok( $url, '?' ) . ( $query ? '?' . http_build_query( $query ) : '' ); }
function add_query_arg( $args, $value = null, $url = null ) { if ( ! is_array( $args ) ) { $args = array( $args => $value ); } else { $url = $value; } $parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $query ); return strtok( $url, '?' ) . '?' . http_build_query( array_merge( $query, $args ) ); }

require __DIR__ . '/fixtures/oscars-portal-board-harness.php';

foreach ( array( 'lunara_resize_wpcom_image_url', 'lunara_image_cdn_source', 'lunara_image_cdn_crop_url', 'lunara_image_source_size_from_url', 'lunara_image_upload_size', 'lunara_image_cdn_crop_limit', 'lunara_image_cdn_crop_srcset', 'lunara_image_sizes_for' ) as $name ) {
	eval( lunara_extract_named_function( $root . '/inc/setup.php', $name, $name ) );
}

$picks = array(
	401 => array( 'category' => 'Best Picture', 'film' => 'One Battle after Another', 'person' => '', 'status' => 'front_runner', 'url' => 'https://example.test/oscars/title/tt30144839/', 'year' => 2027, 'title' => 'Pick 401' ),
	402 => array( 'category' => 'Directing', 'film' => 'The Odyssey', 'person' => 'Christopher Nolan', 'status' => 'contender', 'url' => 'https://example.test/oscars/name/nm0634240/', 'year' => 2027, 'title' => 'Pick 402' ),
	403 => array( 'category' => 'Cinematography', 'film' => 'Sinners', 'person' => '', 'status' => 'watchlist', 'url' => '', 'year' => 2027, 'title' => 'Pick 403' ),
);
$html = lunara_board_render_with_picks(
	$picks,
	array(
		// A 1920x1280 still the caller measured: every width stays; nothing joins above the list.
		401 => array( 'src' => 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/07/still.jpg?fit=768%2C512&quality=82&ssl=1', 'kind' => 'photo', 'width' => 1920, 'height' => 1280 ),
		// A 640x385 still with no measured size: the URL's fit= caps the crops at 256px.
		402 => array( 'src' => 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/08/portrait.jpg?fit=640%2C385&quality=82&ssl=1', 'kind' => 'portrait' ),
		// A host the CDN cannot resize is printed as given.
		403 => array( 'src' => 'https://example.org/poster.jpg', 'kind' => 'poster' ),
	)
);
preg_match_all( '/<span class="lunara-oscars-board-art" aria-hidden="true">(<img[^>]*>)<\/span>/', $html, $arts );
board_art_assert( 3 === count( $arts[1] ), 'Every pick with art prints one art image (got ' . count( $arts[1] ) . ').' );
list( $measured, $capped, $remote ) = $arts[1];

board_art_assert( 1 === preg_match( '/^<img src="[^"]*resize=352%2C528[^"]*" srcset="([^"]*)" sizes="auto, \(max-width: 600px\) 96px, \(max-width: 640px\) 275px, \(max-width: 820px\) 340px, \(max-width: 1200px\) 320px, 256px" alt="" loading="lazy" decoding="async" \/>$/', $measured, $m ), 'The measured still prints a 352px crop as src, the board widths after auto, and no alt text (got ' . $measured . ').' );
$widths = array_map( static function ( $candidate ) { return (int) substr( strrchr( trim( $candidate ), ' ' ), 1 ); }, explode( ',', $m[1] ) );
board_art_assert( array( 96, 192, 256, 352, 512 ) === $widths, 'A 1920x1280 still keeps every width the board asks for (96-512px; 3.2.155 dropped 704) and gains nothing above it (got ' . implode( ',', $widths ) . ').' );
board_art_assert( 1 === preg_match( '/resize=96%2C144[^ ]* 96w/', $m[1] ) && 1 === preg_match( '/resize=512%2C768[^ ]* 512w/', $m[1] ) && 5 === preg_match_all( '/resize=(\d+)%2C(\d+)/', $m[1], $pairs ), 'Every candidate is a 2:3 crop of the source.' );
foreach ( $pairs[1] as $i => $w ) { board_art_assert( abs( (int) $pairs[2][ $i ] - round( (int) $w * 1.5 ) ) <= 1, "Candidate {$w} is cropped 2:3 (height {$pairs[2][$i]})." ); }

board_art_assert( 1 === preg_match( '/srcset="([^"]*)"/', $capped, $c ), 'The uncapped-by-caller still has candidates.' );
$capped_widths = array_map( static function ( $candidate ) { return (int) substr( strrchr( trim( $candidate ), ' ' ), 1 ); }, explode( ',', $c[1] ) );
board_art_assert( array( 96, 192, 256 ) === $capped_widths && false !== strpos( $capped, 'src="https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/08/portrait.jpg?quality=86&#038;ssl=1&#038;resize=256%2C384"' ), 'A 640x385 still stops at its 256px native crop (no 352-704px upscales) and prints the widest kept crop as src (got ' . $capped . ').' );

board_art_assert( '<img src="https://example.org/poster.jpg" alt="" loading="lazy" decoding="async" />' === $remote, 'A host the CDN cannot resize is printed exactly as given (got ' . $remote . ').' );
board_art_assert( false === strpos( $html, 'style="background' ), 'The board prints no inline backgrounds.' );

echo "Oscars board art: {$checks} checks passed.\n";
