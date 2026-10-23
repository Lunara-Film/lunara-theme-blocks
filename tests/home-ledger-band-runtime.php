<?php
/**
 * Behavior contract for the Home Oscar Ledger band (Direction D, 3.2.120).
 *
 * Renders inc/home-ledger-band.php against stubbed WordPress and a stubbed
 * Oscars data layer (the snapshot, the reader, the right-sizers) and asserts
 * what a visitor gets: one band with the live ceremony count in its
 * headline, the record line from the Ledger's totals, the door to the
 * portal, five Best Picture posters newest ceremony first with posterless
 * ceremonies skipped, lazy images with dimensions, no strip under three
 * posters, the latest-ceremony record when the hub accessor is missing,
 * nothing at all with zero ceremonies, and a payload that is cached and
 * flushed with the Ledger's data. `--fixture` prints the HTML for the
 * browser runtime.
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );

function lunara_test_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

/*
 * The fixture ledger: ceremony 98 is the latest. 96 has a Best Picture with
 * no poster of any kind and must be skipped; 94's poster is an uploaded
 * attachment (poster_html only, no TMDB URL); 92 has no Best Picture yet.
 */
$GLOBALS['lunara_test'] = array(
	'transients'   => array(),
	'rollup_calls' => array(),
	'hub_stats'    => true,
	'snapshot'     => true,
	'ceremonies'   => array(
		98 => array( 'film' => 'Latest Winner', 'film_id' => 'tt0000098', 'poster' => 'https://image.tmdb.org/t/p/original/latest.jpg' ),
		97 => array( 'film' => 'Winner Ninety-Seven', 'film_id' => 'tt0000097', 'poster' => 'https://image.tmdb.org/t/p/w500/ninety-seven.jpg' ),
		96 => array( 'film' => 'Posterless Winner', 'film_id' => 'tt0000096', 'poster' => '' ),
		95 => array( 'film' => 'Winner Ninety-Five', 'film_id' => 'tt0000095', 'poster' => 'https://image.tmdb.org/t/p/w500/ninety-five.jpg' ),
		94 => array( 'film' => 'Uploaded Poster Winner', 'film_id' => 'tt0000094', 'poster' => 'html:https://lunarafilm.files.wordpress.com/posters/ninety-four.jpg' ),
		93 => array( 'film' => 'Winner Ninety-Three', 'film_id' => 'tt0000093', 'poster' => 'https://image.tmdb.org/t/p/w500/ninety-three.jpg' ),
		92 => array(),
		91 => array( 'film' => 'Winner Ninety-One', 'film_id' => 'tt0000091', 'poster' => 'https://image.tmdb.org/t/p/w500/ninety-one.jpg' ),
	),
);

function lunara_test_visual( int $ceremony ): array {
	$row    = $GLOBALS['lunara_test']['ceremonies'][ $ceremony ] ?? array();
	$poster = (string) ( $row['poster'] ?? '' );
	if ( '' === $poster ) {
		return array( 'poster_url' => '', 'poster_html' => '' );
	}
	if ( 0 === strpos( $poster, 'html:' ) ) {
		$src = substr( $poster, 5 );
		return array( 'poster_url' => '', 'poster_html' => '<img class="aat-poster-img" src="' . $src . '" alt="' . $row['film'] . ' poster" loading="lazy" decoding="async" srcset="' . $src . ' 500w" sizes="300px">' );
	}
	return array( 'poster_url' => $poster, 'poster_html' => '' );
}

function lunara_test_best_picture( int $ceremony ): array {
	$row = $GLOBALS['lunara_test']['ceremonies'][ $ceremony ] ?? array();
	if ( empty( $row ) ) {
		return array();
	}
	return array(
		'canonical_category' => 'BEST PICTURE',
		'film'               => $row['film'],
		'film_id'            => $row['film_id'],
		'film_url'           => 'https://example.test/oscars/title/' . $row['film_id'] . '/',
		'year'               => (string) ( 1927 + $ceremony ),
	);
}

// WordPress.
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
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function absint( $n ) { return abs( (int) $n ); }
function get_transient( $key ) { return $GLOBALS['lunara_test']['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['lunara_test']['transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['lunara_test']['transients'][ $key ] ); return true; }

// The Oscars data layer: only what the band reads.
function lunara_oscars_dataset_cache_key( $key ) { return $key . '__stamp-a'; }
function lunara_resize_tmdb_image_url( $url, $size = 'w780' ) { return preg_replace( '#https://image\.tmdb\.org/t/p/(?:w\d+|original)(?=/)#i', 'https://image.tmdb.org/t/p/' . $size, (string) $url ); }
function lunara_rightsize_oscars_card_image_url( $url, $width = 780, $height = 1170 ) { return (string) $url . '?resize=' . $width . '%2C' . $height; }
function lunara_get_home_oscars_snapshot() {
	if ( ! $GLOBALS['lunara_test']['snapshot'] ) {
		return array();
	}
	$latest = lunara_test_best_picture( 98 );
	$latest['visual'] = lunara_test_visual( 98 );
	return array(
		'ceremony'          => 98,
		'ceremony_url'      => 'https://example.test/oscars/ceremony/98/',
		'database_url'      => 'https://example.test/oscars/',
		'best_picture'      => $latest,
		'categories_total'  => 23,
		'winner_rows_total' => 24,
	);
}

class Lunara_Test_Oscars_Reader {
	public function get_hub_page_stats() {
		if ( ! $GLOBALS['lunara_test']['hub_stats'] ) {
			return array();
		}
		return array( 'total_records' => 11642, 'total_winners' => 2473, 'total_categories' => 24, 'total_ceremonies' => 98, 'min_ceremony' => 1, 'max_ceremony' => 98 );
	}
	public function get_ceremony_rollup( $ceremony ) {
		$GLOBALS['lunara_test']['rollup_calls'][] = (int) $ceremony;
		$best = lunara_test_best_picture( (int) $ceremony );
		return array( 'ceremony' => (int) $ceremony, 'best_picture' => $best, 'categories_total' => 23 );
	}
	public function get_title_visual_package( $tt, $size = 'large' ) {
		foreach ( $GLOBALS['lunara_test']['ceremonies'] as $ceremony => $row ) {
			if ( ( $row['film_id'] ?? '' ) === $tt ) {
				return lunara_test_visual( (int) $ceremony );
			}
		}
		return array();
	}
	public function get_ceremony_url( $ceremony ) { return 'https://example.test/oscars/ceremony/' . (int) $ceremony . '/'; }
}
function lunara_oscars_reader() { return new Lunara_Test_Oscars_Reader(); }

require dirname( __DIR__ ) . '/inc/home-ledger-band.php';

$html = lunara_render_home_ledger_band();

if ( in_array( '--fixture', $argv ?? array(), true ) ) {
	echo $html;
	exit( 0 );
}

// One band, in the ledger slot, named for assistive technology, with its
// parts in reading order: kicker, headline, record, door, then the strip.
lunara_test_assert( 1 === substr_count( $html, '<section ' ) && 1 === substr_count( $html, 'class="lunara-home-section lunara-home-slot-ledger lunara-home-ledger-band"' ), 'one band renders, in the ledger slot' );
lunara_test_assert( false !== strpos( $html, 'data-lunara-site-studio-section="ledger"' ) && false !== strpos( $html, 'aria-label="The Oscar Ledger"' ), 'the band is addressable by Site Studio and named for assistive technology' );
$order = array( 'lunara-home-ledger-band-kicker', 'lunara-home-ledger-band-title', 'lunara-home-ledger-band-record', 'lunara-home-ledger-band-link', 'lunara-home-ledger-band-strip' );
for ( $i = 1; $i < count( $order ); $i++ ) {
	lunara_test_assert( false !== strpos( $html, $order[ $i - 1 ] ) && strpos( $html, $order[ $i - 1 ] ) < strpos( $html, $order[ $i ] ), "{$order[ $i - 1 ]} comes before {$order[ $i ]}" );
}

// The copy is built from the data: the live ceremony count, the Ledger's
// totals, the portal URL the snapshot carries.
lunara_test_assert( false !== strpos( $html, '<p class="lunara-home-ledger-band-kicker">Intermission · The Oscar Ledger</p>' ), 'the kicker reads Intermission · The Oscar Ledger' );
lunara_test_assert( false !== strpos( $html, '<h2 class="lunara-home-ledger-band-title">Walk 98 ceremonies through their posters.</h2>' ), 'the headline carries the live ceremony count as digits' );
lunara_test_assert( false !== strpos( $html, '<p class="lunara-home-ledger-band-record">24 categories, 11,642 nominations and 2,473 winners on the record.</p>' ), 'the record line states the Ledger totals' );
lunara_test_assert( 1 === preg_match( '#<a class="lunara-home-ledger-band-link" href="https://example\.test/oscars/">Open the Ledger <span aria-hidden="true">&rarr;</span></a>#', $html ), 'the door opens the portal at the URL the snapshot gives' );
preg_match( '#<div class="lunara-home-ledger-band-copy">(.*?)</div>#s', $html, $copy );
lunara_test_assert( ! empty( $copy[1] ) && 0 === preg_match( '/\d+(st|nd|rd|th)\b|ninety/i', $copy[1] ), 'the copy carries no ordinal or spelled-out numerals' );

// Five posters, newest ceremony first, each a Best Picture poster linking to
// its ceremony; the posterless ceremony is skipped, not framed empty.
preg_match_all( '#<a class="lunara-home-ledger-band-poster" href="([^"]+)">\s*<span class="lunara-home-ledger-band-frame"><img class="lunara-home-ledger-band-poster-media" src="([^"]+)"([^>]*)></span>\s*<span class="lunara-home-ledger-band-caption">([^<]+)</span>\s*</a>#s', $html, $posters, PREG_SET_ORDER );
lunara_test_assert( 5 === count( $posters ) && 5 === substr_count( $html, '<img ' ), 'five posters render' );
lunara_test_assert( array( 'https://example.test/oscars/ceremony/98/', 'https://example.test/oscars/ceremony/97/', 'https://example.test/oscars/ceremony/95/', 'https://example.test/oscars/ceremony/94/', 'https://example.test/oscars/ceremony/93/' ) === array_column( $posters, 1 ), 'posters run newest ceremony first and a ceremony without a poster is skipped for the next' );
lunara_test_assert( array( 'Latest Winner', 'Winner Ninety-Seven', 'Winner Ninety-Five', 'Uploaded Poster Winner', 'Winner Ninety-Three' ) === array_column( $posters, 4 ), 'each poster is captioned with its film from the data' );
lunara_test_assert( false === strpos( $html, 'Posterless' ) && 0 === substr_count( $html, 'lunara-home-ledger-band-frame"></span>' ), 'the posterless winner never appears, framed or named' );
lunara_test_assert( array( 97, 96, 95, 94, 93 ) === $GLOBALS['lunara_test']['rollup_calls'], 'only the ceremonies after the snapshot are read from the plugin, newest first, and only until the strip is full' );

// Posters are lazy, dimensioned, decorative (the caption names the link) and
// right-sized: TMDB at w342 with strip-width candidates, uploads through the
// card right-sizer with no invented candidates.
foreach ( $posters as $poster ) {
	lunara_test_assert( false !== strpos( $poster[3], ' alt="" width="342" height="513" loading="lazy" decoding="async"' ), "poster {$poster[4]} is lazy, dimensioned and decorative" );
}
lunara_test_assert( 'https://image.tmdb.org/t/p/w342/latest.jpg' === $posters[0][2] && false !== strpos( $posters[0][3], 'srcset="https://image.tmdb.org/t/p/w185/latest.jpg 185w, https://image.tmdb.org/t/p/w342/latest.jpg 342w, https://image.tmdb.org/t/p/w500/latest.jpg 500w"' ) && false !== strpos( $posters[0][3], 'sizes="(max-width: 759px) 31vw, (max-width: 999px) 19vw, 140px"' ), 'a TMDB original is served at w342 with candidates for the strip widths' );
lunara_test_assert( 'https://image.tmdb.org/t/p/w342/ninety-seven.jpg' === $posters[1][2], 'a TMDB w500 poster is served at w342' );
lunara_test_assert( 'https://lunarafilm.files.wordpress.com/posters/ninety-four.jpg?resize=342%2C513' === $posters[3][2] && false === strpos( $posters[3][3], 'srcset=' ), 'an uploaded poster is read from poster_html and right-sized through the card helper, with no TMDB candidates' );

// The payload is cached under the dataset stamp and served from the cache,
// and the flush clears it.
lunara_test_assert( isset( $GLOBALS['lunara_test']['transients']['lunara_home_ledger_band_v1__stamp-a'] ), 'the band caches its payload under the dataset stamp' );
$GLOBALS['lunara_test']['rollup_calls'] = array();
lunara_test_assert( $html === lunara_render_home_ledger_band() && array() === $GLOBALS['lunara_test']['rollup_calls'], 'a second render comes from the cache without reading the plugin' );
lunara_flush_home_ledger_band_cache();
lunara_test_assert( array() === $GLOBALS['lunara_test']['transients'], 'the flush clears the cached payload' );

// Without the hub accessor the record falls back to the latest ceremony's
// own counts from the snapshot; nothing is invented for nominations.
$GLOBALS['lunara_test']['hub_stats'] = false;
$fallback = lunara_render_home_ledger_band();
lunara_test_assert( false !== strpos( $fallback, '<p class="lunara-home-ledger-band-record">The latest ceremony: 23 categories and 24 winners.</p>' ), 'without Ledger totals the record states the latest ceremony counts only' );
lunara_test_assert( false !== strpos( $fallback, 'Walk 98 ceremonies through their posters.' ) && 5 === substr_count( $fallback, '<img ' ), 'the headline and strip are unaffected by the record fallback' );
$GLOBALS['lunara_test']['hub_stats'] = true;
$GLOBALS['lunara_test']['transients'] = array();

// Fewer than three posters: the band renders without the strip.
$GLOBALS['lunara_test']['ceremonies'] = array(
	98 => $GLOBALS['lunara_test']['ceremonies'][98],
	97 => $GLOBALS['lunara_test']['ceremonies'][97],
	96 => $GLOBALS['lunara_test']['ceremonies'][96],
);
$sparse = lunara_render_home_ledger_band();
lunara_test_assert( 1 === substr_count( $sparse, '<section ' ) && false !== strpos( $sparse, 'lunara-home-ledger-band has-no-strip"' ), 'with fewer than three posters the band still renders and says it has no strip' );
lunara_test_assert( false === strpos( $sparse, 'lunara-home-ledger-band-strip' ) && false === strpos( $sparse, '<img ' ), 'no strip and no image under three posters' );
lunara_test_assert( false !== strpos( $sparse, 'Walk 98 ceremonies through their posters.' ) && false !== strpos( $sparse, 'Open the Ledger' ), 'the copy and the door remain without the strip' );
$GLOBALS['lunara_test']['transients'] = array();

// Zero ceremonies: nothing renders and nothing is cached.
$GLOBALS['lunara_test']['snapshot'] = false;
lunara_test_assert( '' === lunara_render_home_ledger_band() && array() === $GLOBALS['lunara_test']['transients'], 'with no ceremonies the band renders nothing and caches nothing' );

fwrite( STDOUT, "Home ledger band: contract passed.\n" );
