<?php
/**
 * Theme 3.2.151: every public, anonymous, cacheable page carries a five-minute
 * cache lifetime, so the WordPress.com edge never serves day-old HTML after a
 * deploy; anything that already set Cache-Control keeps its own.
 *
 * Run: php tests/public-cache-lifetime-runtime.php
 */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$checks = 0;
function lifetime_assert( $condition, $message ) { global $checks; ++$checks; if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
function add_action() {}

function lifetime_extract( $file, $name ) {
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
lifetime_extract( 'inc/frontend.php', 'lunara_public_page_cache_lifetime_value' );

$lifetime = 'Cache-Control: max-age=300, must-revalidate';
lifetime_assert( $lifetime === lunara_public_page_cache_lifetime_value( array() ), 'An anonymous public page gets the five-minute lifetime.' );
foreach ( array( 'admin', 'headers_sent', 'logged_in', 'preview', 'customize_preview', 'feed', 'not_found', 'do_not_cache', 'preview_query' ) as $flag ) {
	lifetime_assert( '' === lunara_public_page_cache_lifetime_value( array( $flag => true ) ), "No lifetime header when {$flag} is true." );
}
lifetime_assert( '' === lunara_public_page_cache_lifetime_value( array( 'headers' => array( 'X-Other: 1', 'cache-control: private, no-store' ) ) ), 'An existing Cache-Control header is kept, whatever its case.' );
lifetime_assert( $lifetime === lunara_public_page_cache_lifetime_value( array( 'headers' => array( 'X-Other: 1' ) ) ), 'Unrelated headers do not block the lifetime.' );

$frontend = file_get_contents( dirname( __DIR__ ) . '/inc/frontend.php' );
lifetime_assert( 1 === preg_match( "/add_action\( 'template_redirect', 'lunara_public_page_cache_lifetime_header', 1000 \);/", $frontend ), 'The header runs late on template_redirect, after anything that set its own Cache-Control.' );
foreach ( array( 'is_admin()', 'headers_sent()', 'is_user_logged_in()', 'is_preview()', 'is_customize_preview()', 'is_feed()', 'is_404()', "defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE", "\$_GET['preview']", 'headers_list()' ) as $fact ) {
	lifetime_assert( false !== strpos( $frontend, $fact ), "The hook reads {$fact} from the request." );
}

echo "Public cache lifetime: {$checks} checks passed.\n";
