<?php
/**
 * Behavior contract for the Debrief poster by IMDb id (3.2.125).
 *
 * A pairing's poster comes from what the site already holds first — the TMDB
 * poster hydrated on a published Lunara review of the film, that review's
 * featured image, the movie entity's poster meta or featured image — then the
 * Oscars Ledger, then TMDB. A failed TMDB request is never remembered as "no
 * poster": before this, a five-second blip left a blank plate under a film
 * for seven days.
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'AAT_TMDB_API_KEY', 'test-key' );

$GLOBALS['lunara_test'] = array();

function lunara_test_reset(): void {
	$GLOBALS['lunara_test'] = array(
		'posts'      => array(), // type => [ [id, meta] ]
		'thumbs'     => array(),
		'transients' => array(),
		'set'        => array(),
		'remote'     => array( 'error' => false, 'code' => 200, 'body' => '{}' ),
		'requests'   => 0,
		'gateway'    => new WP_Error(),
		'gateway_calls' => 0,
	);
}

function lunara_test_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

class WP_Error { public function get_error_code() { return 'lunara_test_error'; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function __( $t, $d = null ) { return $t; }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES, 'UTF-8' ); }
function post_type_exists( $type ) { return in_array( $type, array( 'review', 'movie' ), true ); }
function get_posts( $args ) {
	foreach ( $GLOBALS['lunara_test']['posts'][ $args['post_type'] ] ?? array() as $row ) {
		if ( ( $row['meta'][ $args['meta_key'] ] ?? null ) === $args['meta_value'] ) {
			return array( $row['id'] );
		}
	}
	return array();
}
function get_post_meta( $id, $key, $single = false ) {
	foreach ( $GLOBALS['lunara_test']['posts'] as $rows ) {
		foreach ( $rows as $row ) {
			if ( $row['id'] === $id ) {
				return $row['meta'][ $key ] ?? '';
			}
		}
	}
	return '';
}
function get_the_post_thumbnail_url( $id, $size = 'post-thumbnail' ) { return $GLOBALS['lunara_test']['thumbs'][ $id ] ?? ''; }
function get_transient( $key ) { return $GLOBALS['lunara_test']['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['lunara_test']['transients'][ $key ] = $value; $GLOBALS['lunara_test']['set'][] = array( $key, $value, $ttl ); return true; }
function wp_remote_get( $url, $args = array() ) {
	++$GLOBALS['lunara_test']['requests'];
	$r = $GLOBALS['lunara_test']['remote'];
	return $r['error'] ? new WP_Error() : array( 'response' => array( 'code' => $r['code'] ), 'body' => $r['body'] );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? (int) $r['response']['code'] : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? (string) $r['body'] : ''; }

// Load the real functions without the file's bootstrap hooks.
function lunara_test_load_function( string $name ): void {
	$tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/inc/debrief.php' ) );
	foreach ( $tokens as $i => $token ) {
		if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) { continue; }
		$j = $i + 1;
		while ( isset( $tokens[ $j ] ) && ( ! is_array( $tokens[ $j ] ) || T_STRING !== $tokens[ $j ][0] ) ) { ++$j; }
		if ( ( $tokens[ $j ][1] ?? '' ) !== $name ) { continue; }
		$code = ''; $depth = 0; $opened = false;
		for ( $k = $i; $k < count( $tokens ); ++$k ) {
			$part  = is_array( $tokens[ $k ] ) ? $tokens[ $k ][1] : $tokens[ $k ];
			$code .= $part;
			if ( '{' === $part || ( is_array( $tokens[ $k ] ) && in_array( $tokens[ $k ][0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) { ++$depth; $opened = true; }
			elseif ( '}' === $part && 0 === --$depth && $opened ) { eval( $code ); return; }
		}
	}
	throw new RuntimeException( 'Missing function: ' . $name );
}
// Lunara Core's gateway, as the theme sees it: configurable answers, counted.
// On a public page Core has not loaded the class, so the theme must load it
// from LUNARA_CORE_DIR itself; this stand-in file plays that part.
$lunara_test_core_dir = sys_get_temp_dir() . '/lunara-core-' . getmypid() . '/';
@mkdir( $lunara_test_core_dir . 'includes', 0777, true );
file_put_contents( $lunara_test_core_dir . 'includes/class-lunara-movie-provider-gateway.php', <<<'PHP'
<?php
final class Lunara_Movie_Provider_Gateway {
	public function credentials_status() { return array( 'omdb' => true, 'tmdb' => true, 'ready' => true ); }
	public function get_artwork_by_imdb( $tt ) {
		++$GLOBALS['lunara_test']['gateway_calls'];
		$answer = $GLOBALS['lunara_test']['gateway'] ?? null;
		return is_callable( $answer ) ? $answer( $tt ) : $answer;
	}
}
PHP
);
define( 'LUNARA_CORE_DIR', $lunara_test_core_dir );
function current_user_can( $cap ) { return ! empty( $GLOBALS['lunara_test']['editor'] ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
lunara_test_load_function( 'lunara_title_poster_trace_enabled' );
lunara_test_load_function( 'lunara_title_poster_trace' );
lunara_test_load_function( 'lunara_title_poster_trace_print' );
lunara_test_load_function( 'lunara_poster_html_with_sizes' );
lunara_test_load_function( 'lunara_get_title_poster_url_from_site' );
lunara_test_load_function( 'lunara_get_title_poster_html' );

$tmdb_body = json_encode( array( 'movie_results' => array( array( 'title' => 'Rustin', 'poster_path' => '/rustin.jpg' ) ) ) );

// ---- The site's own poster comes first: a Lunara review of the film ----
lunara_test_reset();
$GLOBALS['lunara_test']['posts']['review'] = array( array( 'id' => 900, 'meta' => array( '_lunara_imdb_title_id' => 'tt13959828', '_lunara_tmdb_poster_url' => 'https://image.tmdb.org/t/p/original/rustin-review.jpg' ) ) );
$GLOBALS['lunara_test']['remote']['body'] = $tmdb_body;
$html = lunara_get_title_poster_html( 'tt13959828', 'medium', 'poster', 'Rustin' );
lunara_test_assert( false !== strpos( $html, 'src="https://image.tmdb.org/t/p/original/rustin-review.jpg"' ) && false !== strpos( $html, 'alt="Rustin poster"' ) && 0 === $GLOBALS['lunara_test']['requests'], 'a Lunara review of the film supplies the poster and no request leaves the site' );

// ---- Then the review's featured image, then the movie entity ----
lunara_test_reset();
$GLOBALS['lunara_test']['posts']['review'] = array( array( 'id' => 900, 'meta' => array( '_lunara_imdb_title_id' => 'tt13959828' ) ) );
$GLOBALS['lunara_test']['thumbs'][900]     = 'https://lunara.test/wp-content/uploads/rustin-thumb.jpg';
lunara_test_assert( false !== strpos( lunara_get_title_poster_html( 'tt13959828' ), 'rustin-thumb.jpg' ), 'the review featured image stands in when no TMDB poster is hydrated' );
lunara_test_reset();
$GLOBALS['lunara_test']['posts']['movie'] = array( array( 'id' => 501, 'meta' => array( 'imdb_title_id' => 'tt13959828', 'tmdb_poster_url' => 'https://image.tmdb.org/t/p/original/rustin-movie.jpg' ) ) );
lunara_test_assert( false !== strpos( lunara_get_title_poster_html( 'tt13959828' ), 'rustin-movie.jpg' ) && 0 === $GLOBALS['lunara_test']['requests'], 'the movie entity poster stands in, still without a request' );

// ---- Lunara Core's gateway answers before the theme's own TMDB call ----
lunara_test_reset();
lunara_test_assert( ! class_exists( 'Lunara_Movie_Provider_Gateway', false ), 'on a public page the gateway class is not loaded before the theme asks' );
$GLOBALS['lunara_test']['gateway'] = array( 'title' => 'She Said', 'poster_path' => '/shesaid.jpg' );
$html = lunara_get_title_poster_html( 'tt14807308' );
lunara_test_assert( class_exists( 'Lunara_Movie_Provider_Gateway', false ), 'the theme loads the gateway class from LUNARA_CORE_DIR' );
lunara_test_assert( false !== strpos( $html, 'src="https://image.tmdb.org/t/p/w500/shesaid.jpg"' ) && false !== strpos( $html, 'alt="She Said poster"' ) && 1 === $GLOBALS['lunara_test']['gateway_calls'] && 0 === $GLOBALS['lunara_test']['requests'], 'the Core gateway supplies the poster and the title; the theme makes no request of its own' );
lunara_get_title_poster_html( 'tt14807308' );
lunara_test_assert( 1 === $GLOBALS['lunara_test']['gateway_calls'], 'a gateway answer is remembered, so the gateway is asked once' );
lunara_test_reset();
$GLOBALS['lunara_test']['gateway'] = new WP_Error(); // not configured, circuit open, not found…
$GLOBALS['lunara_test']['remote']['body'] = $tmdb_body;
$html = lunara_get_title_poster_html( 'tt13959828' );
lunara_test_assert( false !== strpos( $html, '/rustin.jpg' ) && 1 === $GLOBALS['lunara_test']['gateway_calls'] && 1 === $GLOBALS['lunara_test']['requests'], 'when the gateway has no answer the theme falls through to its own TMDB call' );

// ---- TMDB answers when the site holds nothing; the answer is cached a week ----
lunara_test_reset();
$GLOBALS['lunara_test']['remote']['body'] = $tmdb_body;
$html = lunara_get_title_poster_html( 'tt13959828', 'medium', 'poster', '' );
lunara_test_assert( false !== strpos( $html, 'src="https://image.tmdb.org/t/p/w500/rustin.jpg"' ) && false !== strpos( $html, 'alt="Rustin poster"' ) && 1 === $GLOBALS['lunara_test']['requests'], 'TMDB supplies the poster and the title when the site holds nothing' );
lunara_test_assert( 1 === count( $GLOBALS['lunara_test']['set'] ) && 7 * DAY_IN_SECONDS === $GLOBALS['lunara_test']['set'][0][2], 'a found poster is remembered for a week' );
lunara_get_title_poster_html( 'tt13959828' );
lunara_test_assert( 1 === $GLOBALS['lunara_test']['requests'], 'the remembered poster is served without another request' );

// ---- A failed request is not an answer and is never remembered ----
lunara_test_reset();
$GLOBALS['lunara_test']['remote'] = array( 'error' => true, 'code' => 0, 'body' => '' );
lunara_test_assert( '' === lunara_get_title_poster_html( 'tt13959828' ) && array() === $GLOBALS['lunara_test']['set'], 'a network error yields no poster now and nothing is cached' );
$GLOBALS['lunara_test']['remote'] = array( 'error' => false, 'code' => 429, 'body' => '{"status_message":"rate limited"}' );
lunara_test_assert( '' === lunara_get_title_poster_html( 'tt13959828' ) && array() === $GLOBALS['lunara_test']['set'], 'a non-200 answer is not cached either' );
$GLOBALS['lunara_test']['remote'] = array( 'error' => false, 'code' => 200, 'body' => $tmdb_body );
lunara_test_assert( false !== strpos( lunara_get_title_poster_html( 'tt13959828' ), '/rustin.jpg' ), 'the next request after a blip finds the poster' );

// ---- A genuine "no poster" is remembered briefly, not for a week ----
lunara_test_reset();
$GLOBALS['lunara_test']['remote']['body'] = json_encode( array( 'movie_results' => array() ) );
lunara_test_assert( '' === lunara_get_title_poster_html( 'tt13959828' ) && 1 === count( $GLOBALS['lunara_test']['set'] ) && '' === $GLOBALS['lunara_test']['set'][0][1] && 6 * HOUR_IN_SECONDS === $GLOBALS['lunara_test']['set'][0][2], 'a real empty answer is remembered for six hours' );

// ---- The editor-only trace names every step a plate went through ----
lunara_test_reset();
$_GET['lunara_poster_trace'] = '1';
$GLOBALS['lunara_test']['editor'] = true;
$GLOBALS['lunara_test']['gateway'] = new WP_Error();
$GLOBALS['lunara_test']['remote'] = array( 'error' => false, 'code' => 200, 'body' => json_encode( array( 'movie_results' => array() ) ) );
lunara_get_title_poster_html( 'tt14807308' );
$steps = array_map( static function ( $row ) { return $row[1] . '=' . $row[2]; }, $GLOBALS['lunara_title_poster_trace'] ?? array() );
lunara_test_assert( in_array( 'site=none', $steps, true ) && in_array( 'gateway=class loaded', $steps, true ) && in_array( 'gateway credentials=tmdb=yes', $steps, true ) && in_array( 'gateway error=lunara_test_error', $steps, true ) && in_array( 'theme tmdb=key constant defined', $steps, true ), 'the trace records the site, gateway and theme steps for an editor who asked for it: ' . implode( ' / ', $steps ) );
ob_start(); lunara_title_poster_trace_print(); $printed = ob_get_clean();
lunara_test_assert( false !== strpos( $printed, '<!-- lunara-title-poster-trace' ) && false !== strpos( $printed, 'tt14807308 | site | none' ), 'the trace prints as one footer comment' );
unset( $_GET['lunara_poster_trace'] );

// ---- Only real IMDb ids are looked up ----
lunara_test_reset();
lunara_test_assert( '' === lunara_get_title_poster_html( 'nope' ) && 0 === $GLOBALS['lunara_test']['requests'], 'a malformed id gets no lookup' );

fwrite( STDOUT, "Title poster: site first, TMDB answers cached, failures never remembered.\n" );
