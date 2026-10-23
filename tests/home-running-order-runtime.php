<?php
/**
 * Behavior contract for The Running Order (Direction D, 3.2.120).
 *
 * Renders inc/home-running-order.php against stubbed WordPress and asserts
 * what a visitor gets: one section, the newest reviews first minus the three
 * the program masthead shows, the count link to the archive, lazy 2:3 posters
 * at TMDB w342 with their dimensions, a typographic card for a review without
 * a poster (or with its Card / Poster artwork switched off), the split
 * headline with its separator kept for assistive technology, the score, ''
 * with no reviews, the count filter's clamp, and the stylesheet hook. Run
 * with --fixture to print the HTML for the browser test.
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

function lunara_test_review_rows(): array {
	$rows = array(
		array( 'ID' => 11, 'title' => 'The Social Reckoning – A Procedural Without a Pulse', 'url' => 'https://example.test/reviews/the-social-reckoning/', 'poster' => 'https://image.tmdb.org/t/p/original/reckoning-poster.jpg', 'score' => '4' ),
		array( 'ID' => 12, 'title' => 'Practical Magic 2 &#8211; The Curdled Spell', 'url' => 'https://example.test/reviews/practical-magic-2/', 'poster' => '', 'score' => '2' ),
		array( 'ID' => 13, 'title' => 'Misty Green &#8211; The Price of Second Acts', 'url' => 'https://example.test/reviews/misty-green/', 'poster' => 'https://image.tmdb.org/t/p/original/misty-poster.jpg', 'score' => '3' ),
		array( 'ID' => 14, 'title' => 'Review Fourteen – The Fourteenth Take', 'url' => 'https://example.test/reviews/review-fourteen/', 'poster' => 'https://image.tmdb.org/t/p/original/fourteen.jpg', 'score' => '4' ),
		array( 'ID' => 15, 'title' => 'Review Fifteen &#8211; The Fifteenth Take', 'url' => 'https://example.test/reviews/review-fifteen/', 'poster' => 'https://image.tmdb.org/t/p/w500/fifteen.jpg', 'score' => '3.5' ),
		array( 'ID' => 16, 'title' => 'Review Sixteen', 'url' => 'https://example.test/reviews/review-sixteen/', 'poster' => '', 'score' => '2' ),
		array( 'ID' => 17, 'title' => 'Review Seventeen - The Seventeenth Take', 'url' => 'https://example.test/reviews/review-seventeen/', 'poster' => 'https://example.test/wp-content/uploads/2026/10/seventeen.jpg', 'score' => '' ),
		array( 'ID' => 18, 'title' => 'Review Eighteen – The Eighteenth Take', 'url' => 'https://example.test/reviews/review-eighteen/', 'poster' => 'https://image.tmdb.org/t/p/original/eighteen.jpg', 'score' => '5' ),
	);
	foreach ( range( 19, 40 ) as $id ) {
		$rows[] = array( 'ID' => $id, 'title' => "Review {$id} – Take {$id}", 'url' => "https://example.test/reviews/review-{$id}/", 'poster' => "https://image.tmdb.org/t/p/original/poster-{$id}.jpg", 'score' => '3' );
	}
	return $rows;
}

$GLOBALS['lunara_test'] = array(
	'reviews'      => lunara_test_review_rows(),
	'slides'       => true,
	'slide_url'    => 'https://example.test/reviews/the-social-reckoning/',
	'studio_off'   => array( 18 ),
	'filters'      => array(),
	'actions'      => array(),
	'front'        => true,
	'stylesheets'  => array(),
	'review_count' => 248,
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
	public static $last_args = array();
	public function __construct( array $args = array() ) {
		self::$last_args = $args;
		$limit = (int) ( $args['posts_per_page'] ?? 10 );
		foreach ( array_slice( $GLOBALS['lunara_test']['reviews'], 0, $limit ) as $row ) {
			$this->posts[] = new WP_Post( $row['ID'], $row['title'] );
		}
	}
}

class Lunara_Review_Image_Studio {
	public static function resolve_slot( $id, $slot ) {
		if ( in_array( (int) $id, $GLOBALS['lunara_test']['studio_off'], true ) ) {
			return array( 'mode' => 'off', 'url' => '', 'attachment_id' => 0 );
		}
		$poster = lunara_test_review( (int) $id )['poster'];
		return '' !== $poster ? array( 'mode' => 'auto', 'url' => $poster, 'attachment_id' => 0 ) : array();
	}
}

function lunara_test_review( int $id ): array {
	foreach ( $GLOBALS['lunara_test']['reviews'] as $row ) {
		if ( $row['ID'] === $id ) {
			return $row;
		}
	}
	return array( 'ID' => $id, 'title' => '', 'url' => '', 'poster' => '', 'score' => '' );
}

function add_action( $hook, $callback, $priority = 10 ): void { $GLOBALS['lunara_test']['actions'][] = array( $hook, $callback, $priority ); }
function add_filter( $hook, $callback, $priority = 10 ): void { $GLOBALS['lunara_test']['filters'][ $hook ][] = $callback; }
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['lunara_test']['filters'][ $hook ] ?? array() as $callback ) {
		$value = call_user_func( $callback, $value, ...$args );
	}
	return $value;
}
function is_front_page(): bool { return (bool) $GLOBALS['lunara_test']['front']; }
function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return (string) $url; }
function esc_html_e( $text, $domain = null ) { echo esc_html( $text ); }
function esc_attr_e( $text, $domain = null ) { echo esc_attr( $text ); }
function wp_kses_post( $html ) { return (string) $html; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function get_post_type_archive_link( $type ) { return 'https://example.test/' . $type . 's/'; }
function get_bloginfo( $what ) { return 'Lunara Film'; }
function get_theme_mod( $key, $default = false ) { return $default; }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) ); }
function absint( $n ) { return abs( (int) $n ); }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function wp_trim_words( $text, $num, $more ) { $w = preg_split( '/\s+/', trim( (string) $text ) ); return count( $w ) > $num ? implode( ' ', array_slice( $w, 0, $num ) ) . $more : $text; }
function get_permalink( $post ) { return lunara_test_review( is_object( $post ) ? (int) $post->ID : (int) $post )['url']; }
function get_the_title( $post ) { return lunara_test_review( is_object( $post ) ? (int) $post->ID : (int) $post )['title']; }
function get_the_excerpt( $post ) { return 'An excerpt for the lead story.'; }
function get_post_meta( $id, $key, $single = false ) {
	$row = lunara_test_review( (int) $id );
	return '_lunara_score' === $key ? $row['score'] : ( '_lunara_tmdb_poster_url' === $key ? $row['poster'] : '' );
}
function wp_count_posts( $type ) { return (object) array( 'publish' => $GLOBALS['lunara_test']['review_count'] ); }
function lunara_get_latest_review_post() { return new WP_Post( 11, lunara_test_review( 11 )['title'] ); }
function lunara_get_cinematic_hero_slides( $count ) {
	if ( ! $GLOBALS['lunara_test']['slides'] ) {
		return array();
	}
	return array( array( 'kicker' => 'Lead Editorial', 'title' => 'Lead', 'excerpt' => 'Slide excerpt.', 'url' => $GLOBALS['lunara_test']['slide_url'], 'cta' => 'Read', 'image' => '' ) );
}
// The Card / Poster owner in automatic mode: the TMDB poster, as the carousels see it.
function lunara_get_review_card_image_data( $id, $size, $attrs ) {
	$url = lunara_test_review( (int) $id )['poster'];
	return array( 'url' => $url, 'html' => '', 'has_image' => '' !== $url );
}
function lunara_render_stars( $score ) {
	$score = (float) $score;
	$html  = '<span class="lunara-stars">' . str_repeat( '<span class="lunara-star">&#9733;</span>', (int) floor( $score ) );
	if ( $score - floor( $score ) >= 0.5 ) {
		$html .= '<span class="lunara-star lunara-star--half">&frac12;</span>';
	}
	return $html . '</span>';
}
function lunara_print_cacheable_stylesheet( $handle, $path ) { $GLOBALS['lunara_test']['stylesheets'][] = array( $handle, $path ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function remove_query_arg( $keys, $url ) {
	$parts = parse_url( (string) $url ); parse_str( $parts['query'] ?? '', $query );
	foreach ( (array) $keys as $key ) { unset( $query[ $key ] ); }
	return strtok( (string) $url, '?' ) . ( $query ? '?' . http_build_query( $query ) : '' );
}
function add_query_arg( $args, $url ) {
	$parts = parse_url( (string) $url ); parse_str( $parts['query'] ?? '', $query );
	return strtok( (string) $url, '?' ) . '?' . http_build_query( array_merge( $query, (array) $args ) );
}

// The live image resizers, so the TMDB w342 swap and the upload crop are the real ones.
function lunara_test_extract( string $file, string $name ): void {
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
lunara_test_extract( 'inc/setup.php', 'lunara_resize_tmdb_image_url' );
lunara_test_extract( 'inc/setup.php', 'lunara_resize_wpcom_image_url' );
lunara_test_extract( 'inc/review-rendering.php', 'lunara_review_image_can_use_wpcom_resize' );

require dirname( __DIR__ ) . '/inc/helpers.php';
require dirname( __DIR__ ) . '/inc/home-front-desk.php';
require dirname( __DIR__ ) . '/inc/home-running-order.php';

function lunara_test_card_hrefs( string $html ): array {
	preg_match_all( '#<a class="lunara-home-running-order-card[^"]*" href="https://example\.test/reviews/([a-z0-9-]+)/"#', $html, $m );
	return $m[1];
}

function lunara_test_card( string $html, string $slug ): string {
	$start = strpos( $html, 'href="https://example.test/reviews/' . $slug . '/"' );
	lunara_test_assert( false !== $start, "a card for {$slug}" );
	$start = (int) strrpos( substr( $html, 0, $start ), '<li class="lunara-home-running-order-item">' );
	return substr( $html, $start, strpos( $html, '</li>', $start ) - $start );
}

$html = lunara_render_home_running_order();

if ( in_array( '--fixture', $argv ?? array(), true ) ) {
	echo $html;
	exit( 0 );
}

// Structure: one section in the featured slot, the head, then the wall.
lunara_test_assert( 1 === substr_count( $html, '<section class="lunara-home-section lunara-home-slot-featured lunara-home-running-order" data-lunara-site-studio-section="featured" aria-label="The Running Order">' ), 'one Running Order section in the featured slot' );
lunara_test_assert( 1 === substr_count( $html, '</section>' ), 'the section closes once' );
lunara_test_assert( false !== strpos( $html, '<p class="lunara-home-running-order-kicker">The Running Order</p>' ) && false !== strpos( $html, '<h2 class="lunara-home-running-order-title">Every review, newest first.</h2>' ), 'kicker and title' );
lunara_test_assert( strpos( $html, 'lunara-home-running-order-head' ) < strpos( $html, 'lunara-home-running-order-grid' ), 'the head precedes the wall' );
lunara_test_assert( false !== strpos( $html, '<a class="lunara-home-running-order-all" href="https://example.test/reviews/">All 248 reviews</a>' ), 'the count link names every published review and opens the archive' );

// Order and count: newest first, the masthead's three skipped, 12 by default.
$hrefs = lunara_test_card_hrefs( $html );
lunara_test_assert( 12 === count( $hrefs ) && 12 === substr_count( $html, '<li class="lunara-home-running-order-item">' ), 'twelve posters by default' );
lunara_test_assert( ! in_array( 'the-social-reckoning', $hrefs, true ) && ! in_array( 'practical-magic-2', $hrefs, true ) && ! in_array( 'misty-green', $hrefs, true ), 'the lead story and its two companions are not repeated on the wall' );
$expected = array_merge( array( 'review-fourteen', 'review-fifteen', 'review-sixteen', 'review-seventeen', 'review-eighteen' ), array_map( static fn( $id ) => "review-{$id}", range( 19, 25 ) ) );
lunara_test_assert( $expected === $hrefs, 'the wall runs newest first from the fourth-newest review' );
lunara_test_assert( ( WP_Query::$last_args['posts_per_page'] ?? 0 ) === 15 && 'review' === ( WP_Query::$last_args['post_type'] ?? '' ) && 'publish' === ( WP_Query::$last_args['post_status'] ?? '' ) && ! empty( WP_Query::$last_args['no_found_rows'] ), 'one bounded published-review query covers the wall plus the three it skips' );

// A poster: lazy, 2:3 dimensions, TMDB right-sized to w342 with a w500 candidate.
$fourteen = lunara_test_card( $html, 'review-fourteen' );
lunara_test_assert( 0 === strpos( trim( strip_tags( $fourteen ) ), 'Review Fourteen' ), 'a card opens with the film name: no numeral or label before it' );
lunara_test_assert( 1 === preg_match( '#<a class="lunara-home-running-order-card has-poster" href="https://example\.test/reviews/review-fourteen/">\s*<span class="lunara-home-running-order-frame"><img class="lunara-home-running-order-poster" src="https://image\.tmdb\.org/t/p/w342/fourteen\.jpg" alt="" width="342" height="513" loading="lazy" decoding="async" srcset="https://image\.tmdb\.org/t/p/w342/fourteen\.jpg 342w, https://image\.tmdb\.org/t/p/w500/fourteen\.jpg 500w" sizes="[^"]+"></span>#s', $fourteen ), 'a TMDB original becomes a lazy w342 poster with its dimensions and a w500 candidate' );
lunara_test_assert( false !== strpos( lunara_test_card( $html, 'review-fifteen' ), 'src="https://image.tmdb.org/t/p/w342/fifteen.jpg"' ), 'a stored w500 poster is right-sized too' );
$seventeen = lunara_test_card( $html, 'review-seventeen' );
lunara_test_assert( false !== strpos( $seventeen, 'src="https://example.test/wp-content/uploads/2026/10/seventeen.jpg?resize=342%2C513&quality=86&ssl=1" alt="" width="342" height="513" loading="lazy" decoding="async" sizes=' ) && false === strpos( $seventeen, 'srcset=' ), 'an uploaded card image is cropped to the frame through Site Accelerator, without a guessed srcset' );
lunara_test_assert( 0 === substr_count( $html, 'loading="eager"' ) && 0 === substr_count( $html, 'fetchpriority' ), 'the wall is below the fold: nothing eager' );

// No poster, or Card / Poster artwork switched off: a typographic card, never an empty frame.
$sixteen = lunara_test_card( $html, 'review-sixteen' );
lunara_test_assert( false !== strpos( $sixteen, 'class="lunara-home-running-order-card has-no-poster"' ) && false === strpos( $sixteen, '<img' ) && false !== strpos( $sixteen, '<span class="lunara-home-running-order-frame is-text"><span class="lunara-home-running-order-headline"><span class="lunara-home-running-order-film">Review Sixteen</span></span></span>' ), 'a review without a poster is a text card with its title in the frame' );
$eighteen = lunara_test_card( $html, 'review-eighteen' );
lunara_test_assert( false !== strpos( $eighteen, 'has-no-poster' ) && false === strpos( $eighteen, 'eighteen.jpg' ), 'Image Studio off keeps the poster off the wall' );
lunara_test_assert( 0 === preg_match( '#<span class="lunara-home-running-order-frame">\s*</span>#', $html ), 'no empty frame' );

// Headlines split into film and take; the separator stays for assistive technology.
lunara_test_assert( false !== strpos( $fourteen, '<span class="lunara-home-running-order-headline"><span class="lunara-home-running-order-film">Review Fourteen</span><span class="lunara-screen-reader-text"> – </span><span class="lunara-home-running-order-take">The Fourteenth Take</span></span>' ), 'the headline splits at the dash with the separator kept for screen readers' );
lunara_test_assert( false !== strpos( lunara_test_card( $html, 'review-fifteen' ), '<span class="lunara-home-running-order-film">Review Fifteen</span><span class="lunara-screen-reader-text"> – </span><span class="lunara-home-running-order-take">The Fifteenth Take</span>' ), 'an entity dash decodes before the split' );
lunara_test_assert( false !== strpos( $seventeen, '<span class="lunara-home-running-order-film">Review Seventeen</span><span class="lunara-screen-reader-text"> - </span><span class="lunara-home-running-order-take">The Seventeenth Take</span>' ), 'a spaced hyphen splits too' );
lunara_test_assert( false === strpos( $sixteen, 'lunara-home-running-order-take' ), 'a title without a take has no take span' );

// Score beneath the poster when the review has one.
lunara_test_assert( 1 === preg_match( '#<span class="lunara-home-running-order-score"><span class="lunara-stars">(<span class="lunara-star">&\#9733;</span>){4}</span></span>#', $fourteen ), 'a scored review shows its stars' );
lunara_test_assert( false !== strpos( lunara_test_card( $html, 'review-fifteen' ), 'lunara-star--half' ), 'a half score shows its half star' );
lunara_test_assert( false === strpos( $seventeen, 'lunara-home-running-order-score' ), 'an unscored review shows no score' );

// Only what the masthead shows is skipped: with a non-review lead, the two companions are the two newest reviews and the wall starts at the third.
$GLOBALS['lunara_test']['slide_url'] = 'https://example.test/journal/some-entry/';
$hrefs = lunara_test_card_hrefs( lunara_render_home_running_order() );
lunara_test_assert( 'misty-green' === ( $hrefs[0] ?? '' ) && ! in_array( 'the-social-reckoning', $hrefs, true ) && ! in_array( 'practical-magic-2', $hrefs, true ) && 12 === count( $hrefs ), 'with a journal lead the wall skips only the two companion reviews' );
$GLOBALS['lunara_test']['slide_url'] = 'https://example.test/reviews/the-social-reckoning/';

// The count filter, clamped to 6–24.
add_filter( 'lunara_home_running_order_count', static fn() => 8 );
lunara_test_assert( 8 === count( lunara_test_card_hrefs( lunara_render_home_running_order() ) ), 'the filter sets the count' );
$GLOBALS['lunara_test']['filters'] = array();
add_filter( 'lunara_home_running_order_count', static fn() => 3 );
lunara_test_assert( 6 === count( lunara_test_card_hrefs( lunara_render_home_running_order() ) ), 'the count never drops below six' );
$GLOBALS['lunara_test']['filters'] = array();
add_filter( 'lunara_home_running_order_count', static fn() => 40 );
$hrefs = lunara_test_card_hrefs( lunara_render_home_running_order() );
lunara_test_assert( 24 === count( $hrefs ) && 'review-37' === end( $hrefs ), 'the count never exceeds twenty-four' );
$GLOBALS['lunara_test']['filters'] = array();

// The count link with one review, and nothing to show at all.
$GLOBALS['lunara_test']['review_count'] = 1;
lunara_test_assert( false !== strpos( lunara_render_home_running_order(), '>All 1 review</a>' ), 'the count link reads singular for one review' );
$GLOBALS['lunara_test']['review_count'] = 248;
$GLOBALS['lunara_test']['reviews'] = array_slice( lunara_test_review_rows(), 0, 3 );
lunara_test_assert( '' === lunara_render_home_running_order(), 'when the masthead shows every review the wall renders nothing' );
$GLOBALS['lunara_test']['reviews'] = array();
$GLOBALS['lunara_test']['slides']  = false;
lunara_test_assert( '' === lunara_render_home_running_order(), 'with no reviews the wall renders nothing' );

// The stylesheet prints on Home only, at wp_head 46 after the masthead variables.
$hook = null;
foreach ( $GLOBALS['lunara_test']['actions'] as $action ) {
	if ( 'wp_head' === $action[0] && 'lunara_home_running_order_styles' === $action[1] ) {
		$hook = $action;
	}
}
lunara_test_assert( null !== $hook && 46 === $hook[2], 'the stylesheet hooks wp_head at 46' );
lunara_home_running_order_styles();
lunara_test_assert( array( array( 'lunara-home-running-order', 'assets/css/lunara-home-running-order.css' ) ) === $GLOBALS['lunara_test']['stylesheets'], 'Home prints the cacheable Running Order stylesheet' );
lunara_test_assert( is_file( dirname( __DIR__ ) . '/assets/css/lunara-home-running-order.css' ), 'the stylesheet exists' );
$GLOBALS['lunara_test']['front'] = false;
lunara_home_running_order_styles();
lunara_test_assert( 1 === count( $GLOBALS['lunara_test']['stylesheets'] ), 'other routes do not print it' );

fwrite( STDOUT, "Home Running Order: poster wall contract passed.\n" );
