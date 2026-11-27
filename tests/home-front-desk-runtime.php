<?php
/**
 * Behavior contract for the Home front desk (the program masthead, 3.2.119).
 *
 * Renders inc/home-front-desk.php against stubbed WordPress and asserts what
 * a visitor gets: the dateline, the mark, the priority lead story and then
 * the three doors. Each door has one current line, and Reviews shares the
 * lead's source. Also covers the headline splitter used on Review pages.
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['lunara_test'] = array(
	'mods'    => array(),
	'reviews' => array(
		array( 'ID' => 11, 'title' => 'The Social Reckoning – A Procedural Without a Pulse', 'url' => 'https://example.test/reviews/the-social-reckoning/', 'still' => 'https://image.tmdb.org/t/p/original/reckoning.jpg', 'date' => '2026-10-18 15:00:00' ),
		array( 'ID' => 12, 'title' => 'Practical Magic 2 &#8211; The Curdled Spell', 'url' => 'https://example.test/reviews/practical-magic-2/', 'still' => '' ),
		array( 'ID' => 13, 'title' => 'Misty Green &#8211; The Price of Second Acts', 'url' => 'https://example.test/reviews/misty-green/', 'still' => 'https://image.tmdb.org/t/p/original/misty.jpg' ),
		array( 'ID' => 14, 'title' => 'Lurker', 'url' => 'https://example.test/reviews/lurker/', 'still' => 'https://image.tmdb.org/t/p/original/lurker.jpg' ),
	),
	'slides'  => true,
	// The newest Journal entry (the Journal door) and the Ledger band's cached
	// counts (the Oscars door); no Oscar pick until the Sweep Watch scenario.
	'journal' => array(
		array( 'ID' => 21, 'title' => 'Gremlins 3 Slides to 2028, and It Needs to Pick a Lane', 'url' => 'https://example.test/journal/gremlins-3-slides-to-2028/', 'thumb' => 2101, 'image' => 'https://example.test/wp-content/uploads/2026/09/gremlins-3-set-for-october-2028.jpg', 'date' => '2026-10-05 15:00:00' ),
	),
	'band'    => array( 'ceremonies' => 98, 'record' => array( 'scope' => 'ledger', 'categories' => 66, 'nominations' => 12138, 'winners' => 2800 ), 'posters' => array() ),
);

function lunara_test_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function lunara_test_img_attrs( string $html, string $class ): array {
	if ( ! preg_match( '#<img class="[^"]*\b' . preg_quote( $class, '#' ) . '\b[^"]*"[^>]*>#', $html, $tag ) ) {
		return array();
	}
	preg_match_all( '/([\w-]+)="([^"]*)"/', $tag[0], $matches );
	return array_combine( $matches[1], array_map( 'html_entity_decode', $matches[2] ) );
}

class WP_Post {
	public $ID;
	public $post_title;
	public $post_date = '';
	public function __construct( int $id, string $title, string $date = '' ) {
		$this->ID         = $id;
		$this->post_title = $title;
		$this->post_date  = $date;
	}
}

class WP_Query {
	public static $journal_args = array();
	public $posts = array();
	public function __construct( array $args = array() ) {
		$limit = (int) ( $args['posts_per_page'] ?? 10 );
		if ( 'journal' === ( $args['post_type'] ?? '' ) ) {
			self::$journal_args = $args;
			foreach ( array_slice( $GLOBALS['lunara_test']['journal'], 0, $limit ) as $row ) {
				$this->posts[] = new WP_Post( $row['ID'], $row['title'], (string) ( $row['date'] ?? '' ) );
			}
			return;
		}
		foreach ( array_slice( $GLOBALS['lunara_test']['reviews'], 0, $limit ) as $row ) {
			$this->posts[] = new WP_Post( $row['ID'], $row['title'], (string) ( $row['date'] ?? '' ) );
		}
	}
}

function lunara_test_review( int $id ): array {
	foreach ( array_merge( $GLOBALS['lunara_test']['reviews'], $GLOBALS['lunara_test']['journal'] ) as $row ) {
		if ( $row['ID'] === $id ) {
			return $row;
		}
	}
	return array( 'ID' => $id, 'title' => '', 'url' => '', 'still' => '' );
}

function add_action( ...$args ): void {}
function is_front_page(): bool { return true; }
function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return (string) $url; }
function esc_html_e( $text, $domain = null ) { echo esc_html( $text ); }
function esc_attr_e( $text, $domain = null ) { echo esc_attr( $text ); }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function wp_timezone() { return new DateTimeZone( 'UTC' ); }
function wp_date( $format, $timestamp = null ) {
	if ( null === $timestamp ) {
		return 'October 2026';
	}
	return ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( wp_timezone() )->format( $format );
}
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function get_post_type_archive_link( $type ) { return 'https://example.test/' . $type . 's/'; }
function get_bloginfo( $what ) { return 'Lunara Film'; }
function get_theme_mod( $key, $default = false ) { return $GLOBALS['lunara_test']['mods'][ $key ] ?? $default; }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) ); }
function absint( $n ) { return abs( (int) $n ); }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function wp_trim_words( $text, $num, $more ) { $w = preg_split( '/\s+/', trim( (string) $text ) ); return count( $w ) > $num ? implode( ' ', array_slice( $w, 0, $num ) ) . $more : $text; }
function get_permalink( $post ) { return lunara_test_review( is_object( $post ) ? (int) $post->ID : (int) $post )['url']; }
function get_the_title( $post ) { $row = lunara_test_review( is_object( $post ) ? (int) $post->ID : (int) $post ); return '' !== $row['title'] ? $row['title'] : ( is_object( $post ) ? (string) $post->post_title : '' ); }
function get_the_excerpt( $post ) { return 'An excerpt for the lead story.'; }
function attachment_url_to_postid( $url ) { return 0; }
function wp_get_attachment_image( ...$args ) { return ''; }
function wp_count_posts( $type ) { return (object) array( 'publish' => 248 ); }
function lunara_get_home_identity_logo_id() { return 0; }
function lunara_home_select_setting( $key, $default, $allowed ) { return $default; }
function lunara_home_brand_number_setting( $key, $default, $min, $max ) { return $default; }
function lunara_get_latest_review_post() { return new WP_Post( 11, lunara_test_review( 11 )['title'] ); }
// The cover switch (inc/cinematic-home.php, its own contract in home-opener-runtime.php).
function lunara_home_cover_is_enabled() { return $GLOBALS['lunara_test']['cover'] ?? true; }
function lunara_get_review_hero_image_url( $id ) { return lunara_test_review( (int) $id )['still']; }
function lunara_rightsize_backdrop_url( $url ) { return str_replace( '/original/', '/w1280/', (string) $url ); }
function get_post_thumbnail_id( $id ) { return (int) ( lunara_test_review( (int) $id )['thumb'] ?? 0 ); }
function wp_get_attachment_image_url( $id, $size = 'full' ) { foreach ( $GLOBALS['lunara_test']['journal'] as $row ) { if ( ( $row['thumb'] ?? 0 ) === (int) $id ) { return $row['image']; } } return ( $GLOBALS['lunara_test']['artwork'] ?? array() )[ (int) $id ] ?? ''; }
function lunara_get_home_ledger_band_data() { return $GLOBALS['lunara_test']['band']; }
function lunara_get_cinematic_hero_slides( $count ) {
	if ( ! $GLOBALS['lunara_test']['slides'] ) {
		return array();
	}
	$lead = lunara_test_review( 11 );
	return array( array( 'kicker' => 'Lead Editorial', 'title' => $lead['title'], 'excerpt' => 'Slide excerpt.', 'url' => $lead['url'], 'cta' => 'Read the review', 'image' => 'https://image.tmdb.org/t/p/w1280/reckoning.jpg' ) );
}


// Token-extract the CDN candidate helpers from inc/setup.php (3.2.154), so the
// markup under test carries the candidates the site prints.
function lunara_test_extract_setup( $name ) {
	$tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/inc/setup.php' ) );
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
if ( ! function_exists( 'wp_parse_url' ) ) { function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); } }
if ( ! function_exists( 'remove_query_arg' ) ) { function remove_query_arg( $keys, $url ) { $parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $query ); foreach ( (array) $keys as $key ) { unset( $query[ $key ] ); } return strtok( $url, '?' ) . ( $query ? '?' . http_build_query( $query ) : '' ); } }
if ( ! function_exists( 'add_query_arg' ) ) { function add_query_arg( $args, $value = null, $url = null ) { if ( ! is_array( $args ) ) { $args = array( $args => $value ); } else { $url = $value; } $parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $query ); return strtok( $url, '?' ) . '?' . http_build_query( array_merge( $query, $args ) ); } }
foreach ( array( 'lunara_resize_wpcom_image_url', 'lunara_image_cdn_source', 'lunara_image_cdn_crop_url', 'lunara_image_cdn_width_url', 'lunara_image_source_size_from_url', 'lunara_image_upload_size', 'lunara_image_cdn_crop_limit', 'lunara_image_cdn_crop_srcset', 'lunara_image_cdn_width_srcset', 'lunara_image_sizes_for' ) as $lunara_helper ) { lunara_test_extract_setup( $lunara_helper ); }

require dirname( __DIR__ ) . '/inc/helpers.php';
require dirname( __DIR__ ) . '/inc/pillar-desk.php';
require dirname( __DIR__ ) . '/inc/home-front-desk.php';

// The shared headline splitter.
$split_cases = array(
	'Misty Green &#8211; The Price of Second Acts' => array( 'Misty Green', 'The Price of Second Acts' ),
	'The Social Reckoning – A Procedural Without a Pulse' => array( 'The Social Reckoning', 'A Procedural Without a Pulse' ),
	'Lurker — The Title Is the Position' => array( 'Lurker', 'The Title Is the Position' ),
	'Heat - The Long Goodbye' => array( 'Heat', 'The Long Goodbye' ),
	'Spider-Man: Brand New Day' => array( 'Spider-Man: Brand New Day' ),
	'Mary Shelley&#8217;s Frankenstein' => array( 'Mary Shelley’s Frankenstein' ),
);
foreach ( $split_cases as $input => $expected ) {
	$parts = lunara_split_review_headline( $input );
	if ( 2 === count( $expected ) ) {
		lunara_test_assert( 3 === count( $parts ) && $parts[0] === $expected[0] && $parts[2] === $expected[1], "split: {$input}" );
	} else {
		lunara_test_assert( 1 === count( $parts ) && $parts[0] === $expected[0], "whole: {$input}" );
	}
}

$html = lunara_render_home_front_door();

if ( in_array( '--fixture', $argv ?? array(), true ) ) {
	echo $html;
	exit( 0 );
}

// The cover opens with the dateline and mark, then the lead story and doors.
lunara_test_assert( 1 === substr_count( $html, 'class="lunara-home-masthead lunara-home-front-desk lunara-home-program lunara-home-doors is-cover' ), 'one program masthead section, the three-doors masthead, and on Home it is the cover' );
lunara_test_assert( false === strpos( $html, 'lunara-home-signal-bar' ) && false === strpos( $html, 'lunara-home-front-desk-signal' ) && false === strpos( $html, 'lunara-home-front-desk-search' ), 'the signal bar and signal cards are retired' );
lunara_test_assert( strpos( $html, 'lunara-home-program-dateline' ) < strpos( $html, 'lunara-home-masthead-identity' ) && strpos( $html, 'lunara-home-masthead-identity' ) < strpos( $html, 'lunara-home-front-desk-grid' ) && strpos( $html, 'lunara-home-front-desk-grid' ) < strpos( $html, 'lunara-home-masthead-routes' ), 'reading and source order: dateline, identity, Now Showing, and then the doors' );
lunara_test_assert( false !== strpos( $html, '<span>Dallas</span>' ) && false !== strpos( $html, '<span>October 2026</span>' ) && false !== strpos( $html, '<span>248 reviews</span>' ), 'the dateline carries place, month and the review count' );
lunara_test_assert( false !== strpos( $html, '<p class="lunara-home-masthead-dek">Reviews, news, and the Oscars. One film desk in Dallas.</p>' ), 'the dek says what Lunara Film is in one plain line' );
// The cover (3.2.130): the header keeps the search and drops its wordmark,
// so the masthead prints no second Search; it is the one mark on the page.
lunara_test_assert( false === strpos( $html, 'data-lunara-search-open' ) && false === strpos( $html, 'lunara-home-program-search' ), 'the cover masthead prints no second Search control' );

// The three doors: Reviews, Journal, Oscars, in that order, each with a word, one live line and a cue.
lunara_test_assert( 3 === substr_count( $html, 'class="lunara-home-masthead-route lunara-home-door ' ), 'three doors' );
lunara_test_assert( 1 === preg_match( '#<span class="lunara-home-masthead-route-label">Reviews</span>.*<span class="lunara-home-masthead-route-label">Journal</span>.*<span class="lunara-home-masthead-route-label">Oscars</span>#s', $html ), 'the doors read Reviews, Journal, Oscars, in that order' );
lunara_test_assert( 3 === substr_count( $html, '<span class="lunara-home-door-line">' ) && 3 === substr_count( $html, '<span class="lunara-home-door-cta">Enter <span aria-hidden="true">&rarr;</span></span>' ), 'every door carries one line and the Enter cue' );
lunara_test_assert( 1 === preg_match( '#<a class="lunara-home-masthead-route lunara-home-door is-door-reviews has-image" href="https://example.test/reviews/">(.+?)</a>#s', $html, $reviews_door ), 'the Reviews door opens the reviews archive' );
$reviews_image = lunara_test_img_attrs( $reviews_door[1], 'lunara-home-door-media' );
lunara_test_assert( 'lazy' === ( $reviews_image['loading'] ?? '' ) && ! isset( $reviews_image['fetchpriority'] ) && ! isset( $reviews_image['srcset'] ), 'the Reviews door defers the shared single source' );
lunara_test_assert( false !== strpos( $reviews_door[1], 'Updated October 18' ) && false !== strpos( $reviews_door[1], '248 reviews. Newest: The Social Reckoning.' ), 'the Reviews door shows the update, count, and newest film' );
lunara_test_assert( 1 === preg_match( '#<a class="lunara-home-masthead-route lunara-home-door is-door-journal has-image" href="https://example.test/journals/">(.+?)</a>#s', $html, $journal_door ), 'the Journal door opens the archive' );
$journal_image = lunara_test_img_attrs( $journal_door[1], 'lunara-home-door-media' );
lunara_test_assert( 'lazy' === ( $journal_image['loading'] ?? '' ) && ! isset( $journal_image['fetchpriority'] ) && lunara_image_sizes_for( 'front-door' ) === ( $journal_image['sizes'] ?? '' ), 'the Journal door defers a responsive image sized to its layout' );
lunara_test_assert( 1 === preg_match( '#gremlins-3-set-for-october-2028\.jpg#', $journal_image['src'] ?? '' ) && 1 === preg_match( '/ 420w, .* 640w, .* 840w, .* 1200w, .* 1600w$/', $journal_image['srcset'] ?? '' ), 'the Journal artwork uses uncropped, progressively sized candidates' );
lunara_test_assert( false !== strpos( $journal_door[1], 'Updated October 5' ) && false !== strpos( $journal_door[1], 'Film news. Latest: Gremlins 3 Slides to 2028, and It Needs to Pick a Lane.' ), 'the Journal door shows the update and newest entry' );
lunara_test_assert( 'journal' === ( WP_Query::$journal_args['post_type'] ?? '' ) && 'publish' === ( WP_Query::$journal_args['post_status'] ?? '' ) && 1 === ( WP_Query::$journal_args['posts_per_page'] ?? 0 ) && ! empty( WP_Query::$journal_args['no_found_rows'] ), 'the Journal door asks for one published entry and no row count' );
lunara_test_assert( 1 === preg_match( '#<a class="lunara-home-masthead-route lunara-home-door is-door-oscars has-no-image" href="https://example.test/oscars/">\s*<span class="lunara-home-door-veil" aria-hidden="true"></span>\s*<span class="lunara-home-door-status"><span class="lunara-pillar-desk-mark" aria-hidden="true"></span>On record</span>\s*<span class="lunara-home-masthead-route-label">Oscars</span>\s*<span class="lunara-home-door-line">98 ceremonies and 12,138 nominations, every nominee on record.</span>#s', $html ), 'without an Oscar pick the Oscars door is typographic (no 342px poster upscaled) and says the Ledger\'s counts' );

lunara_test_assert( 0 === preg_match( '#class="lunara-home-door-media[^>]*%2C#', $html ), 'door media candidates remain uncropped' );
lunara_test_assert( 3 === substr_count( $html, 'lunara-home-door-status' ) && 0 === substr_count( $html, 'lunara-home-door-status is-live' ), 'each door carries a freshness status, and an older date stays quiet' );
$GLOBALS['lunara_test']['journal'][0]['date'] = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d H:i:s' );
$live_doors = lunara_render_home_front_door();
$GLOBALS['lunara_test']['journal'][0]['date'] = '2026-10-05 15:00:00';
lunara_test_assert( 1 === substr_count( $live_doors, 'lunara-home-door-status is-live' ) && false !== strpos( $live_doors, '<span class="lunara-home-door-status is-live"><span class="lunara-pillar-desk-mark" aria-hidden="true"></span>Updated today</span>' ) && false !== strpos( $live_doors, '<span>Updated today</span>' ), 'a Journal that moved today marks that door live and adds Updated today to the dateline' );

// Lead story: first feature, with one priority still and a split headline.
lunara_test_assert( 1 === preg_match( '#<article class="lunara-home-front-desk-lead has-image">.+?<a class="lunara-home-front-desk-lead-link" href="https://example.test/reviews/the-social-reckoning/">.+?Now Showing.+?</article>#s', $html, $lead_article ), 'Now Showing links to the lead review' );
$lead_image = lunara_test_img_attrs( $lead_article[0], 'lunara-home-front-desk-lead-media' );
lunara_test_assert( 'eager' === ( $lead_image['loading'] ?? '' ) && 'high' === ( $lead_image['fetchpriority'] ?? '' ) && 1 === substr_count( $html, 'fetchpriority="high"' ), 'only the lead image has eager high-priority delivery' );
lunara_test_assert( lunara_image_sizes_for( 'front-desk-lead' ) === ( $lead_image['sizes'] ?? '' ) && ! isset( $lead_image['srcset'] ), 'the lead declares its rendered frame and keeps its single source' );
lunara_test_assert( 'https://image.tmdb.org/t/p/w1280/reckoning.jpg' === ( $lead_image['src'] ?? '' ) && ( $lead_image['src'] ?? '' ) === ( $reviews_image['src'] ?? '' ), 'the lead and Reviews door share the same right-sized still URL' );
lunara_test_assert( false !== strpos( $html, '<h2 class="lunara-home-front-desk-title"><span class="lunara-home-front-desk-film">The Social Reckoning</span><span class="lunara-screen-reader-text"> – </span><span class="lunara-home-front-desk-take">A Procedural Without a Pulse</span></h2>' ), 'the lead headline splits into film and take with the separator kept for assistive technology' );
lunara_test_assert( false === strpos( $html, 'lunara-home-front-desk-companion' ) && false === strpos( $html, 'lunara-home-front-desk-stack' ) && false === strpos( $html, 'misty' ), 'the companions left with the doors: the Running Order carries every review' );
lunara_test_assert( false !== strpos( $html, 'lunara-home-front-desk-grid has-no-companions' ), 'the lead grid says it stands alone' );

// Cover off (the lunara_home_cover_enabled filter): the header carries the
// wordmark again, and the masthead gets its Search button back.
$GLOBALS['lunara_test']['cover'] = false;
$uncovered = lunara_render_home_front_door();
$GLOBALS['lunara_test']['cover'] = true;
lunara_test_assert( false === strpos( $uncovered, ' is-cover' ) && 1 === substr_count( $uncovered, 'class="lunara-home-program-search" data-lunara-search-open' ), 'with the cover off the masthead keeps its own Search button' );

// Sweep Watch: with an Oscar pick the Oscars door takes its artwork and names the film.
$GLOBALS['lunara_test']['artwork'] = array( 3101 => 'https://example.test/wp-content/uploads/2026/07/The-Odyssey.jpg' );
// Declared conditionally so they exist only from here: PHP hoists unconditional declarations, and the first render must see no pick.
if ( ! function_exists( 'lunara_home_oscars_selection' ) ) {
	function lunara_home_oscars_selection( $kind, $year = 0 ) { return array( 'mode' => 'legacy', 'ids' => array() ); }
	function lunara_site_studio_home_oscars_lineup( $kind, $mode, $ids, $count, $year ) { $GLOBALS['lunara_test']['lineup_args'] = func_get_args(); return array( 31 ); }
	function get_post( $id ) { return 31 === (int) $id ? new WP_Post( 31, 'The Odyssey' ) : null; }
	function lunara_home_oscar_artwork( $kind, $post_id, $use_overrides = true ) { return array( 'image_id' => 3101, 'url' => $GLOBALS['lunara_test']['artwork'][3101], 'has_image' => true ); }
}
$sweep = lunara_render_home_front_door();
lunara_test_assert( array( 'picks', 'legacy', array(), 1, 0 ) === ( $GLOBALS['lunara_test']['lineup_args'] ?? null ), 'the Oscars door asks the picks lineup for one pick through the published selection' );
lunara_test_assert( 1 === preg_match( '#<a class="lunara-home-masthead-route lunara-home-door is-door-oscars has-image" href="https://example.test/oscars/">(.+?)</a>#s', $sweep, $oscars_door ), 'the Oscars door opens the Oscar Ledger' );
$oscars_image = lunara_test_img_attrs( $oscars_door[1], 'lunara-home-door-media' );
lunara_test_assert( 'lazy' === ( $oscars_image['loading'] ?? '' ) && ! isset( $oscars_image['fetchpriority'] ) && lunara_image_sizes_for( 'front-door' ) === ( $oscars_image['sizes'] ?? '' ), 'the Oscars door defers the responsive art' );
lunara_test_assert( false !== strpos( $oscars_image['src'] ?? '', 'The-Odyssey.jpg' ) && false !== strpos( $oscars_image['srcset'] ?? '', '1600w' ) && false !== strpos( $oscars_door[1], '98 ceremonies, 12,138 nominations, and Sweep Watch: The Odyssey.' ), 'with a pick the Oscars door shows the Sweep Watch artwork and names the film' );

// Without the Ledger's counts the Oscars door still says what the Oscars are.
$GLOBALS['lunara_test']['band'] = array();
$no_counts = lunara_render_home_front_door();
$GLOBALS['lunara_test']['band'] = array( 'ceremonies' => 98, 'record' => array( 'nominations' => 12138 ), 'posters' => array() );
lunara_test_assert( false !== strpos( $no_counts, '<span class="lunara-home-door-line">Every ceremony, every nominee, and Sweep Watch: The Odyssey.</span>' ), 'without counts the Oscars door keeps its plain line' );

// Without hero slides the lead falls back to the newest review with its right-sized still.
$GLOBALS['lunara_test']['slides'] = false;
$fallback = lunara_render_home_front_door();
lunara_test_assert( false !== strpos( $fallback, 'src="https://image.tmdb.org/t/p/w1280/reckoning.jpg"' ) && false !== strpos( $fallback, '>Now Showing<' ), 'the newest review leads when no hero slide is saved' );

// No Journal entry yet, no lead still: typographic doors, no empty frame.
$GLOBALS['lunara_test']['journal'] = array();
$GLOBALS['lunara_test']['reviews'][0]['still'] = '';
$bare = lunara_render_home_front_door();
lunara_test_assert( false !== strpos( $bare, 'is-door-reviews has-no-image' ) && false !== strpos( $bare, 'is-door-journal has-no-image' ) && 1 === substr_count( $bare, 'lunara-home-door-frame' ) && false !== strpos( $bare, '<span class="lunara-home-door-line">Film news, trailers, and quick reactions from the desk.</span>' ) && false !== strpos( $bare, 'lunara-home-front-desk-lead has-no-image' ) && 0 === preg_match( '#<span class="lunara-home-(door|front-desk)-frame"[^>]*>\s*</span>#', $bare ), 'a door or lead without a picture is typographic, never an empty frame' );

fwrite( STDOUT, "Home front desk: the three doors contract passed.\n" );
