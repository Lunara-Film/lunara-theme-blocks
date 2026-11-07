<?php
/**
 * Theme 3.2.154/3.2.155: the legacy title-poster builder prints 2:3 crops
 * with the pairing width when its caller names a width, and the image as it
 * was given when none does. The real functions run from inc/debrief.php.
 *
 * Run: php tests/debrief-poster-candidates-runtime.php
 */
$checks = 0;
function poster_assert( $condition, $message ) { global $checks; ++$checks; if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }

function esc_url( $url ) { return str_replace( '&', '&#038;', (string) $url ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
function __( $text, $domain = null ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function home_url( $path = '' ) { return 'https://lunarafilm.com/' . ltrim( (string) $path, '/' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function remove_query_arg( $keys, $url ) { $parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $query ); foreach ( (array) $keys as $key ) { unset( $query[ $key ] ); } return strtok( $url, '?' ) . ( $query ? '?' . http_build_query( $query ) : '' ); }
function add_query_arg( $args, $value = null, $url = null ) { if ( ! is_array( $args ) ) { $args = array( $args => $value ); } else { $url = $value; } $parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $query ); return strtok( $url, '?' ) . '?' . http_build_query( array_merge( $query, $args ) ); }
function lunara_title_poster_trace( $tt, $step, $note = '' ) {}
function lunara_get_title_poster_url_from_site( $tt ) { return $GLOBALS['poster_site'][ $tt ] ?? ''; }

function poster_extract( $file, $name ) {
	$tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/' . $file ) );
	foreach ( $tokens as $i => $token ) {
		if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) { continue; }
		$j = $i + 1; while ( isset( $tokens[ $j ] ) && ( ! is_array( $tokens[ $j ] ) || T_STRING !== $tokens[ $j ][0] ) ) { ++$j; }
		if ( ( $tokens[ $j ][1] ?? '' ) !== $name ) { continue; }
		$code = ''; $depth = 0; $opened = false;
		for ( $k = $i; $k < count( $tokens ); ++$k ) {
			$part = is_array( $tokens[ $k ] ) ? $tokens[ $k ][1] : $tokens[ $k ]; $code .= $part;
			if ( '{' === $part ) { ++$depth; $opened = true; } elseif ( '}' === $part && 0 === --$depth && $opened ) { eval( $code ); return; }
		}
	}
	throw new RuntimeException( 'Missing function ' . $name );
}
foreach ( array( 'lunara_resize_wpcom_image_url', 'lunara_image_cdn_source', 'lunara_image_cdn_crop_url', 'lunara_image_source_size_from_url', 'lunara_image_upload_size', 'lunara_image_cdn_crop_limit', 'lunara_image_cdn_crop_srcset', 'lunara_image_sizes_for' ) as $name ) { poster_extract( 'inc/setup.php', $name ); }
foreach ( array( 'lunara_poster_html_with_sizes', 'lunara_get_title_poster_html' ) as $name ) { poster_extract( 'inc/debrief.php', $name ); }

$GLOBALS['poster_site'] = array( 'tt1285016' => 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/03/tt1285016-poster.jpg?fit=683%2C1024&quality=82&ssl=1' );

// A pairing card names its width: 2:3 crops at 96-384px, the pairing sizes after auto, the 256px crop as src.
$card = lunara_get_title_poster_html( 'tt1285016', 'medium', 'lunara-pair-preview-thumb', 'She Said', 'lazy', lunara_image_sizes_for( 'pair-card' ) );
poster_assert( 1 === preg_match( '#^<img class="lunara-pair-preview-thumb" src="[^"]*resize=256%2C384[^"]*" srcset="([^"]*)" sizes="auto, \(max-width: 640px\) 96px, 128px" alt="She Said poster" loading="lazy" decoding="async" data-no-lazy="1" data-skip-lazy="1">$#', $card, $m ), 'A pairing poster prints its candidates, the pairing widths and the 256px crop as src (got ' . $card . ').' );
$widths = array_map( static function ( $candidate ) { return (int) substr( strrchr( trim( $candidate ), ' ' ), 1 ); }, explode( ',', $m[1] ) );
poster_assert( array( 96, 128, 192, 256, 384 ) === $widths, 'The candidates are the five pairing widths, all within the 683px source (got ' . implode( ',', $widths ) . ').' );
poster_assert( 5 === preg_match_all( '/resize=(\d+)%2C(\d+)/', $m[1], $pairs ) && array_sum( array_map( static function ( $w, $h ) { return abs( (int) $h - round( (int) $w * 1.5 ) ) <= 1 ? 0 : 1; }, $pairs[1], $pairs[2] ) ) === 0, 'Every candidate is a 2:3 crop.' );

// An eager caller keeps no auto.
$eager = lunara_get_title_poster_html( 'tt1285016', 'medium', 'lunara-pair-preview-thumb', 'She Said', 'eager', '(max-width: 640px) 96px, 128px' );
poster_assert( false !== strpos( $eager, 'sizes="(max-width: 640px) 96px, 128px"' ) && false !== strpos( $eager, 'loading="eager"' ), 'An eager poster says its widths with no auto.' );

// No width named: the image prints as it was given, one file, no candidates.
$plain = lunara_get_title_poster_html( 'tt1285016', 'medium', 'lunara-debrief-thumb', 'She Said', 'lazy' );
poster_assert( false === strpos( $plain, 'srcset=' ) && false === strpos( $plain, 'sizes=' ) && false !== strpos( $plain, 'src="https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/03/tt1285016-poster.jpg?fit=683%2C1024' ), 'A caller that names no width gets the poster exactly as it was given (got ' . $plain . ').' );

// A host the CDN cannot resize prints as given even with a width named.
$GLOBALS['poster_site']['tt0000001'] = 'https://example.org/poster.jpg';
$remote = lunara_get_title_poster_html( 'tt0000001', 'medium', 'lunara-pair-preview-thumb', 'Elsewhere', 'lazy', '(max-width: 640px) 96px, 128px' );
poster_assert( false === strpos( $remote, 'srcset=' ) && false !== strpos( $remote, 'src="https://example.org/poster.jpg"' ), 'A remote poster keeps its one URL.' );

echo "Debrief poster candidates: {$checks} checks passed.\n";
