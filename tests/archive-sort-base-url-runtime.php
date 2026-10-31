<?php
/**
 * Behavior contract for the archive sort base URL (3.2.143,
 * lunara_archive_sort_base_url() in inc/helpers.php).
 *
 * The Reviews and Journal sort links and the Reviews year filter build on
 * the current archive URL. get_pagenum_link() escapes its URL by default
 * (& becomes &#038;), and core's add_query_arg() splits a URL at its first
 * "#", so "#038;..." became a fragment: on a page with two query parameters,
 * every sort link lost the second one (found live on /reviews/, 2026-10-31).
 *
 * The stubs below follow core: get_pagenum_link() escapes unless told not
 * to, and add_query_arg()/remove_query_arg() keep core's fragment rule.
 *
 * Run: php tests/archive-sort-base-url-runtime.php
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$checks = 0;
function lunara_test_assert( $condition, string $message ): void {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$GLOBALS['lunara_test_request'] = '';

/** Core: the URL of a page of the current archive, escaped unless $escape is false. */
function get_pagenum_link( $pagenum = 1, $escape = true ) {
	$url = $GLOBALS['lunara_test_request'];
	return $escape ? str_replace( '&', '&#038;', $url ) : $url;
}

/** Core's add_query_arg(), with its fragment rule (wp-includes/functions.php). */
function add_query_arg( ...$args ) {
	$uri  = is_array( $args[0] ) ? (string) $args[1] : (string) $args[2];
	$frag = strstr( $uri, '#' );
	if ( $frag ) {
		$uri = substr( $uri, 0, -strlen( $frag ) );
	} else {
		$frag = '';
	}
	$protocol = '';
	if ( 0 === stripos( $uri, 'https://' ) ) {
		$protocol = 'https://';
		$uri      = substr( $uri, 8 );
	}
	if ( false !== strpos( $uri, '?' ) ) {
		list( $base, $query ) = explode( '?', $uri, 2 );
		$base                .= '?';
	} else {
		$base  = $uri . '?';
		$query = '';
	}
	parse_str( $query, $qs );
	$qs = array_map( 'urlencode', $qs );
	foreach ( is_array( $args[0] ) ? $args[0] : array( $args[0] => $args[1] ) as $key => $value ) {
		$qs[ $key ] = $value;
	}
	$pairs = array();
	foreach ( $qs as $key => $value ) {
		if ( false !== $value ) {
			$pairs[] = $key . '=' . $value;
		}
	}
	$ret = $protocol . $base . trim( implode( '&', $pairs ), '?' ) . $frag;
	return str_replace( '?#', '#', rtrim( $ret, '?' ) );
}
function remove_query_arg( $key, $query = false ) {
	foreach ( (array) $key as $k ) {
		$query = add_query_arg( $k, false, $query );
	}
	return $query;
}

require dirname( __DIR__ ) . '/inc/archive-urls.php';

/** The query of a link as a visitor's browser reads it, and its fragment. */
function lunara_test_link( string $url ): array {
	$parts = parse_url( $url );
	parse_str( $parts['query'] ?? '', $query );
	return array( $query, $parts['fragment'] ?? '' );
}

// A Reviews page with a year filter, a campaign tag, a sort and a page.
$GLOBALS['lunara_test_request'] = 'https://lunarafilm.com/reviews/?review_year=2024&utm_source=letterboxd&sort=title_asc&paged=3';

// The live defect, reproduced with the escaped URL: the sort link keeps only
// the first parameter, and the rest falls into the fragment.
$old = add_query_arg( 'sort', 'release_asc', remove_query_arg( array( 'sort', 'paged' ), get_pagenum_link( 1 ) ) );
list( $old_query, $old_fragment ) = lunara_test_link( $old );
lunara_test_assert( ! isset( $old_query['utm_source'] ) && '' !== $old_fragment, 'with the escaped URL, the second parameter falls into the fragment (the live defect)' );

$base = lunara_archive_sort_base_url();
list( $base_query, $base_fragment ) = lunara_test_link( $base );
lunara_test_assert( array( 'review_year' => '2024', 'utm_source' => 'letterboxd' ) === $base_query && '' === $base_fragment, 'the base keeps every other parameter and drops the sort and the page' );
lunara_test_assert( false === strpos( $base, '&#038;' ), 'the base is unescaped; each link escapes it when it prints' );

list( $link_query, $link_fragment ) = lunara_test_link( add_query_arg( 'sort', rawurlencode( 'release_asc' ), $base ) );
lunara_test_assert( array( 'review_year' => '2024', 'utm_source' => 'letterboxd', 'sort' => 'release_asc' ) === $link_query && '' === $link_fragment, 'a sort link keeps the year filter and every other parameter' );

list( $filter_query ) = lunara_test_link( lunara_archive_sort_base_url( array( 'review_year' ) ) );
lunara_test_assert( array( 'utm_source' => 'letterboxd' ) === $filter_query, "the year filter's form action drops its own key too" );

// The plain archive: no parameters, no question mark.
$GLOBALS['lunara_test_request'] = 'https://lunarafilm.com/journal/';
lunara_test_assert( 'https://lunarafilm.com/journal/' === lunara_archive_sort_base_url(), 'a plain archive keeps its plain URL' );
lunara_test_assert( 'https://lunarafilm.com/journal/?sort=date_asc' === add_query_arg( 'sort', 'date_asc', lunara_archive_sort_base_url() ), 'a sort link on a plain archive adds only the sort' );

fwrite( STDOUT, "Archive sort base URL runtime passed: {$checks} checks.\n" );
