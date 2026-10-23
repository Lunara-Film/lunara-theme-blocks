<?php
/**
 * Home — Intermission: The Oscar Ledger (Direction D, 3.2.121).
 *
 * The full-bleed band between the review sections: "Walk 98 ceremonies
 * through their posters", a strip of Best Picture posters from the newest
 * ceremonies and the door to /oscars/. Rendered into the `ledger` homepage
 * slot (front-page.php). Owns its own stylesheet
 * (assets/css/lunara-home-ledger-band.css), printed on wp_head at 46.
 *
 * Data comes from the real Oscars builders, never from copy typed here: the
 * ceremony count, the latest Best Picture and the portal URL from
 * lunara_get_home_oscars_snapshot(); the record totals from the Ledger
 * plugin's get_hub_page_stats() through lunara_oscars_reader(); the earlier
 * ceremonies' Best Pictures from get_ceremony_rollup() and their posters from
 * get_title_visual_package(), the same resolution the portal uses. The band
 * caches its assembled payload for fifteen minutes under the dataset stamp
 * and clears it on the plugin's import and swap actions.
 *
 * Contract: lunara_render_home_ledger_band() returns a self-contained
 * <section class="lunara-home-section lunara-home-slot-ledger …"> or '' when
 * the Oscars data has no ceremonies. One poster per ceremony, newest first; a
 * ceremony whose Best Picture has no poster is skipped, never an empty
 * frame; fewer than three posters and the band renders without the strip.
 * Square corners, gold rules top and bottom, no ordinal numerals.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many posters the strip holds, and how many ceremonies it may walk to
 * fill them before giving up on a posterless run.
 */
if ( ! defined( 'LUNARA_HOME_LEDGER_BAND_POSTERS' ) ) {
	define( 'LUNARA_HOME_LEDGER_BAND_POSTERS', 5 );
}
if ( ! defined( 'LUNARA_HOME_LEDGER_BAND_MIN_POSTERS' ) ) {
	define( 'LUNARA_HOME_LEDGER_BAND_MIN_POSTERS', 3 );
}
if ( ! defined( 'LUNARA_HOME_LEDGER_BAND_CEREMONY_WALK' ) ) {
	define( 'LUNARA_HOME_LEDGER_BAND_CEREMONY_WALK', 10 );
}

/**
 * A poster URL sized for the strip, or '' when the visual package has none.
 *
 * Reads poster_url first, then the src of the plugin's poster_html (an
 * uploaded poster), as the portal does. TMDB posters are requested at w342,
 * the width above the widest slot the strip renders; wp.com-hosted uploads go
 * through the shared card right-sizer.
 *
 * @param mixed $visual A get_title_visual_package() payload.
 * @return string
 */
function lunara_home_ledger_band_poster_src( $visual ) {
	if ( ! is_array( $visual ) ) {
		return '';
	}

	$url = isset( $visual['poster_url'] ) && is_scalar( $visual['poster_url'] ) ? trim( (string) $visual['poster_url'] ) : '';
	if ( '' === $url && ! empty( $visual['poster_html'] ) && is_scalar( $visual['poster_html'] ) && preg_match( '/\ssrc="([^"]+)"/i', (string) $visual['poster_html'], $m ) ) {
		$url = trim( html_entity_decode( (string) $m[1], ENT_QUOTES, 'UTF-8' ) );
	}

	if ( '' === $url ) {
		return '';
	}

	if ( preg_match( '#^https://image\.tmdb\.org/t/p/#i', $url ) ) {
		return function_exists( 'lunara_resize_tmdb_image_url' ) ? (string) lunara_resize_tmdb_image_url( $url, 'w342' ) : $url;
	}

	return function_exists( 'lunara_rightsize_oscars_card_image_url' ) ? (string) lunara_rightsize_oscars_card_image_url( $url, 342, 513 ) : $url;
}

/**
 * Responsive candidates for a TMDB poster at the strip's widths, or ''.
 *
 * @param string $url Poster URL as returned by lunara_home_ledger_band_poster_src().
 * @return string
 */
function lunara_home_ledger_band_poster_srcset( $url ) {
	if ( ! preg_match( '#^(https://image\.tmdb\.org/t/p/)(?:w\d+|original)(/[^\s?\#"\'<>]+)$#i', trim( (string) $url ), $parts ) ) {
		return '';
	}

	$candidates = array();
	foreach ( array( 185, 342, 500 ) as $width ) {
		$candidates[] = $parts[1] . 'w' . $width . $parts[2] . ' ' . $width . 'w';
	}

	return implode( ', ', $candidates );
}

/**
 * The band's cache key, stamped with the live dataset when the plugin has one.
 *
 * @return string
 */
function lunara_home_ledger_band_cache_key() {
	$key = 'lunara_home_ledger_band_v1';

	return function_exists( 'lunara_oscars_dataset_cache_key' ) ? (string) lunara_oscars_dataset_cache_key( $key ) : $key;
}

/**
 * Drop the band's payload when the Ledger data changes.
 */
function lunara_flush_home_ledger_band_cache() {
	if ( ! function_exists( 'delete_transient' ) ) {
		return;
	}

	delete_transient( 'lunara_home_ledger_band_v1' );
	delete_transient( lunara_home_ledger_band_cache_key() );
}
add_action( 'aat_after_data_import', 'lunara_flush_home_ledger_band_cache' );
add_action( 'aat_ledger_swapped', 'lunara_flush_home_ledger_band_cache' );

/**
 * Everything the band prints, assembled from the Oscars data layer.
 *
 * @return array{
 *     ceremonies:int,
 *     portal_url:string,
 *     record:array{scope:string,categories:int,nominations:int,winners:int},
 *     posters:array<int,array{ceremony:int,title:string,src:string,srcset:string,url:string}>
 * }|array{}
 */
function lunara_get_home_ledger_band_data() {
	if ( ! function_exists( 'lunara_get_home_oscars_snapshot' ) ) {
		return array();
	}

	$cache_key = lunara_home_ledger_band_cache_key();
	$cached    = function_exists( 'get_transient' ) ? get_transient( $cache_key ) : false;
	if ( is_array( $cached ) && ! empty( $cached['ceremonies'] ) ) {
		return $cached;
	}

	$snapshot   = lunara_get_home_oscars_snapshot();
	$ceremonies = is_array( $snapshot ) ? absint( $snapshot['ceremony'] ?? 0 ) : 0;
	if ( $ceremonies <= 0 ) {
		return array();
	}

	$reader     = function_exists( 'lunara_oscars_reader' ) ? lunara_oscars_reader() : null;
	$portal_url = isset( $snapshot['database_url'] ) && is_scalar( $snapshot['database_url'] ) ? trim( (string) $snapshot['database_url'] ) : '';
	if ( '' === $portal_url ) {
		$portal_url = home_url( '/oscars/' );
	}

	// The record: whole-ledger totals from the plugin's hub accessor; without
	// it, the latest ceremony's own counts, which the snapshot always carries.
	$record = array(
		'scope'       => 'ledger',
		'categories'  => 0,
		'nominations' => 0,
		'winners'     => 0,
	);
	if ( $reader && method_exists( $reader, 'get_hub_page_stats' ) ) {
		$stats = $reader->get_hub_page_stats();
		if ( is_array( $stats ) ) {
			$record['categories']  = absint( $stats['total_categories'] ?? 0 );
			$record['nominations'] = absint( $stats['total_records'] ?? 0 );
			$record['winners']     = absint( $stats['total_winners'] ?? 0 );
		}
	}
	if ( 0 === $record['categories'] && 0 === $record['nominations'] && 0 === $record['winners'] ) {
		$record = array(
			'scope'       => 'latest',
			'categories'  => absint( $snapshot['categories_total'] ?? 0 ),
			'nominations' => 0,
			'winners'     => absint( $snapshot['winner_rows_total'] ?? 0 ),
		);
	}

	// One Best Picture poster per ceremony, newest first. The latest ceremony
	// comes from the snapshot, which already resolved its visual; earlier
	// ceremonies come from the plugin's rollup and title visual package.
	$posters = array();
	$walked  = 0;
	for ( $n = $ceremonies; $n >= 1 && count( $posters ) < LUNARA_HOME_LEDGER_BAND_POSTERS && $walked < LUNARA_HOME_LEDGER_BAND_CEREMONY_WALK; $n--, $walked++ ) {
		if ( $n === $ceremonies ) {
			$best         = is_array( $snapshot['best_picture'] ?? null ) ? $snapshot['best_picture'] : array();
			$ceremony_url = isset( $snapshot['ceremony_url'] ) && is_scalar( $snapshot['ceremony_url'] ) ? trim( (string) $snapshot['ceremony_url'] ) : '';
		} else {
			if ( ! $reader || ! method_exists( $reader, 'get_ceremony_rollup' ) ) {
				break;
			}
			$rollup = $reader->get_ceremony_rollup( $n );
			$best   = is_array( $rollup ) && is_array( $rollup['best_picture'] ?? null ) ? $rollup['best_picture'] : array();
			if ( ! empty( $best['film_id'] ) && method_exists( $reader, 'get_title_visual_package' ) ) {
				$best['visual'] = $reader->get_title_visual_package( (string) $best['film_id'], 'medium' );
			}
			$ceremony_url = method_exists( $reader, 'get_ceremony_url' ) ? trim( (string) $reader->get_ceremony_url( $n ) ) : '';
		}

		$src = lunara_home_ledger_band_poster_src( $best['visual'] ?? null );
		if ( '' === $src ) {
			continue;
		}

		$url = $ceremony_url;
		if ( '' === $url && ! empty( $best['film_url'] ) && is_scalar( $best['film_url'] ) ) {
			$url = trim( (string) $best['film_url'] );
		}

		$posters[] = array(
			'ceremony' => $n,
			'title'    => isset( $best['film'] ) && is_scalar( $best['film'] ) ? trim( (string) $best['film'] ) : '',
			'src'      => $src,
			'srcset'   => lunara_home_ledger_band_poster_srcset( $src ),
			'url'      => '' !== $url ? $url : $portal_url,
		);
	}

	if ( count( $posters ) < LUNARA_HOME_LEDGER_BAND_MIN_POSTERS ) {
		$posters = array();
	}

	$data = array(
		'ceremonies' => $ceremonies,
		'portal_url' => $portal_url,
		'record'     => $record,
		'posters'    => $posters,
	);

	if ( function_exists( 'set_transient' ) ) {
		set_transient( $cache_key, $data, 15 * ( defined( 'MINUTE_IN_SECONDS' ) ? MINUTE_IN_SECONDS : 60 ) );
	}

	return $data;
}

/**
 * The one-line record under the headline, or '' when there is nothing to say.
 *
 * @param array $record The band data's record block.
 * @return string
 */
function lunara_home_ledger_band_record_line( $record ) {
	$categories  = absint( $record['categories'] ?? 0 );
	$nominations = absint( $record['nominations'] ?? 0 );
	$winners     = absint( $record['winners'] ?? 0 );

	$bits = array();
	if ( $categories > 0 ) {
		/* translators: %s: number of award categories. */
		$bits[] = sprintf( _n( '%s category', '%s categories', $categories, 'lunara-film' ), number_format_i18n( $categories ) );
	}
	if ( $nominations > 0 ) {
		/* translators: %s: number of nominations. */
		$bits[] = sprintf( _n( '%s nomination', '%s nominations', $nominations, 'lunara-film' ), number_format_i18n( $nominations ) );
	}
	if ( $winners > 0 ) {
		/* translators: %s: number of winners. */
		$bits[] = sprintf( _n( '%s winner', '%s winners', $winners, 'lunara-film' ), number_format_i18n( $winners ) );
	}

	if ( empty( $bits ) ) {
		return '';
	}

	$last = array_pop( $bits );
	$list = empty( $bits ) ? $last : sprintf(
		/* translators: 1: comma-separated counts, 2: the final count. */
		__( '%1$s and %2$s', 'lunara-film' ),
		implode( ', ', $bits ),
		$last
	);

	if ( 'latest' === ( $record['scope'] ?? '' ) ) {
		/* translators: %s: the latest ceremony's counts. */
		return sprintf( __( 'The latest ceremony: %s.', 'lunara-film' ), $list );
	}

	/* translators: %s: the whole ledger's counts. */
	return sprintf( __( '%s on the record.', 'lunara-film' ), $list );
}

/**
 * Render the Oscar Ledger band.
 *
 * @return string
 */
function lunara_render_home_ledger_band() {
	$data = lunara_get_home_ledger_band_data();
	if ( empty( $data ) || empty( $data['ceremonies'] ) ) {
		return '';
	}

	$ceremonies = absint( $data['ceremonies'] );
	$headline   = sprintf(
		/* translators: %s: number of ceremonies in the Ledger. */
		_n( 'Walk %s ceremony through its posters.', 'Walk %s ceremonies through their posters.', $ceremonies, 'lunara-film' ),
		number_format_i18n( $ceremonies )
	);
	$record  = lunara_home_ledger_band_record_line( is_array( $data['record'] ?? null ) ? $data['record'] : array() );
	$posters = is_array( $data['posters'] ?? null ) ? $data['posters'] : array();
	$sizes   = '(max-width: 759px) 31vw, (max-width: 999px) 19vw, 140px';

	ob_start();
	?>
	<section class="lunara-home-section lunara-home-slot-ledger lunara-home-ledger-band<?php echo empty( $posters ) ? ' has-no-strip' : ''; ?>" data-lunara-site-studio-section="ledger" aria-label="<?php esc_attr_e( 'The Oscar Ledger', 'lunara-film' ); ?>">
		<div class="lunara-home-ledger-band-inner">
			<div class="lunara-home-ledger-band-copy">
				<p class="lunara-home-ledger-band-kicker"><?php esc_html_e( 'Intermission · The Oscar Ledger', 'lunara-film' ); ?></p>
				<h2 class="lunara-home-ledger-band-title"><?php echo esc_html( $headline ); ?></h2>
				<?php if ( '' !== $record ) : ?>
					<p class="lunara-home-ledger-band-record"><?php echo esc_html( $record ); ?></p>
				<?php endif; ?>
				<a class="lunara-home-ledger-band-link" href="<?php echo esc_url( $data['portal_url'] ); ?>"><?php esc_html_e( 'Open the Ledger', 'lunara-film' ); ?> <span aria-hidden="true">&rarr;</span></a>
			</div>
			<?php if ( ! empty( $posters ) ) : ?>
				<div class="lunara-home-ledger-band-strip">
					<?php foreach ( $posters as $poster ) : ?>
						<a class="lunara-home-ledger-band-poster" href="<?php echo esc_url( $poster['url'] ); ?>">
							<span class="lunara-home-ledger-band-frame"><img class="lunara-home-ledger-band-poster-media" src="<?php echo esc_url( $poster['src'] ); ?>"<?php if ( '' !== $poster['srcset'] ) : ?> srcset="<?php echo esc_attr( $poster['srcset'] ); ?>" sizes="<?php echo esc_attr( $sizes ); ?>"<?php endif; ?> alt="" width="342" height="513" loading="lazy" decoding="async"></span>
							<?php if ( '' !== $poster['title'] ) : ?>
								<span class="lunara-home-ledger-band-caption"><?php echo esc_html( $poster['title'] ); ?></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

/**
 * The band's stylesheet, at the cascade point after the home modules (44) and
 * the masthead variables (45).
 */
function lunara_home_ledger_band_styles() {
	if ( ! is_front_page() || ! function_exists( 'lunara_print_cacheable_stylesheet' ) ) {
		return;
	}

	lunara_print_cacheable_stylesheet( 'lunara-home-ledger-band', 'assets/css/lunara-home-ledger-band.css' );
}
add_action( 'wp_head', 'lunara_home_ledger_band_styles', 46 );
