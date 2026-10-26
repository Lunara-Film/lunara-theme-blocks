<?php
/**
 * Behavior contract for Home — Stills that Rhyme (Direction D, 3.2.125): the
 * reviewed film and its three Debrief films, never cut to one pair.
 *
 * Renders inc/home-stills-that-rhyme.php against stubbed WordPress and a
 * stubbed Debrief layer (the exact functions the module calls, with fixture
 * data) and asserts what a visitor gets: two 16:9 frames when both stills
 * exist, Theme Echo preferred and the other roles tried in Method order, the
 * legacy Pair It With desk whenever a still or the pairing is missing, the
 * "Film, Year." captions with the lead's take, the role's Method question,
 * lazy dimensioned images, the partner-still source chain, how the lead
 * review is found, and the pair filter.
 *
 * Run: php tests/home-stills-that-rhyme-runtime.php [--fixture]
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['lunara_test'] = array();

function lunara_test_reset(): void {
	$GLOBALS['lunara_test'] = array(
		'filters'   => array(),
		'posts'     => array(
			// Fictional films: fixture data, not film facts.
			21  => array( 'type' => 'review', 'title' => 'Harbor Light – The Tide Comes In Twice', 'url' => 'https://example.test/reviews/harbor-light/', 'meta' => array( '_lunara_year' => '2026', '_lunara_tmdb_poster_url' => 'https://image.tmdb.org/t/p/original/harbor-poster.jpg' ) ),
			22  => array( 'type' => 'review', 'title' => 'Night Ferry &#8211; Crossing in the Dark', 'url' => 'https://example.test/reviews/night-ferry/', 'meta' => array( '_lunara_year' => '1998' ) ),
			23  => array( 'type' => 'review', 'title' => 'Salt Road', 'url' => 'https://example.test/reviews/salt-road/', 'meta' => array() ),
			31  => array( 'type' => 'journal', 'title' => 'Desk note', 'url' => 'https://example.test/journal/desk-note/', 'meta' => array() ),
			501 => array( 'type' => 'movie', 'title' => 'Night Ferry', 'url' => 'https://example.test/movie/night-ferry/', 'meta' => array( 'release_year' => '1998', 'imdb_title_id' => 'tt1000001', 'tmdb_backdrop_url' => 'https://image.tmdb.org/t/p/original/ferry-backdrop.jpg', 'tmdb_poster_url' => 'https://image.tmdb.org/t/p/original/ferry-poster.jpg' ) ),
			502 => array( 'type' => 'movie', 'title' => 'Salt Road', 'url' => 'https://example.test/movie/salt-road/', 'meta' => array( 'release_year' => '2004', 'imdb_title_id' => 'tt1000002' ) ),
			503 => array( 'type' => 'movie', 'title' => 'Dry Season', 'url' => 'https://example.test/movie/dry-season/', 'meta' => array( 'release_year' => '2011', 'imdb_title_id' => 'tt1000003', 'tmdb_backdrop_url' => 'https://image.tmdb.org/t/p/original/dry-backdrop.jpg' ) ),
		),
		'stills'    => array( 21 => 'https://image.tmdb.org/t/p/original/harbor.jpg', 22 => 'https://image.tmdb.org/t/p/original/ferry-hero.jpg', 23 => '' ),
		'lead_url'  => 'https://example.test/reviews/harbor-light/',
		'latest'    => 21,
		'pairings'  => array(),
		'review_for_movie' => array(),
		'thumbs'    => array(),
		'tt_posters' => array( 'tt1000003' => 'https://image.tmdb.org/t/p/w500/dry-poster.jpg' ), // Dry Season: a poster by IMDb id only
		'ledger'    => array(),
		'calls'     => array(),
	);
}

function lunara_test_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function lunara_test_post( int $id ): ?array {
	return $GLOBALS['lunara_test']['posts'][ $id ] ?? null;
}

// ---- WordPress seams ---------------------------------------------------------
function add_action( ...$args ): void {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
	$GLOBALS['lunara_test']['filters'][ $hook ][] = $callback;
}
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['lunara_test']['filters'][ $hook ] ?? array() as $callback ) {
		$value = call_user_func( $callback, $value, ...$args );
	}
	return $value;
}
function __( $text, $domain = null ) { return $text; }
// The Lunara Film Debrief editor saves its words as theme mods (3.2.132).
function get_theme_mod( $key, $default = false ) { return $GLOBALS['lunara_test']['mods'][ $key ] ?? $default; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' ); }
function esc_html_e( $text, $domain = null ) { echo esc_html( $text ); }
function esc_attr_e( $text, $domain = null ) { echo esc_attr( $text ); }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) ); }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function get_post( $id ) {
	$row = lunara_test_post( (int) $id );
	return $row ? (object) array( 'ID' => (int) $id, 'post_type' => $row['type'], 'post_status' => $row['status'] ?? 'publish', 'post_password' => '', 'post_title' => $row['title'] ) : null;
}
function get_post_meta( $id, $key, $single = false ) { return lunara_test_post( (int) $id )['meta'][ $key ] ?? ''; }
function get_the_title( $id ) { return (string) ( lunara_test_post( (int) $id )['title'] ?? '' ); }
function get_permalink( $id ) { return (string) ( lunara_test_post( (int) $id )['url'] ?? '' ); }
function url_to_postid( $url ) {
	$GLOBALS['lunara_test']['calls'][] = 'url_to_postid';
	foreach ( $GLOBALS['lunara_test']['posts'] as $id => $row ) {
		if ( untrailingslashit( $row['url'] ) === untrailingslashit( (string) $url ) ) {
			return $id;
		}
	}
	return 0;
}
function attachment_url_to_postid( $url ) { return 0; }

// ---- Lunara seams: the exact functions the module calls ------------------------
function lunara_get_home_front_door_lead() { return array( 'kicker' => 'Lead Editorial', 'title' => 'x', 'excerpt' => '', 'url' => $GLOBALS['lunara_test']['lead_url'], 'cta' => 'Read', 'image' => '' ); }
function lunara_get_latest_review_post() { $id = (int) $GLOBALS['lunara_test']['latest']; return $id ? get_post( $id ) : null; }
function lunara_get_review_hero_image_url( $id ) { return (string) ( $GLOBALS['lunara_test']['stills'][ (int) $id ] ?? '' ); }
function lunara_rightsize_backdrop_url( $url ) { return str_replace( '/original/', '/w1280/', (string) $url ); }
function lunara_entity_review_for_movie( $movie_id ) { return $GLOBALS['lunara_test']['review_for_movie'][ (int) $movie_id ] ?? null; }
class WP_Query { public $posts = array(); public function __construct( array $args = array() ) { $this->posts = array_map( 'intval', (array) ( $GLOBALS['lunara_test']['recent'] ?? array() ) ); } }
function lunara_get_pairing_desk_review_id() { foreach ( (array) ( $GLOBALS['lunara_test']['recent'] ?? array() ) as $id ) { $post = lunara_test_post( (int) $id ); if ( $post && ( ! empty( $post['meta']['theme_echo_movie'] ) || ! empty( $GLOBALS['lunara_test']['pairings'][ (int) $id ] ) ) ) { return (int) $id; } } return 0; }
function get_the_post_thumbnail_url( $id, $size = 'large' ) { return (string) ( $GLOBALS['lunara_test']['thumbs'][ (int) $id ] ?? '' ); }
function lunara_resize_tmdb_image_url( $url, $size = 'w780' ) { return preg_replace( '#/t/p/[a-z0-9]+/#', '/t/p/' . $size . '/', (string) $url ); }
function lunara_get_title_poster_html( $tt, $size = 'medium', $class = '', $title = '', $loading = 'lazy', $sizes = '' ) { $url = $GLOBALS['lunara_test']['tt_posters'][ strtolower( (string) $tt ) ] ?? ''; return '' === $url ? '' : '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $url ) . '" alt="' . esc_attr( $title ) . ' poster" loading="lazy" decoding="async">'; }
function lunara_render_home_pairing_desk() { return '<section id="pairing-desk" class="lunara-home-section lunara-home-slot-pairing-desk lunara-pairing-desk-section">legacy-pair-it-with-desk</section>'; }
function lunara_debrief_method_roles() {
	return array(
		'theme'   => array( 'relation' => 'theme_echo', 'meta' => '_lunara_theme_echo', 'label' => 'Theme Echo', 'question' => 'What is this film really about — and who else has said it?' ),
		'counter' => array( 'relation' => 'counter_program', 'meta' => '_lunara_counter_program', 'label' => 'Counter Program', 'question' => 'What is the strongest argument against it?' ),
		'career'  => array( 'relation' => 'career_context', 'meta' => '_lunara_career_context', 'label' => 'Career Context', 'question' => 'Where does this sit in the artist\'s story?' ),
	);
}
/** The light identity the Method index reads: title, year, tt, href (relational movie first, then the legacy text). */
function lunara_debrief_method_read_pairing( $review_id, $role ) {
	$movie_id = (int) get_post_meta( $review_id, $role['relation'] . '_movie', true );
	if ( $movie_id > 0 ) {
		$movie = lunara_test_post( $movie_id );
		if ( $movie && 'movie' === $movie['type'] ) {
			return array( 'title' => $movie['title'], 'year' => $movie['meta']['release_year'] ?? '', 'tt' => $movie['meta']['imdb_title_id'] ?? '', 'href' => $movie['url'] );
		}
	}
	return $GLOBALS['lunara_test']['pairings'][ (int) $review_id ][ $role['meta'] ] ?? null;
}
class Academy_Awards_Table {
	public static function get_instance() { return new self(); }
	public function get_title_visual_package( $tt, $size ) { return $GLOBALS['lunara_test']['ledger'][ $tt ] ?? array(); }
}

require dirname( __DIR__ ) . '/inc/helpers.php';
require dirname( __DIR__ ) . '/inc/home-stills-that-rhyme.php';

// A helper: give review 21 (Harbor Light) its full Debrief trio.
//   Theme Echo      Night Ferry (501): backdrop + poster meta
//   Counter Program Dry Season (503): backdrop; poster only by IMDb id
//   Career Context  Salt Road  (502): no still, no poster anywhere
function lunara_test_trio( int $review_id = 21 ): void {
	$GLOBALS['lunara_test']['posts'][ $review_id ]['meta']['theme_echo_movie']      = 501;
	$GLOBALS['lunara_test']['posts'][ $review_id ]['meta']['counter_program_movie'] = 503;
	$GLOBALS['lunara_test']['posts'][ $review_id ]['meta']['career_context_movie']  = 502;
}
// Give every film in the trio a still, so the stills option can be taken.
function lunara_test_all_stills(): void {
	$GLOBALS['lunara_test']['posts'][502]['meta']['tmdb_backdrop_url'] = 'https://image.tmdb.org/t/p/original/salt-backdrop.jpg';
}

// ---- Scenario 1: posters are the default face ----
lunara_test_reset();
lunara_test_trio();
$html = lunara_render_home_stills_that_rhyme();

if ( in_array( '--fixture', $argv ?? array(), true ) ) {
	echo $html;
	exit( 0 );
}

lunara_test_assert( 1 === substr_count( $html, '<section ' ) && 1 === preg_match( '#<section id="pairing-desk" class="lunara-home-section lunara-home-slot-pairing-desk lunara-home-stills-that-rhyme is-media-posters" data-lunara-site-studio-section="pairing-desk" data-lunara-stills-media="posters" aria-label="The Lunara Film Debrief">#', $html ), 'one section in the pairing-desk slot, in posters media, keeping its id and Site Studio hook' );
lunara_test_assert( false === strpos( $html, 'legacy-pair-it-with-desk' ), 'the legacy desk does not render alongside the module' );
lunara_test_assert( false !== strpos( $html, '<p class="lunara-home-stills-kicker">The Lunara Film Debrief</p>' ) && false !== strpos( $html, '<h2 class="lunara-home-stills-title">Every review ends with three more films.</h2>' ), 'with the editor blank, the Debrief defaults: its name and the thesis line' );
lunara_test_assert( 1 === substr_count( $html, '<p class="lunara-home-stills-copy">Theme Echo, Counter Program, and Career Context close every Lunara Film review — the next three movies after the credits, each selected individually by me.</p>' ), 'with the editor blank, the Debrief default line, between the headline and the source' );
lunara_test_assert( strpos( $html, 'lunara-home-stills-title' ) < strpos( $html, 'lunara-home-stills-copy' ) && strpos( $html, 'lunara-home-stills-copy' ) < strpos( $html, 'lunara-home-stills-source' ), 'kicker, headline, line, then the source' );
lunara_test_assert( 1 === preg_match( '#<p class="lunara-home-stills-source">Fresh from the debrief of <a href="https://example.test/reviews/harbor-light/">Harbor Light – The Tide Comes In Twice</a></p>#', $html ), 'the source line names the review the trio comes from' );

// The reviewed film leads with its poster; then exactly three pairings in Method order, each under its full role name with the question.
lunara_test_assert( 1 === preg_match( '#<a class="lunara-home-stills-item is-review" href="https://example.test/reviews/harbor-light/">\s*<span class="lunara-home-stills-frame is-poster"><img class="lunara-home-stills-media" src="https://image.tmdb.org/t/p/w342/harbor-poster.jpg" alt="" width="342" height="513" loading="lazy" decoding="async"></span>#s', $html ), 'the reviewed film opens the set with its poster at w342' );
lunara_test_assert( 1 === preg_match( '#<span class="lunara-home-stills-film">Harbor Light, 2026\.</span>\s*<em class="lunara-home-stills-take">The Tide Comes In Twice</em>#s', $html ), 'the review caption reads "Film, Year." with the take in italic' );
lunara_test_assert( 3 === substr_count( $html, 'lunara-home-stills-item is-pairing' ) && 1 === substr_count( $html, 'class="lunara-home-stills-three" role="list"' ), 'exactly three pairings in one list' );
$order = array( strpos( $html, 'is-role-theme' ), strpos( $html, 'is-role-counter' ), strpos( $html, 'is-role-career' ) );
lunara_test_assert( false !== $order[0] && $order[0] < $order[1] && $order[1] < $order[2] && strpos( $html, 'is-review' ) < $order[0], 'the reviewed film, then Theme Echo, Counter Program, Career Context, in Method order' );
foreach ( array( 'Theme Echo' => 'What is this film really about — and who else has said it?', 'Counter Program' => 'What is the strongest argument against it?', 'Career Context' => 'Where does this sit in the artist&#039;s story?' ) as $label => $question ) {
	lunara_test_assert( false !== strpos( $html, '<span class="lunara-home-stills-role">' . $label . '</span>' ) && false !== strpos( $html, '<em class="lunara-home-stills-question-text">' . $question . '</em>' ), "the full role name and the Method question for {$label}" );
}
lunara_test_assert( 1 === preg_match( '#is-pairing is-role-theme" href="https://example.test/movie/night-ferry/" role="listitem">\s*<span class="lunara-home-stills-role">Theme Echo</span>\s*<span class="lunara-home-stills-frame is-poster"><img class="lunara-home-stills-media" src="https://image.tmdb.org/t/p/w342/ferry-poster.jpg"#s', $html ) && false !== strpos( $html, '<span class="lunara-home-stills-film">Night Ferry, 1998.</span>' ), 'Theme Echo shows the movie entity poster, captioned "Film, Year."' );
lunara_test_assert( 1 === preg_match( '#is-pairing is-role-counter" href="https://example.test/movie/dry-season/" role="listitem">\s*<span class="lunara-home-stills-role">Counter Program</span>\s*<span class="lunara-home-stills-frame is-poster"><img class="lunara-home-stills-media" src="https://image.tmdb.org/t/p/w500/dry-poster.jpg" alt="Dry Season poster"#s', $html ), 'Counter Program shows the poster the shared helper finds by IMDb id' );
lunara_test_assert( 1 === preg_match( '#is-pairing is-role-career has-no-art" href="https://example.test/movie/salt-road/" role="listitem">\s*<span class="lunara-home-stills-role">Career Context</span>\s*<span class="lunara-home-stills-frame is-text is-poster"><span class="lunara-home-stills-text-title">Salt Road</span></span>#s', $html ) && false !== strpos( $html, 'Salt Road, 2004.' ), 'a pairing with no poster anywhere is a typographic plate, never dropped and never an empty frame' );
lunara_test_assert( 3 === substr_count( $html, '<img ' ) && 0 === substr_count( $html, 'is-still' ) && false === strpos( $html, 'backdrop' ), 'posters only: no still is drawn in posters media even where one exists' );
lunara_test_assert( 1 === substr_count( $html, 'lunara-home-stills-take' ), 'only the reviewed film carries a take' );

// ---- The stills option: taken only when every film in the set has a still ----
lunara_test_reset();
lunara_test_trio();
lunara_test_all_stills();
$html = lunara_render_home_stills_that_rhyme();
if ( in_array( '--fixture=stills', $argv ?? array(), true ) ) {
	echo $html;
	exit( 0 );
}
lunara_test_assert( false !== strpos( $html, 'lunara-home-stills-that-rhyme is-media-stills' ) && false !== strpos( $html, 'data-lunara-stills-media="stills"' ), 'with a still for the reviewed film and all three pairings the set shows stills' );
lunara_test_assert( 4 === substr_count( $html, 'class="lunara-home-stills-frame is-still"' ) && 4 === substr_count( $html, '<img ' ) && false === strpos( $html, 'is-poster' ) && false === strpos( $html, 'has-no-art' ), 'four 16:9 stills, no poster, no plate' );
lunara_test_assert( false !== strpos( $html, 'src="https://image.tmdb.org/t/p/w1280/harbor.jpg"' ) && false !== strpos( $html, 'src="https://image.tmdb.org/t/p/w1280/ferry-backdrop.jpg"' ) && false !== strpos( $html, 'src="https://image.tmdb.org/t/p/w1280/dry-backdrop.jpg"' ) && false !== strpos( $html, 'src="https://image.tmdb.org/t/p/w1280/salt-backdrop.jpg"' ), 'the stills are the right-sized backdrops' );

lunara_test_reset();
lunara_test_trio();
lunara_test_all_stills();
$GLOBALS['lunara_test']['stills'][21] = '';
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'is-media-posters' ) && false === strpos( $html, 'is-still' ), 'a reviewed film without a still keeps the set on posters' );

// ---- The media filter: posters can always be asked for; stills only when the set can honour them ----
lunara_test_reset();
lunara_test_trio();
lunara_test_all_stills();
add_filter( 'lunara_home_stills_that_rhyme_media', static function ( $media, $set ) { lunara_test_assert( 'stills' === $media && isset( $set['pairings']['theme'] ), 'the media filter receives the automatic choice and the set' ); return 'posters'; }, 10, 2 );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'is-media-posters' ) && 4 === substr_count( $html, 'is-poster' ), 'a filter can hold the set on posters even when stills exist' );
lunara_test_reset();
lunara_test_trio();
add_filter( 'lunara_home_stills_that_rhyme_media', static function ( $media ) { return 'stills'; } );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'is-media-posters' ), 'a request for stills the set cannot honour falls back to posters' );

// ---- Partner still sources (stills media): a Lunara review of the film, the Ledger backdrop ----
lunara_test_reset();
lunara_test_trio();
$GLOBALS['lunara_test']['review_for_movie'][502] = 23; // Salt Road has a Lunara review with no still...
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'is-media-posters' ), 'a Lunara review without a still gives the pairing nothing, so the set stays on posters' );
$GLOBALS['lunara_test']['stills'][23] = 'https://image.tmdb.org/t/p/original/salt-hero.jpg'; // ...until the review has one
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'is-media-stills' ) && false !== strpos( $html, 'src="https://image.tmdb.org/t/p/w1280/salt-hero.jpg"' ), 'the hero still of a Lunara review of the paired film completes the stills set' );

lunara_test_reset();
lunara_test_trio();
$GLOBALS['lunara_test']['posts'][21]['meta']['career_context_movie'] = 0;
$GLOBALS['lunara_test']['pairings'][21]['_lunara_career_context']    = array( 'title' => 'Quiet Harbor', 'year' => '1961', 'tt' => 'tt1000009', 'href' => 'https://www.imdb.com/title/tt1000009/' );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'is-role-career has-no-art" href="https://example.test/reviews/harbor-light/"' ) && false === strpos( $html, 'imdb.com' ), 'an IMDb-only pairing with no art is a plate that links back to the review, never off-site' );
$GLOBALS['lunara_test']['tt_posters']['tt1000009'] = 'https://image.tmdb.org/t/p/w500/quiet-poster.jpg';
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'is-media-posters' ) && false !== strpos( $html, 'src="https://image.tmdb.org/t/p/w500/quiet-poster.jpg"' ) && false !== strpos( $html, 'Quiet Harbor, 1961.' ), 'a poster found by IMDb id dresses an IMDb-only pairing' );
$GLOBALS['lunara_test']['ledger']['tt1000009'] = array( 'poster_url' => 'https://image.tmdb.org/t/p/w500/quiet-poster.jpg', 'backdrop_url' => 'https://image.tmdb.org/t/p/original/quiet-backdrop.jpg' );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'is-media-stills' ) && false !== strpos( $html, 'src="https://image.tmdb.org/t/p/w1280/quiet-backdrop.jpg"' ) && false === strpos( $html, 'quiet-poster' ), 'in stills media the Ledger backdrop stands in by IMDb id, never the poster' );

// ---- The set walks past a lead without all three (3.2.124) ----
lunara_test_reset();
$GLOBALS['lunara_test']['posts'][21]['meta']['theme_echo_movie'] = 501; // Harbor Light has one pairing, not three
lunara_test_trio( 22 );                                              // Night Ferry has the trio
$GLOBALS['lunara_test']['recent'] = array( 21, 22, 23 );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( array( 21, 22, 23 ) === lunara_home_stills_that_rhyme_candidate_review_ids(), 'candidates run lead first, then the desk review, then the newest reviews, without repeats' );
lunara_test_assert( false === strpos( $html, 'legacy-pair-it-with-desk' ) && false !== strpos( $html, 'is-review" href="https://example.test/reviews/night-ferry/"' ) && false === strpos( $html, 'Harbor Light' ) && 3 === substr_count( $html, 'is-pairing' ), 'a lead without its full trio hands the section to the newest review that has all three' );
lunara_test_reset();
$GLOBALS['lunara_test']['recent'] = array( 21, 22, 23 );
add_filter( 'lunara_home_stills_that_rhyme_candidates', static function ( $ids ) { return array( 23 ); } );
lunara_test_assert( array( 23 ) === lunara_home_stills_that_rhyme_candidate_review_ids(), 'the candidate list is filterable' );

// ---- Legacy desk: no qualifying review, never an empty frame ----
lunara_test_reset();
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( '<section id="pairing-desk" class="lunara-home-section lunara-home-slot-pairing-desk lunara-pairing-desk-section">legacy-pair-it-with-desk</section>' === $html, 'with no paired review anywhere the legacy desk renders untouched' );

lunara_test_reset();
lunara_test_trio();
$GLOBALS['lunara_test']['posts'][21]['meta']['_lunara_tmdb_poster_url'] = '';
$GLOBALS['lunara_test']['stills'][21]                                   = '';
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'is-review" href="https://example.test/reviews/harbor-light/">' ) && 1 === preg_match( '#is-review" href="[^"]+">\s*<span class="lunara-home-stills-frame is-text is-poster"><span class="lunara-home-stills-text-title">Harbor Light</span>#s', $html ), 'a reviewed film with neither poster nor still still leads, as a plate: the trio is what matters' );

lunara_test_reset();
$GLOBALS['lunara_test']['posts'][21]['meta']['theme_echo_movie']      = 501;
$GLOBALS['lunara_test']['posts'][21]['meta']['counter_program_movie'] = 503;
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'legacy-pair-it-with-desk' ), 'two pairings are not the Debrief: the set needs all three or the desk renders' );

// ---- Which review leads ----
lunara_test_reset();
lunara_test_trio();
$GLOBALS['lunara_test']['lead_url'] = 'https://example.test/journal/desk-note/';
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'Harbor Light, 2026.' ), 'a Journal lead story falls back to the newest published review' );

lunara_test_reset();
lunara_test_trio( 22 );
$GLOBALS['lunara_test']['lead_url'] = 'https://example.test/reviews/night-ferry/';
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'is-review" href="https://example.test/reviews/night-ferry/"' ) && 1 === preg_match( '#<span class="lunara-home-stills-film">Night Ferry, 1998\.</span>\s*<em class="lunara-home-stills-take">Crossing in the Dark</em>#s', $html ), 'a review lead story that is not the newest review still leads, entity dash decoded' );

lunara_test_reset();
lunara_test_trio();
lunara_render_home_stills_that_rhyme();
lunara_test_assert( ! in_array( 'url_to_postid', $GLOBALS['lunara_test']['calls'], true ), 'when the lead is the newest review no rewrite lookup runs' );

// ---- The set filter ----
lunara_test_reset();
lunara_test_trio();
add_filter( 'lunara_home_stills_that_rhyme_set', static function ( $set ) {
	lunara_test_assert( is_array( $set ) && 21 === $set['review']['id'] && 'posters' === $set['media'] && array( 'theme', 'counter', 'career' ) === array_keys( $set['pairings'] ) && 501 === $set['pairings']['theme']['partner']['movie_id'], 'the filter receives the resolved set with its media' );
	$set['pairings']['career']['partner'] = array( 'title' => 'Dry Season', 'year' => '2011', 'url' => 'https://example.test/movie/dry-season/', 'poster' => 'https://image.tmdb.org/t/p/w342/dry-poster.jpg' );
	return $set;
} );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( 1 === preg_match( '#is-role-career" href="https://example.test/movie/dry-season/"#', $html ) && false === strpos( $html, 'Salt Road' ), 'a filter can replace a pairing' );

lunara_test_reset();
lunara_test_trio();
add_filter( 'lunara_home_stills_that_rhyme_set', static function ( $set ) { unset( $set['pairings']['career'] ); return $set; } );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'legacy-pair-it-with-desk' ), 'a filter that drops a role gets the legacy desk: the trio is the contract' );

lunara_test_reset();
add_filter( 'lunara_home_stills_that_rhyme_set', static function ( $set ) {
	lunara_test_assert( null === $set, 'the filter runs even when nothing resolved, so a control can supply a set' );
	$pairing = static function ( $slug, $label, $title, $year, $poster ) { return array( 'role' => array( 'slug' => $slug, 'label' => $label, 'question' => 'Supplied.' ), 'partner' => array( 'title' => $title, 'year' => $year, 'url' => 'https://example.test/movie/' . sanitize_key( $title ) . '/', 'poster' => $poster ) ); };
	return array(
		'media'    => 'posters',
		'review'   => array( 'id' => 21, 'film' => 'Harbor Light', 'take' => '', 'year' => '2026', 'url' => 'https://example.test/reviews/harbor-light/', 'poster' => 'https://image.tmdb.org/t/p/w342/harbor-poster.jpg' ),
		'pairings' => array(
			'theme'   => $pairing( 'theme', 'Theme Echo', 'Night Ferry', '1998', 'https://image.tmdb.org/t/p/w342/ferry-poster.jpg' ),
			'counter' => $pairing( 'counter', 'Counter Program', 'Dry Season', '2011', '' ),
			'career'  => $pairing( 'career', 'Career Context', 'Salt Road', '2004', '' ),
		),
	);
} );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( 3 === substr_count( $html, 'is-pairing' ) && 2 === substr_count( $html, 'has-no-art' ) && false !== strpos( $html, 'Supplied.' ) && false === strpos( $html, 'legacy-pair-it-with-desk' ), 'a supplied set renders, plates where posters are missing' );

// ---- The Lunara Film Debrief editor drives the words (3.2.132) ----
// What Dalton types in Site Studio (the same fields that head the Reviews
// page Pairing Desk) replaces the defaults; a blank field falls back.
lunara_test_reset();
lunara_test_trio();
$GLOBALS['lunara_test']['mods'] = array( 'lunara_home_pairing_desk_kicker' => '  A saved kicker ', 'lunara_home_pairing_desk_title' => 'A saved headline.', 'lunara_home_pairing_desk_copy' => 'A saved line about the Debrief.' );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'aria-label="A saved kicker"' ) && false !== strpos( $html, '<p class="lunara-home-stills-kicker">A saved kicker</p>' ) && false !== strpos( $html, '<h2 class="lunara-home-stills-title">A saved headline.</h2>' ) && false !== strpos( $html, '<p class="lunara-home-stills-copy">A saved line about the Debrief.</p>' ), 'the saved kicker, headline and line replace the defaults' );
$GLOBALS['lunara_test']['mods'] = array( 'lunara_home_pairing_desk_kicker' => '   ', 'lunara_home_pairing_desk_copy' => '' );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, '<p class="lunara-home-stills-kicker">The Lunara Film Debrief</p>' ) && false !== strpos( $html, 'each selected individually by me.</p>' ), 'a blank field falls back to the Debrief default' );

// ---- Which review leads (3.2.138) ----
// Both the lead story (21) and the editor's Featured Review (22) carry a
// full trio. Dalton chose his pick (2026-10-26): by default the editor's
// Featured Review leads, so Home matches the Reviews page Pairing Desk;
// 'lead' puts the masthead's lead story first.
lunara_test_reset();
lunara_test_trio( 21 );
lunara_test_trio( 22 );
$GLOBALS['lunara_test']['recent'] = array( 22 );
lunara_test_assert( 'editor' === lunara_home_stills_that_rhyme_review_source() && 22 === ( lunara_home_stills_that_rhyme_candidate_review_ids()[0] ?? 0 ) && in_array( 21, lunara_home_stills_that_rhyme_candidate_review_ids(), true ), 'by default the Featured Review is tried first and the lead story follows' );
lunara_test_assert( false !== strpos( lunara_render_home_stills_that_rhyme(), 'Fresh from the debrief of <a href="https://example.test/reviews/night-ferry/">' ), 'by default Home features the editor\'s Featured Review' );
add_filter( 'lunara_home_stills_that_rhyme_review_source', static function () { return 'lead'; } );
lunara_test_assert( 'lead' === lunara_home_stills_that_rhyme_review_source() && 21 === ( lunara_home_stills_that_rhyme_candidate_review_ids()[0] ?? 0 ), 'lead first: the lead story is tried first' );
lunara_test_assert( false !== strpos( lunara_render_home_stills_that_rhyme(), 'Fresh from the debrief of <a href="https://example.test/reviews/harbor-light/">' ), 'lead first: Home features the lead story' );
$GLOBALS['lunara_test']['filters'] = array();
add_filter( 'lunara_home_stills_that_rhyme_review_source', static function () { return 'anything else'; } );
lunara_test_assert( 'editor' === lunara_home_stills_that_rhyme_review_source(), 'an unknown source value keeps the Featured Review first' );
// A Featured Review without all three pairings never costs the section:
// the lead story with its trio takes it.
lunara_test_reset();
$GLOBALS['lunara_test']['filters'] = array();
lunara_test_trio( 21 );
$GLOBALS['lunara_test']['posts'][22]['meta']['theme_echo_movie'] = 501; // the pick has one pairing, not three
$GLOBALS['lunara_test']['recent'] = array( 22, 21 );
lunara_test_assert( 22 === ( lunara_home_stills_that_rhyme_candidate_review_ids()[0] ?? 0 ) && false !== strpos( lunara_render_home_stills_that_rhyme(), 'Fresh from the debrief of <a href="https://example.test/reviews/harbor-light/">' ), 'a Featured Review without its full trio hands the showcase to the lead story' );

fwrite( STDOUT, "Home stills that rhyme: the reviewed film and its three, posters by default — contract passed.\n" );
