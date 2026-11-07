<?php
/**
 * Behavior contract for the Home front desk (the program masthead, 3.2.119).
 *
 * Renders inc/home-front-desk.php against stubbed WordPress and asserts what
 * a visitor gets: the dateline, the lead story with its still and split
 * headline, two companions that skip the lead, lazy companions, the three
 * doors, and the retired signal bar staying retired. Also covers the shared
 * headline splitter the Review page uses.
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['lunara_test'] = array(
	'mods'    => array(),
	'reviews' => array(
		array( 'ID' => 11, 'title' => 'The Social Reckoning – A Procedural Without a Pulse', 'url' => 'https://example.test/reviews/the-social-reckoning/', 'still' => 'https://image.tmdb.org/t/p/original/reckoning.jpg' ),
		array( 'ID' => 12, 'title' => 'Practical Magic 2 &#8211; The Curdled Spell', 'url' => 'https://example.test/reviews/practical-magic-2/', 'still' => '' ),
		array( 'ID' => 13, 'title' => 'Misty Green &#8211; The Price of Second Acts', 'url' => 'https://example.test/reviews/misty-green/', 'still' => 'https://image.tmdb.org/t/p/original/misty.jpg' ),
		array( 'ID' => 14, 'title' => 'Lurker', 'url' => 'https://example.test/reviews/lurker/', 'still' => 'https://image.tmdb.org/t/p/original/lurker.jpg' ),
	),
	'slides'  => true,
);

function lunara_test_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

class WP_Post {
	public $ID;
	public $post_title;
	public function __construct( int $id, string $title ) {
		$this->ID         = $id;
		$this->post_title = $title;
	}
}

class WP_Query {
	public $posts = array();
	public function __construct( array $args = array() ) {
		$limit = (int) ( $args['posts_per_page'] ?? 10 );
		foreach ( array_slice( $GLOBALS['lunara_test']['reviews'], 0, $limit ) as $row ) {
			$this->posts[] = new WP_Post( $row['ID'], $row['title'] );
		}
	}
}

function lunara_test_review( int $id ): array {
	foreach ( $GLOBALS['lunara_test']['reviews'] as $row ) {
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
function wp_date( $format ) { return 'October 2026'; }
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
function get_the_title( $post ) { return lunara_test_review( is_object( $post ) ? (int) $post->ID : (int) $post )['title']; }
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
foreach ( array( 'lunara_resize_wpcom_image_url', 'lunara_image_cdn_source', 'lunara_image_cdn_crop_url', 'lunara_image_cdn_width_url', 'lunara_image_source_size_from_url', 'lunara_image_cdn_crop_limit', 'lunara_image_cdn_crop_srcset', 'lunara_image_cdn_width_srcset', 'lunara_image_sizes_for' ) as $lunara_helper ) { lunara_test_extract_setup( $lunara_helper ); }

require dirname( __DIR__ ) . '/inc/helpers.php';
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

// Structure.
lunara_test_assert( 1 === substr_count( $html, 'class="lunara-home-masthead lunara-home-front-desk lunara-home-program is-cover' ), 'one program masthead section, and on Home it is the cover' );
lunara_test_assert( false === strpos( $html, 'lunara-home-signal-bar' ) && false === strpos( $html, 'lunara-home-front-desk-signal' ) && false === strpos( $html, 'lunara-home-front-desk-search' ), 'the signal bar and signal cards are retired' );
lunara_test_assert( strpos( $html, 'lunara-home-program-dateline' ) < strpos( $html, 'lunara-home-masthead-identity' ) && strpos( $html, 'lunara-home-masthead-identity' ) < strpos( $html, 'lunara-home-front-desk-grid' ) && strpos( $html, 'lunara-home-front-desk-grid' ) < strpos( $html, 'lunara-home-masthead-routes' ), 'dateline, identity, Now Showing, doors: in that order' );
lunara_test_assert( false !== strpos( $html, '<span>Dallas</span>' ) && false !== strpos( $html, '<span>October 2026</span>' ) && false !== strpos( $html, '<span>248 reviews</span>' ), 'the dateline carries place, month and the review count' );
// The cover (3.2.130): the header keeps the search and drops its wordmark,
// so the masthead prints no second Search; it is the one mark on the page.
lunara_test_assert( false === strpos( $html, 'data-lunara-search-open' ) && false === strpos( $html, 'lunara-home-program-search' ), 'the cover masthead prints no second Search control' );
lunara_test_assert( 3 === substr_count( $html, 'class="lunara-home-masthead-route"' ), 'three section doors remain' );

// Cover off (the lunara_home_cover_enabled filter): the header carries the
// wordmark again, and the masthead gets its Search button back.
$GLOBALS['lunara_test']['cover'] = false;
$uncovered = lunara_render_home_front_door();
$GLOBALS['lunara_test']['cover'] = true;
lunara_test_assert( false === strpos( $uncovered, ' is-cover' ) && 1 === substr_count( $uncovered, 'class="lunara-home-program-search" data-lunara-search-open' ), 'with the cover off the masthead keeps its own Search button' );

// Lead story.
lunara_test_assert( 1 === preg_match( '#<article class="lunara-home-front-desk-lead has-image">\s*<a class="lunara-home-front-desk-lead-link" href="https://example.test/reviews/the-social-reckoning/">\s*<span class="lunara-home-front-desk-frame"><img class="lunara-home-front-desk-lead-media skip-lazy no-lazy" src="https://image.tmdb.org/t/p/w1280/reckoning.jpg" alt="" width="1920" height="1080" loading="eager" decoding="async" fetchpriority="high"#s', $html ), 'the lead opens with its still as the eager, high-priority LCP image' );
lunara_test_assert( false !== strpos( $html, '<span class="lunara-home-front-desk-label">Now Showing</span>' ), 'the lead is labelled Now Showing' );
lunara_test_assert( false !== strpos( $html, '<h2 class="lunara-home-front-desk-title"><span class="lunara-home-front-desk-film">The Social Reckoning</span><span class="lunara-screen-reader-text"> – </span><span class="lunara-home-front-desk-take">A Procedural Without a Pulse</span></h2>' ), 'the lead headline splits into film and take with the separator kept for assistive technology' );

// Companions: the two newest reviews that are not the lead, lazy stills, title-card state when there is none.
lunara_test_assert( 2 === substr_count( $html, 'class="lunara-home-front-desk-companion ' ), 'two companions' );
lunara_test_assert( false === strpos( $html, 'href="https://example.test/reviews/the-social-reckoning/"><span class="lunara-home-front-desk-frame"><img class="lunara-home-front-desk-companion-media' ), 'the lead is not repeated as a companion' );
lunara_test_assert( false !== strpos( $html, 'class="lunara-home-front-desk-companion has-no-image" href="https://example.test/reviews/practical-magic-2/"' ), 'a review without a still is a text companion, not an empty frame' );
lunara_test_assert( 1 === preg_match( '#class="lunara-home-front-desk-companion has-image" href="https://example.test/reviews/misty-green/">\s*<span class="lunara-home-front-desk-frame"><img class="lunara-home-front-desk-companion-media" src="https://i0.wp.com/image.tmdb.org/t/p/original/misty.jpg\?resize=1200%2C675[^"]*" alt="" width="1920" height="1080" loading="lazy" decoding="async" sizes="\(max-width: 820px\) calc\(100vw - 64px\), \(max-width: 1100px\) 440px, \(max-width: 1280px\) 340px, 420px" srcset="[^"]*resize=420%2C236[^"]* 420w, [^"]* 640w, [^"]* 840w, [^"]*resize=1200%2C675[^"]* 1200w">#s', $html ), 'a companion still loads lazily from 16:9 crops at 420-1200px (its TMDB source is 1280 wide, so no 1600w and no 1280w a tenth above 1200), says the measured companion widths, and its src is the widest candidate (3.2.154)' );
lunara_test_assert( 1 === preg_match( '#<img class="lunara-home-front-desk-lead-media skip-lazy no-lazy" src="https://image.tmdb.org/t/p/w1280/reckoning.jpg" alt="" width="1920" height="1080" loading="eager" decoding="async" fetchpriority="high" sizes="\(max-width: 1100px\) calc\(100vw - 32px\), 860px">#', $html ), 'the lead still keeps its one preloaded source and no srcset' );
lunara_test_assert( false === strpos( $html, 'lurker' ), 'only two companions render' );
lunara_test_assert( false !== strpos( $html, '<span class="lunara-home-front-desk-film">Practical Magic 2</span><span class="lunara-screen-reader-text"> – </span><span class="lunara-home-front-desk-take">The Curdled Spell</span>' ), 'companion headlines split and decode the entity dash' );

// Without hero slides the lead falls back to the newest review with its right-sized still.
$GLOBALS['lunara_test']['slides'] = false;
$fallback = lunara_render_home_front_door();
lunara_test_assert( false !== strpos( $fallback, 'src="https://image.tmdb.org/t/p/w1280/reckoning.jpg"' ) && false !== strpos( $fallback, '>Now Showing<' ), 'the newest review leads when no hero slide is saved' );

// No companions: the grid says so, no empty stack.
$GLOBALS['lunara_test']['reviews'] = array( $GLOBALS['lunara_test']['reviews'][0] );
$alone = lunara_render_home_front_door();
lunara_test_assert( false !== strpos( $alone, 'lunara-home-front-desk-grid has-no-companions' ) && false === strpos( $alone, 'lunara-home-front-desk-stack' ), 'a lone review renders no empty companion stack' );

fwrite( STDOUT, "Home front desk: program masthead contract passed.\n" );
