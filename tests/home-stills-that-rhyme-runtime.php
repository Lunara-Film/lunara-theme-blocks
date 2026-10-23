<?php
/**
 * Behavior contract for Home — Stills that Rhyme (Direction D, 3.2.120).
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
			21  => array( 'type' => 'review', 'title' => 'Harbor Light – The Tide Comes In Twice', 'url' => 'https://example.test/reviews/harbor-light/', 'meta' => array( '_lunara_year' => '2026' ) ),
			22  => array( 'type' => 'review', 'title' => 'Night Ferry &#8211; Crossing in the Dark', 'url' => 'https://example.test/reviews/night-ferry/', 'meta' => array( '_lunara_year' => '1998' ) ),
			23  => array( 'type' => 'review', 'title' => 'Salt Road', 'url' => 'https://example.test/reviews/salt-road/', 'meta' => array() ),
			31  => array( 'type' => 'journal', 'title' => 'Desk note', 'url' => 'https://example.test/journal/desk-note/', 'meta' => array() ),
			501 => array( 'type' => 'movie', 'title' => 'Night Ferry', 'url' => 'https://example.test/movie/night-ferry/', 'meta' => array( 'release_year' => '1998', 'imdb_title_id' => 'tt1000001', 'tmdb_backdrop_url' => 'https://image.tmdb.org/t/p/original/ferry-backdrop.jpg' ) ),
			502 => array( 'type' => 'movie', 'title' => 'Salt Road', 'url' => 'https://example.test/movie/salt-road/', 'meta' => array( 'release_year' => '2004', 'imdb_title_id' => 'tt1000002' ) ),
			503 => array( 'type' => 'movie', 'title' => 'Dry Season', 'url' => 'https://example.test/movie/dry-season/', 'meta' => array( 'release_year' => '2011', 'imdb_title_id' => 'tt1000003', 'tmdb_backdrop_url' => 'https://image.tmdb.org/t/p/original/dry-backdrop.jpg' ) ),
		),
		'stills'    => array( 21 => 'https://image.tmdb.org/t/p/original/harbor.jpg', 22 => 'https://image.tmdb.org/t/p/original/ferry-hero.jpg', 23 => '' ),
		'lead_url'  => 'https://example.test/reviews/harbor-light/',
		'latest'    => 21,
		'pairings'  => array(),
		'review_for_movie' => array(),
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

// ---- Scenario 1: the lead review and its Theme Echo movie, both with stills ----
lunara_test_reset();
$GLOBALS['lunara_test']['posts'][21]['meta']['theme_echo_movie']      = 501;
$GLOBALS['lunara_test']['posts'][21]['meta']['counter_program_movie'] = 503;
$html = lunara_render_home_stills_that_rhyme();

if ( in_array( '--fixture', $argv ?? array(), true ) ) {
	echo $html;
	exit( 0 );
}

lunara_test_assert( 1 === substr_count( $html, '<section ' ) && 1 === preg_match( '#<section id="pairing-desk" class="lunara-home-section lunara-home-slot-pairing-desk lunara-home-stills-that-rhyme is-role-theme" data-lunara-site-studio-section="pairing-desk" aria-label="Stills that Rhyme">#', $html ), 'one self-contained section in the pairing-desk slot, found by Site Studio' );
lunara_test_assert( false === strpos( $html, 'legacy-pair-it-with-desk' ), 'the legacy desk does not render alongside the stills' );
lunara_test_assert( 2 === substr_count( $html, 'class="lunara-home-stills-frame"' ) && 2 === substr_count( $html, '<img ' ), 'two frames, two stills' );
lunara_test_assert( strpos( $html, 'lunara-home-stills-head' ) < strpos( $html, 'lunara-home-stills-grid' ) && strpos( $html, 'is-review' ) < strpos( $html, 'is-partner' ), 'head, then the review still, then the partner still' );
lunara_test_assert( false !== strpos( $html, '<p class="lunara-home-stills-kicker">Stills that Rhyme</p>' ) && false !== strpos( $html, '<h2 class="lunara-home-stills-title">Two films, one image idea.</h2>' ), 'kicker and the GT Sectra line' );
lunara_test_assert( false !== strpos( $html, '<span class="lunara-home-stills-role">Theme Echo</span>' ) && false !== strpos( $html, '<em class="lunara-home-stills-question-text">What is this film really about — and who else has said it?</em>' ), 'the full role name and its Method question' );
lunara_test_assert( 1 === preg_match( '#<a class="lunara-home-stills-item is-review" href="https://example.test/reviews/harbor-light/">\s*<span class="lunara-home-stills-frame"><img class="lunara-home-stills-media" src="https://image.tmdb.org/t/p/w1280/harbor.jpg" alt="" width="1600" height="900" loading="lazy" decoding="async"></span>#s', $html ), 'the review still is right-sized, lazy, dimensioned and links to the review' );
lunara_test_assert( 1 === preg_match( '#<a class="lunara-home-stills-item is-partner" href="https://example.test/movie/night-ferry/">\s*<span class="lunara-home-stills-frame"><img class="lunara-home-stills-media" src="https://image.tmdb.org/t/p/w1280/ferry-backdrop.jpg" alt="" width="1600" height="900" loading="lazy" decoding="async"></span>#s', $html ), 'the partner still is the linked movie\'s backdrop and links to its Film Dossier' );
lunara_test_assert( 1 === preg_match( '#<span class="lunara-home-stills-film">Harbor Light, 2026\.</span>\s*<em class="lunara-home-stills-take">The Tide Comes In Twice</em>#s', $html ), 'the review caption reads "Film, Year." with the take in italic' );
lunara_test_assert( false !== strpos( $html, '<span class="lunara-home-stills-film">Night Ferry, 1998.</span>' ) && 1 === substr_count( $html, 'lunara-home-stills-take' ), 'the partner caption reads "Film, Year." without a take' );
lunara_test_assert( false === strpos( $html, 'Dry Season' ), 'Theme Echo is preferred when it has a still, even with other roles filled' );

// ---- Role order: Theme Echo without a still, then Counter Program, then Career Context ----
lunara_test_reset();
$GLOBALS['lunara_test']['posts'][21]['meta']['theme_echo_movie']      = 502; // no backdrop, no review, no Ledger art
$GLOBALS['lunara_test']['posts'][21]['meta']['counter_program_movie'] = 503;
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'lunara-home-stills-that-rhyme is-role-counter' ) && false !== strpos( $html, '>Counter Program</span>' ) && false !== strpos( $html, 'What is the strongest argument against it?' ) && false !== strpos( $html, 'Dry Season, 2011.' ) && false === strpos( $html, 'Salt Road' ), 'when Theme Echo has no still, Counter Program is next' );

lunara_test_reset();
$GLOBALS['lunara_test']['posts'][21]['meta']['theme_echo_movie'] = 502;
$GLOBALS['lunara_test']['pairings'][21]['_lunara_counter_program'] = array( 'title' => 'Low Tide', 'year' => '1977', 'tt' => '', 'href' => 'https://www.imdb.com/find/?q=Low+Tide' );
$GLOBALS['lunara_test']['pairings'][21]['_lunara_career_context'] = array( 'title' => 'Night Ferry', 'year' => '1998', 'tt' => 'tt1000001', 'href' => 'https://example.test/reviews/night-ferry/' );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'is-role-career' ) && false !== strpos( $html, '>Career Context</span>' ) && false !== strpos( $html, 'Night Ferry, 1998.' ), 'when neither Theme Echo nor Counter Program has a still, Career Context is last' );

// ---- Partner still sources: movie backdrop, a Lunara review of the film, the Ledger backdrop ----
lunara_test_reset();
$GLOBALS['lunara_test']['posts'][21]['meta']['theme_echo_movie'] = 502;
$GLOBALS['lunara_test']['review_for_movie'][502]                 = 23; // a review of Salt Road with no still
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'legacy-pair-it-with-desk' ), 'a Lunara review without a still gives the partner nothing' );
$GLOBALS['lunara_test']['stills'][23] = 'https://image.tmdb.org/t/p/original/salt-hero.jpg';
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( 1 === preg_match( '#is-partner" href="https://example.test/movie/salt-road/">\s*<span class="lunara-home-stills-frame"><img class="lunara-home-stills-media" src="https://image.tmdb.org/t/p/w1280/salt-hero.jpg"#s', $html ), 'a linked movie without a backdrop borrows the hero still of its Lunara review and keeps the Dossier link' );

lunara_test_reset();
$GLOBALS['lunara_test']['pairings'][21]['_lunara_theme_echo'] = array( 'title' => 'Night Ferry', 'year' => '1998', 'tt' => 'tt1000001', 'href' => 'https://example.test/reviews/night-ferry/' );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( 1 === preg_match( '#is-partner" href="https://example.test/reviews/night-ferry/">\s*<span class="lunara-home-stills-frame"><img class="lunara-home-stills-media" src="https://image.tmdb.org/t/p/w1280/ferry-hero.jpg"#s', $html ), 'a legacy text pairing that resolved to a Lunara review uses that review\'s hero still and link' );

lunara_test_reset();
$GLOBALS['lunara_test']['pairings'][21]['_lunara_theme_echo'] = array( 'title' => 'Quiet Harbor', 'year' => '1961', 'tt' => 'tt1000009', 'href' => 'https://www.imdb.com/title/tt1000009/' );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'legacy-pair-it-with-desk' ), 'an IMDb-only pairing with no Ledger backdrop falls back to the desk' );
$GLOBALS['lunara_test']['ledger']['tt1000009'] = array( 'poster_url' => 'https://image.tmdb.org/t/p/w500/quiet-poster.jpg', 'backdrop_url' => 'https://image.tmdb.org/t/p/original/quiet-backdrop.jpg' );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( 1 === preg_match( '#is-partner" href="https://example.test/reviews/harbor-light/">\s*<span class="lunara-home-stills-frame"><img class="lunara-home-stills-media" src="https://image.tmdb.org/t/p/w1280/quiet-backdrop.jpg"#s', $html ) && false === strpos( $html, 'imdb.com' ) && false !== strpos( $html, 'Quiet Harbor, 1961.' ), 'the Ledger backdrop stands in by IMDb id, never the poster, and an external source links back to the lead review' );

// ---- Legacy desk: missing still or missing pairing, never an empty frame ----
lunara_test_reset();
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( '<section id="pairing-desk" class="lunara-home-section lunara-home-slot-pairing-desk lunara-pairing-desk-section">legacy-pair-it-with-desk</section>' === $html, 'a lead with no pairing hands back the legacy desk untouched' );

lunara_test_reset();
$GLOBALS['lunara_test']['posts'][21]['meta']['theme_echo_movie'] = 501;
$GLOBALS['lunara_test']['stills'][21]                            = '';
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'legacy-pair-it-with-desk' ) && false === strpos( $html, 'lunara-home-stills' ), 'a lead review without a still hands back the legacy desk, not a frame' );

lunara_test_reset();
$GLOBALS['lunara_test']['posts'][21]['meta']['theme_echo_movie'] = 502;
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'legacy-pair-it-with-desk' ) && false === strpos( $html, '<img' ), 'a pairing whose film has no still anywhere hands back the legacy desk, not a placeholder' );

// ---- Which review leads ----
lunara_test_reset();
$GLOBALS['lunara_test']['posts'][21]['meta']['theme_echo_movie'] = 501;
$GLOBALS['lunara_test']['lead_url']                              = 'https://example.test/journal/desk-note/';
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'Harbor Light, 2026.' ), 'a Journal lead story falls back to the newest published review' );

lunara_test_reset();
$GLOBALS['lunara_test']['posts'][22]['meta']['theme_echo_movie'] = 503;
$GLOBALS['lunara_test']['lead_url']                              = 'https://example.test/reviews/night-ferry/';
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'is-review" href="https://example.test/reviews/night-ferry/"' ) && 1 === preg_match( '#<span class="lunara-home-stills-film">Night Ferry, 1998\.</span>\s*<em class="lunara-home-stills-take">Crossing in the Dark</em>#s', $html ) && false !== strpos( $html, 'Dry Season, 2011.' ), 'an older review leading the front door is the review that rhymes, its entity dash decoded' );

lunara_test_reset();
$GLOBALS['lunara_test']['posts'][21]['meta']['theme_echo_movie'] = 501;
lunara_render_home_stills_that_rhyme();
lunara_test_assert( ! in_array( 'url_to_postid', $GLOBALS['lunara_test']['calls'], true ), 'when the lead is the newest review no rewrite lookup runs' );

// ---- The pair filter ----
lunara_test_reset();
$GLOBALS['lunara_test']['posts'][21]['meta']['theme_echo_movie'] = 501;
add_filter( 'lunara_home_stills_that_rhyme_pair', static function ( $pair ) {
	lunara_test_assert( is_array( $pair ) && 'theme' === $pair['role']['slug'] && 21 === $pair['review']['id'] && 501 === $pair['partner']['movie_id'] && 'tt1000001' === $pair['partner']['tt'], 'the filter receives the resolved pair' );
	$pair['role']    = array( 'slug' => 'studio', 'label' => 'Studio Pick', 'question' => 'Chosen by hand.' );
	$pair['partner'] = array( 'title' => 'Dry Season', 'year' => '2011', 'url' => 'https://example.test/movie/dry-season/', 'still' => 'https://image.tmdb.org/t/p/w1280/dry-backdrop.jpg' );
	return $pair;
} );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'is-role-studio' ) && false !== strpos( $html, '>Studio Pick</span>' ) && false !== strpos( $html, 'Dry Season, 2011.' ) && false !== strpos( $html, 'src="https://image.tmdb.org/t/p/w1280/dry-backdrop.jpg"' ) && false === strpos( $html, 'Night Ferry' ), 'a filter can swap the pair before it renders' );

lunara_test_reset();
$GLOBALS['lunara_test']['posts'][21]['meta']['theme_echo_movie'] = 501;
add_filter( 'lunara_home_stills_that_rhyme_pair', static function ( $pair ) { return null; } );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( false !== strpos( $html, 'legacy-pair-it-with-desk' ), 'a filter that withdraws the pair gets the legacy desk' );

lunara_test_reset();
add_filter( 'lunara_home_stills_that_rhyme_pair', static function ( $pair ) {
	lunara_test_assert( null === $pair, 'the filter runs even when nothing resolved, so a control can supply a pair' );
	return array(
		'role'    => array( 'slug' => 'theme', 'label' => 'Theme Echo', 'question' => 'Supplied.' ),
		'review'  => array( 'id' => 21, 'film' => 'Harbor Light', 'take' => '', 'year' => '2026', 'url' => 'https://example.test/reviews/harbor-light/', 'still' => 'https://example.test/wp-content/uploads/harbor.jpg?resize=1600,900' ),
		'partner' => array( 'title' => 'Night Ferry', 'year' => '', 'url' => 'https://example.test/movie/night-ferry/', 'still' => 'https://example.test/wp-content/uploads/ferry.jpg?resize=1600,900' ),
	);
} );
$html = lunara_render_home_stills_that_rhyme();
lunara_test_assert( 2 === substr_count( $html, '<img ' ) && false !== strpos( $html, '<span class="lunara-home-stills-film">Night Ferry.</span>' ) && false === strpos( $html, 'lunara-home-stills-take' ), 'a supplied pair renders; a film without a year reads "Film." and no empty take is emitted' );

fwrite( STDOUT, "Home stills that rhyme: pair contract passed.\n" );
