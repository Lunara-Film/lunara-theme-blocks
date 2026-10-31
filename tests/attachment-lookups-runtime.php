<?php
/**
 * Behavior contract for attachment lookups (3.2.143, inc/attachment-lookups.php).
 *
 * lunara_attachment_id_for_url() must give attachment_url_to_postid()'s
 * answer and skip its slow scan where the answer is known:
 *
 * - a URL outside the uploads (TMDB, the image CDN, a relative path) is 0
 *   without asking core, which is what core's scan finds for it here;
 * - a URL in the uploads gets core's answer once, then the object cache's,
 *   until any post changes (an upload moves the posts' last-changed time).
 *
 * Run: php tests/attachment-lookups-runtime.php
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$checks = 0;
function lunara_test_assert( $condition, string $message ): void {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

// The media library core reads: relative file paths, as every sideload stores them.
$GLOBALS['lunara_test_files'] = array( '2026/07/backrooms-still.jpg' => 101, '2025/03/tony-poster.jpg' => 202 );
$GLOBALS['lunara_test_core']  = array();
$GLOBALS['lunara_test_cache'] = array();
$GLOBALS['lunara_test_last']  = '1000.1';

function wp_get_upload_dir() { return array( 'baseurl' => 'https://lunarafilm.com/wp-content/uploads' ); }
/** Core's lookup: strip the uploads base (scheme forced to match), then match the stored path. */
function attachment_url_to_postid( $url ) {
	$GLOBALS['lunara_test_core'][] = $url;
	$path = preg_replace( '#^http:#', 'https:', $url );
	$base = 'https://lunarafilm.com/wp-content/uploads/';
	if ( 0 === strpos( $path, $base ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	return $GLOBALS['lunara_test_files'][ $path ] ?? 0;
}
function wp_cache_get_last_changed( $group ) { return $GLOBALS['lunara_test_last']; }
function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
	$found = array_key_exists( $group . '|' . $key, $GLOBALS['lunara_test_cache'] );
	return $found ? $GLOBALS['lunara_test_cache'][ $group . '|' . $key ] : false;
}
function wp_cache_set( $key, $value, $group = '', $expire = 0 ) {
	$GLOBALS['lunara_test_cache'][ $group . '|' . $key ] = $value;
	return true;
}

require dirname( __DIR__ ) . '/inc/attachment-lookups.php';

/** Run one lookup; return its answer and whether core was asked. */
function lunara_test_lookup( string $url ): array {
	$asked = count( $GLOBALS['lunara_test_core'] );
	$id    = lunara_attachment_id_for_url( $url );
	return array( $id, count( $GLOBALS['lunara_test_core'] ) > $asked );
}

// URLs that cannot be attachments here: the answer core's scan gives, without the scan.
foreach ( array(
	'https://image.tmdb.org/t/p/w342/abc123.jpg'                               => 'a TMDB poster',
	'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/07/backrooms-still.jpg' => 'an image CDN copy of an upload',
	'/wp-content/uploads/2026/07/backrooms-still.jpg'                         => 'a relative path',
	'https://lunarafilm.com/wp-content/themes/x/still.jpg'                     => 'a theme file',
	''                                                                         => 'an empty URL',
) as $url => $what ) {
	list( $id, $asked ) = lunara_test_lookup( $url );
	lunara_test_assert( 0 === $id && false === $asked, "{$what} is not an attachment, and core is not asked" );
	if ( '' !== $url ) {
		lunara_test_assert( 0 === attachment_url_to_postid( $url ), "core's own answer for {$what} is 0 too" );
	}
}

// An upload: core's answer once, then the cache's.
$still = 'https://lunarafilm.com/wp-content/uploads/2026/07/backrooms-still.jpg';
list( $id, $asked ) = lunara_test_lookup( $still );
lunara_test_assert( 101 === $id && true === $asked, 'an upload gets core’s answer the first time' );
list( $id, $asked ) = lunara_test_lookup( $still );
lunara_test_assert( 101 === $id && false === $asked, 'the second lookup comes from the object cache' );
list( $id, $asked ) = lunara_test_lookup( 'http://lunarafilm.com/wp-content/uploads/2025/03/tony-poster.jpg' );
lunara_test_assert( 202 === $id && true === $asked, 'an http upload URL is still an upload' );
list( $id, $asked ) = lunara_test_lookup( '//lunarafilm.com/wp-content/uploads/2025/03/tony-poster.jpg' );
lunara_test_assert( true === $asked, 'a protocol-relative upload URL is still asked of core' );

// A missing upload is kept as 0, until the media library changes.
$new = 'https://lunarafilm.com/wp-content/uploads/2026/10/new-still.jpg';
list( $id, $asked ) = lunara_test_lookup( $new );
lunara_test_assert( 0 === $id && true === $asked, 'an upload URL with no attachment is 0' );
list( $id, $asked ) = lunara_test_lookup( $new );
lunara_test_assert( 0 === $id && false === $asked, 'the miss is kept too' );
$GLOBALS['lunara_test_files']['2026/10/new-still.jpg'] = 303;
$GLOBALS['lunara_test_last']                           = '1000.2'; // The upload moved the posts' last-changed time.
list( $id, $asked ) = lunara_test_lookup( $new );
lunara_test_assert( 303 === $id && true === $asked, 'after an upload, the URL is asked again and finds the new attachment' );

// A deleted attachment: the change moves the time, so the old answer is not reused.
unset( $GLOBALS['lunara_test_files']['2026/07/backrooms-still.jpg'] );
$GLOBALS['lunara_test_last'] = '1000.3';
list( $id, $asked ) = lunara_test_lookup( $still );
lunara_test_assert( 0 === $id && true === $asked, 'after a deletion, the URL is asked again and is no longer an attachment' );

fwrite( STDOUT, "Attachment lookups runtime passed: {$checks} checks.\n" );
