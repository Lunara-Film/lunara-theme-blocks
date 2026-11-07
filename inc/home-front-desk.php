<?php
/**
 * Home front desk (3.2.119): the masthead as a printed program.
 *
 * A dateline, the Lunara wordmark and dek, then "Now Showing": the lead story
 * with its landscape still and two companion reviews with theirs, then the
 * three section doors. Review headlines split into the film and the take, as
 * on the Review page. Site Studio and Customizer settings keep their meaning:
 * logo sizes, masthead padding and gap, density and route-door prominence and
 * height. The former live signal bar and the Journal/Ledger signal cards are
 * retired; Journal and the Ledger keep their doors and their own sections.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lunara_home_front_door_excerpt( $text, $words = 24 ) {
	$text = trim( wp_strip_all_tags( (string) $text ) );

	if ( '' === $text ) {
		return '';
	}

	return wp_trim_words( $text, max( 8, absint( $words ) ), '...' );
}

function lunara_get_home_front_door_lead() {
	if ( function_exists( 'lunara_get_cinematic_hero_slides' ) ) {
		$slides = lunara_get_cinematic_hero_slides( 1 );
		if ( ! empty( $slides[0] ) && is_array( $slides[0] ) ) {
			$slide = $slides[0];

			return array(
				'kicker'  => trim( (string) ( $slide['kicker'] ?? __( 'Lead Editorial', 'lunara-film' ) ) ),
				'title'   => trim( (string) ( $slide['title'] ?? get_bloginfo( 'name' ) ) ),
				'excerpt' => lunara_home_front_door_excerpt( $slide['excerpt'] ?? '', 30 ),
				'url'     => trim( (string) ( $slide['url'] ?? home_url( '/' ) ) ),
				'cta'     => trim( (string) ( $slide['cta'] ?? __( 'Read the file', 'lunara-film' ) ) ),
				'image'   => trim( (string) ( $slide['image'] ?? '' ) ),
			);
		}
	}

	$latest_review = function_exists( 'lunara_get_latest_review_post' ) ? lunara_get_latest_review_post() : null;
	if ( $latest_review instanceof WP_Post ) {
		return array(
			'kicker'  => __( 'Latest Review', 'lunara-film' ),
			'title'   => get_the_title( $latest_review ),
			'excerpt' => lunara_home_front_door_excerpt( get_the_excerpt( $latest_review ), 30 ),
			'url'     => get_permalink( $latest_review ),
			'cta'     => __( 'Read the review', 'lunara-film' ),
			'image'   => lunara_home_front_door_review_still_url( $latest_review->ID ),
		);
	}

	return array(
		'kicker'  => __( 'Lunara Film', 'lunara-film' ),
		'title'   => __( 'Film criticism, Journal files, and a living Oscar Ledger.', 'lunara-film' ),
		'excerpt' => __( 'The front door follows the work moving now: reviews, desk entries, and the archive record behind them.', 'lunara-film' ),
		'url'     => home_url( '/reviews/' ),
		'cta'     => __( 'Start with reviews', 'lunara-film' ),
		'image'   => '',
	);
}

/**
 * A review's landscape still, right-sized, or '' when it has none.
 *
 * @param int $review_id Review post ID.
 * @return string
 */
function lunara_home_front_door_review_still_url( $review_id ) {
	$image = function_exists( 'lunara_get_review_hero_image_url' )
		? trim( (string) lunara_get_review_hero_image_url( $review_id ) )
		: '';

	if ( '' !== $image && function_exists( 'lunara_rightsize_backdrop_url' ) ) {
		$image = lunara_rightsize_backdrop_url( $image );
	}

	return $image;
}

/**
 * Render a front-desk still. Until 3.2.161 the lead was the first-viewport
 * LCP candidate and two companions followed it; the companions left with the
 * three doors, and the lead now defers behind the Reviews door.
 *
 * @param string $image_url Image URL.
 * @param bool   $is_lead   Whether this is the lead still.
 * @return string
 */
function lunara_home_front_door_still( $image_url, $is_lead, $deferred = false ) {
	$image_url = trim( (string) $image_url );
	if ( '' === $image_url ) {
		return '';
	}

	// 3.2.161: under the three doors the lead still is below the first screen
	// and the Reviews door already carries this exact URL eagerly, so the
	// lead's own copy defers and the browser paints it from the one download.
	$deferred = $is_lead && $deferred;
	$class    = $is_lead ? 'lunara-home-front-desk-lead-media' . ( $deferred ? '' : ' skip-lazy no-lazy' ) : 'lunara-home-front-desk-companion-media';
	// 3.2.154: a companion is full width to 820px, 328-440px in the two-up
	// to 1100px and 300-417px beside the lead above; it is not the lead's width.
	$sizes = $is_lead
		? '(max-width: 1100px) calc(100vw - 32px), 860px'
		: ( function_exists( 'lunara_image_sizes_for' ) ? lunara_image_sizes_for( 'front-desk-companion' ) : '(max-width: 820px) calc(100vw - 64px), (max-width: 1100px) 440px, (max-width: 1280px) 340px, 420px' );
	$attrs = array(
		'class'    => $class,
		'alt'      => '',
		'loading'  => $is_lead && ! $deferred ? 'eager' : 'lazy',
		'decoding' => 'async',
		'sizes'    => $sizes,
	);
	if ( $is_lead && ! $deferred ) {
		$attrs['fetchpriority'] = 'high';
	}

	$attachment_id = function_exists( 'attachment_url_to_postid' )
		? absint( function_exists( 'lunara_attachment_id_for_url' ) ? lunara_attachment_id_for_url( (string) preg_replace( '/\?.*$/', '', $image_url ) ) : attachment_url_to_postid( (string) preg_replace( '/\?.*$/', '', $image_url ) ) )
		: 0;

	if ( $attachment_id ) {
		$image_html = wp_get_attachment_image( $attachment_id, 'lunara-hero-spotlight', false, $attrs );
		if ( '' !== trim( (string) $image_html ) ) {
			return (string) $image_html;
		}
	}

	// 3.2.152: a companion frame is 358px wide on a phone and 420px on desktop,
	// but its only source was the 1600px crop. The candidates are CDN crops to
	// the same 16:9 frame. The lead keeps its single source: its preload names
	// that exact URL, and a second candidate would be a second download.
	$still_set = ! $is_lead && function_exists( 'lunara_image_cdn_crop_srcset' )
		? lunara_image_cdn_crop_srcset( $image_url, 16, 9, array( 420, 640, 840, 1200, 1600 ), 1600 )
		: array( 'src' => '', 'srcset' => '' );

	return sprintf(
		'<img class="%1$s" src="%2$s" alt="" width="1920" height="1080" loading="%3$s" decoding="async"%4$s sizes="%5$s"%6$s>',
		esc_attr( $class ),
		esc_url( '' !== $still_set['src'] ? $still_set['src'] : $image_url ),
		$is_lead && ! $deferred ? 'eager' : 'lazy',
		$is_lead && ! $deferred ? ' fetchpriority="high"' : '',
		esc_attr( $sizes ),
		'' !== $still_set['srcset'] ? ' srcset="' . esc_attr( $still_set['srcset'] ) . '"' : ''
	);
}

/**
 * A door's picture (3.2.161): the still behind one of the three doors.
 *
 * A door is landscape on a phone and portrait on desktop, and its picture
 * covers the door, so no single crop fits: the candidates are whole-picture
 * downscales (the attachment's own sizes for an upload, CDN widths
 * otherwise), and the 'front-door' sizes slot names the width a covering
 * picture needs, the wider of the frame and its height at 16:9. The Reviews
 * door shares the lead's exact URL as a single source, so its eager fetch
 * (the first screen's largest picture) is the one download the lead's
 * preload and the lead still below both use.
 *
 * @param string $image_url Image URL.
 * @param int    $image_id  Attachment ID when the picture is an upload, else 0.
 * @param bool   $shared    Whether the URL is the lead still (single source, high priority).
 * @return string
 */
function lunara_home_front_door_door_still( $image_url, $image_id = 0, $shared = false ) {
	$image_url = trim( (string) $image_url );
	if ( '' === $image_url ) {
		return '';
	}

	$sizes = function_exists( 'lunara_image_sizes_for' )
		? lunara_image_sizes_for( 'front-door' )
		: '(max-width: 820px) max(calc(100vw - 68px), 444px), (max-width: 1300px) 69.4vw, min(69.4vw, 996px)';
	$attrs = array(
		'class'    => 'lunara-home-door-media' . ( $shared ? ' skip-lazy no-lazy' : '' ),
		'alt'      => '',
		'loading'  => 'eager',
		'decoding' => 'async',
		'sizes'    => $sizes,
	);
	if ( $shared ) {
		$attrs['fetchpriority'] = 'high';
	}

	if ( ! $shared ) {
		$image_id = absint( $image_id );
		if ( ! $image_id && function_exists( 'attachment_url_to_postid' ) ) {
			$clean    = (string) preg_replace( '/\?.*$/', '', $image_url );
			$image_id = absint( function_exists( 'lunara_attachment_id_for_url' ) ? lunara_attachment_id_for_url( $clean ) : attachment_url_to_postid( $clean ) );
		}
		if ( $image_id && function_exists( 'wp_get_attachment_image' ) ) {
			// 'full' with its sizes: WordPress lists the same-ratio renditions.
			$image_html = wp_get_attachment_image( $image_id, 'full', false, $attrs );
			if ( '' !== trim( (string) $image_html ) ) {
				return (string) $image_html;
			}
		}
	}

	$still_set = ! $shared && function_exists( 'lunara_image_cdn_width_srcset' )
		? lunara_image_cdn_width_srcset( $image_url, array( 420, 640, 840, 1200, 1600 ), 1600 )
		: array( 'src' => '', 'srcset' => '' );

	return sprintf(
		'<img class="%1$s" src="%2$s" alt="" width="1920" height="1080" loading="eager" decoding="async"%3$s sizes="%4$s"%5$s>',
		esc_attr( $attrs['class'] ),
		esc_url( '' !== $still_set['src'] ? $still_set['src'] : $image_url ),
		$shared ? ' fetchpriority="high"' : '',
		esc_attr( $sizes ),
		'' !== $still_set['srcset'] ? ' srcset="' . esc_attr( $still_set['srcset'] ) . '"' : ''
	);
}

/**
 * The film name alone from a review headline ("Film – Take" gives "Film").
 *
 * @param string $title Plain-text headline.
 * @return string
 */
function lunara_home_front_door_film_name( $title ) {
	$parts = function_exists( 'lunara_split_review_headline' ) ? lunara_split_review_headline( $title ) : array( (string) $title );

	return trim( (string) ( $parts[0] ?? $title ) );
}

/**
 * The newest published Journal entry, for the Journal door (3.2.161).
 *
 * @return array{title:string,url:string,image:string,image_id:int}
 */
function lunara_get_home_front_door_journal() {
	$empty = array(
		'title'    => '',
		'url'      => '',
		'image'    => '',
		'image_id' => 0,
	);
	if ( ! class_exists( 'WP_Query' ) ) {
		return $empty;
	}

	$query = new WP_Query(
		array(
			'post_type'           => 'journal',
			'post_status'         => 'publish',
			'posts_per_page'      => 1,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);
	$post  = $query->posts[0] ?? null;
	if ( ! $post instanceof WP_Post ) {
		return $empty;
	}

	$image_id = function_exists( 'get_post_thumbnail_id' ) ? absint( get_post_thumbnail_id( $post->ID ) ) : 0;
	$image    = $image_id && function_exists( 'wp_get_attachment_image_url' ) ? trim( (string) wp_get_attachment_image_url( $image_id, 'full' ) ) : '';

	return array(
		'title'    => trim( wp_strip_all_tags( (string) get_the_title( $post ) ) ),
		'url'      => (string) get_permalink( $post ),
		'image'    => $image,
		'image_id' => $image_id,
	);
}

/**
 * What the Oscars door says and shows (3.2.161): the Ledger's ceremony and
 * nomination counts from the band's cached data, and the Sweep Watch film from
 * the first Oscar pick with its artwork. Without a pick the door is
 * typographic: the band's posters are 342px renditions, and a door never
 * upscales a picture.
 *
 * @return array{line:string,image:string,image_id:int,ceremonies:int,nominations:int,sweep:string}
 */
function lunara_get_home_front_door_oscars() {
	$out = array(
		'line'        => '',
		'image'       => '',
		'image_id'    => 0,
		'ceremonies'  => 0,
		'nominations' => 0,
		'sweep'       => '',
	);

	if ( function_exists( 'lunara_get_home_ledger_band_data' ) ) {
		$band = lunara_get_home_ledger_band_data();
		if ( is_array( $band ) && ! empty( $band['ceremonies'] ) ) {
			$out['ceremonies']  = absint( $band['ceremonies'] );
			$out['nominations'] = absint( $band['record']['nominations'] ?? 0 );
		}
	}

	if ( function_exists( 'lunara_home_oscars_selection' ) && function_exists( 'lunara_site_studio_home_oscars_lineup' ) ) {
		$selection = lunara_home_oscars_selection( 'picks' );
		$lineup    = lunara_site_studio_home_oscars_lineup( 'picks', (string) ( $selection['mode'] ?? 'legacy' ), (array) ( $selection['ids'] ?? array() ), 1, 0 );
		$pick      = ! empty( $lineup[0] ) && function_exists( 'get_post' ) ? get_post( (int) $lineup[0] ) : null;
		if ( $pick instanceof WP_Post ) {
			$out['sweep'] = trim( wp_strip_all_tags( (string) get_the_title( $pick ) ) );
			if ( function_exists( 'lunara_home_oscar_artwork' ) ) {
				$artwork = lunara_home_oscar_artwork( 'picks', $pick->ID );
				if ( is_array( $artwork ) && ! empty( $artwork['has_image'] ) && ! empty( $artwork['url'] ) ) {
					$out['image']    = trim( (string) $artwork['url'] );
					$out['image_id'] = absint( $artwork['image_id'] ?? 0 );
				}
			}
		}
	}

	if ( $out['ceremonies'] > 0 && $out['nominations'] > 0 ) {
		$out['line'] = '' !== $out['sweep']
			? sprintf(
				/* translators: 1: number of ceremonies, 2: number of nominations, 3: the Sweep Watch film. */
				__( '%1$s ceremonies, %2$s nominations, and Sweep Watch: %3$s.', 'lunara-film' ),
				number_format_i18n( $out['ceremonies'] ),
				number_format_i18n( $out['nominations'] ),
				$out['sweep']
			)
			: sprintf(
				/* translators: 1: number of ceremonies, 2: number of nominations. */
				__( '%1$s ceremonies and %2$s nominations, every nominee on record.', 'lunara-film' ),
				number_format_i18n( $out['ceremonies'] ),
				number_format_i18n( $out['nominations'] )
			);
	} else {
		$out['line'] = '' !== $out['sweep']
			? sprintf(
				/* translators: %s: the Sweep Watch film. */
				__( 'Every ceremony, every nominee, and Sweep Watch: %s.', 'lunara-film' ),
				$out['sweep']
			)
			: __( 'Every ceremony, every nominee, and Sweep Watch.', 'lunara-film' );
	}

	return $out;
}

/**
 * Kept for callers of the 3.2.118 name; the lead still renderer.
 *
 * @param array $lead Front-door lead data.
 * @return string
 */
function lunara_home_front_door_lead_image( $lead ) {
	return lunara_home_front_door_still( (string) ( $lead['image'] ?? '' ), true );
}

/**
 * A headline as film and take, escaped for HTML.
 *
 * @param string $title Plain-text headline.
 * @param string $tag   Element for the film name.
 * @return string
 */
function lunara_home_front_door_headline_html( $title ) {
	$parts = function_exists( 'lunara_split_review_headline' ) ? lunara_split_review_headline( $title ) : array( (string) $title );
	if ( 3 === count( $parts ) ) {
		return '<span class="lunara-home-front-desk-film">' . esc_html( $parts[0] ) . '</span><span class="lunara-screen-reader-text">' . esc_html( $parts[1] ) . '</span><span class="lunara-home-front-desk-take">' . esc_html( $parts[2] ) . '</span>';
	}

	return '<span class="lunara-home-front-desk-film">' . esc_html( (string) $title ) . '</span>';
}

function lunara_render_home_front_door() {
	if ( function_exists( 'lunara_render_plugin_backed_home_hero' ) && function_exists( 'lunara_home_cinematic_front_door_is_enabled' ) && lunara_home_cinematic_front_door_is_enabled() ) {
		$cinematic_front_door = trim( (string) lunara_render_plugin_backed_home_hero() );

		if ( '' !== $cinematic_front_door ) {
			return $cinematic_front_door;
		}
	}

	$reviews_url = get_post_type_archive_link( 'review' ) ?: home_url( '/reviews/' );
	$journal_url = get_post_type_archive_link( 'journal' ) ?: home_url( '/journal/' );
	$logo_id     = lunara_get_home_identity_logo_id();
	$logo_html   = '';
	$density     = lunara_home_select_setting( 'lunara_home_front_door_density', 'editorial', array( 'compact', 'editorial', 'showcase' ) );
	$prominence  = lunara_home_select_setting( 'lunara_home_route_card_prominence', 'strong', array( 'quiet', 'standard', 'strong' ) );
	$lead        = lunara_get_home_front_door_lead();
	// 3.2.161: the three doors carry the first screen; the lead still below
	// them defers and paints from the Reviews door's download of the same URL.
	$lead_image  = lunara_home_front_door_still( $lead['image'], true, true );
	$journal     = lunara_get_home_front_door_journal();
	$oscars      = lunara_get_home_front_door_oscars();
	$counts      = function_exists( 'wp_count_posts' ) ? wp_count_posts( 'review' ) : null;
	$review_total = is_object( $counts ) && isset( $counts->publish ) ? absint( $counts->publish ) : 0;
	$lead_film    = lunara_home_front_door_film_name( $lead['title'] );
	// The cover (3.2.130): the header above carries no wordmark and keeps the
	// search, so this masthead is the brand and prints no second Search.
	$cover = ! function_exists( 'lunara_home_cover_is_enabled' ) || lunara_home_cover_is_enabled();

	if ( $logo_id ) {
		// Timing-immune sizing: the settled dimensions ride on the element, so
		// the wordmark never paints giant while a stylesheet is still arriving.
		$logo_desktop_w = lunara_home_brand_number_setting( 'lunara_home_logo_desktop_max_width', 1180, 520, 1600 );
		$logo_desktop_h = lunara_home_brand_number_setting( 'lunara_home_logo_desktop_max_height', 312, 140, 420 );
		$logo_html      = wp_get_attachment_image(
			$logo_id,
			'full',
			false,
			array(
				'class'         => 'lunara-home-masthead-logo skip-lazy no-lazy',
				'loading'       => 'eager',
				'decoding'      => 'async',
				'fetchpriority' => 'auto',
				'alt'           => '',
				'style'         => sprintf(
					'display:block;width:var(--lunara-masthead-logo-w,min(100%%,%dpx));height:auto;max-height:var(--lunara-masthead-logo-cap,clamp(118px,22vw,%dpx));object-fit:contain;',
					(int) $logo_desktop_w,
					(int) $logo_desktop_h
				),
			)
		);

		if ( is_string( $logo_html ) && '' !== $logo_html && class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$logo_processor = new WP_HTML_Tag_Processor( $logo_html );
			if ( $logo_processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
				$logo_processor->set_attribute( 'loading', 'eager' );
				$logo_processor->set_attribute( 'fetchpriority', 'auto' );
				$logo_html = $logo_processor->get_updated_html();
			}
		}
	}

	// The three doors (3.2.161): Reviews, Journal, Oscars, each a tall picture
	// with one live line under its word. The Reviews door shows the lead still
	// (one URL, one download, shared with the lead below); the Journal door the
	// newest entry's picture; the Oscars door the Sweep Watch artwork.
	if ( $review_total > 0 && '' !== $lead_film ) {
		$reviews_line = sprintf(
			/* translators: 1: number of published reviews, 2: the newest review's film. */
			_n( '%1$s review. Newest: %2$s.', '%1$s reviews. Newest: %2$s.', $review_total, 'lunara-film' ),
			number_format_i18n( $review_total ),
			$lead_film
		);
	} elseif ( $review_total > 0 ) {
		$reviews_line = sprintf(
			/* translators: %s: number of published reviews. */
			_n( '%s review, argued and scored.', '%s reviews, argued and scored.', $review_total, 'lunara-film' ),
			number_format_i18n( $review_total )
		);
	} else {
		$reviews_line = __( 'Every film, argued and scored.', 'lunara-film' );
	}

	$routes = array(
		array(
			'key'    => 'reviews',
			'label'  => __( 'Reviews', 'lunara-film' ),
			'line'   => $reviews_line,
			'url'    => $reviews_url,
			'image'  => $lead['image'],
			'id'     => 0,
			'shared' => true,
		),
		array(
			'key'    => 'journal',
			'label'  => __( 'Journal', 'lunara-film' ),
			// The line reserves two lines on the door; a headline alone fits them.
			'line'   => '' !== $journal['title']
				? sprintf(
					/* translators: %s: the newest Journal entry's title. */
					__( 'Film news. Latest: %s.', 'lunara-film' ),
					$journal['title']
				)
				: __( 'Film news, trailers, and quick reactions from the desk.', 'lunara-film' ),
			'url'    => $journal_url,
			'image'  => $journal['image'],
			'id'     => $journal['image_id'],
			'shared' => false,
		),
		array(
			'key'    => 'oscars',
			'label'  => __( 'Oscars', 'lunara-film' ),
			'line'   => $oscars['line'],
			'url'    => home_url( '/oscars/' ),
			'image'  => $oscars['image'],
			'id'     => $oscars['image_id'],
			'shared' => false,
		),
	);

	$dateline = array_filter(
		array(
			__( 'Dallas', 'lunara-film' ),
			function_exists( 'wp_date' ) ? wp_date( 'F Y' ) : gmdate( 'F Y' ),
			$review_total > 0 ? sprintf(
				/* translators: %s: number of published reviews. */
				_n( '%s review', '%s reviews', $review_total, 'lunara-film' ),
				number_format_i18n( $review_total )
			) : '',
		)
	);

	ob_start();
	?>
	<section class="lunara-home-masthead lunara-home-front-desk lunara-home-program lunara-home-doors<?php echo $cover ? ' is-cover' : ''; ?> is-density-<?php echo esc_attr( $density ); ?> is-route-<?php echo esc_attr( $prominence ); ?>" aria-labelledby="lunara-home-masthead-title">
		<div class="lunara-home-masthead-panel">
			<div class="lunara-home-program-dateline">
				<p class="lunara-home-program-dateline-items"><?php echo implode( '', array_map( static function ( $item ) { return '<span>' . esc_html( $item ) . '</span>'; }, $dateline ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php if ( ! $cover ) : ?>
				<button type="button" class="lunara-home-program-search" data-lunara-search-open><?php esc_html_e( 'Search', 'lunara-film' ); ?></button>
				<?php endif; ?>
			</div>

			<div class="lunara-home-masthead-identity">
				<h1 id="lunara-home-masthead-title" class="screen-reader-text lunara-screen-reader-text"><?php esc_html_e( 'Lunara Film', 'lunara-film' ); ?></h1>
				<div class="lunara-home-masthead-logo-frame" aria-hidden="true">
					<?php
					if ( $logo_html ) {
						echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						?>
						<span class="lunara-home-masthead-logo-fallback"><?php esc_html_e( 'Lunara Film', 'lunara-film' ); ?></span>
						<?php
					}
					?>
				</div>
				<p class="lunara-home-masthead-dek"><?php esc_html_e( 'Reviews, news, and the Oscars. One film desk in Dallas.', 'lunara-film' ); ?></p>
			</div>

			<nav class="lunara-home-masthead-routes" aria-label="<?php esc_attr_e( 'Lunara Film section doors', 'lunara-film' ); ?>">
				<?php foreach ( $routes as $route ) : ?>
					<?php $door_image = lunara_home_front_door_door_still( $route['image'], $route['id'], $route['shared'] ); ?>
					<a class="lunara-home-masthead-route lunara-home-door is-door-<?php echo esc_attr( $route['key'] ); ?><?php echo $door_image ? ' has-image' : ' has-no-image'; ?>" href="<?php echo esc_url( $route['url'] ); ?>">
						<?php if ( $door_image ) : ?>
							<span class="lunara-home-door-frame" aria-hidden="true"><?php echo $door_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<?php endif; ?>
						<span class="lunara-home-door-veil" aria-hidden="true"></span>
						<span class="lunara-home-masthead-route-label"><?php echo esc_html( $route['label'] ); ?></span>
						<span class="lunara-home-door-line"><?php echo esc_html( $route['line'] ); ?></span>
						<span class="lunara-home-door-cta"><?php esc_html_e( 'Enter', 'lunara-film' ); ?> <span aria-hidden="true">&rarr;</span></span>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="lunara-home-front-desk-grid has-no-companions">
				<article class="lunara-home-front-desk-lead<?php echo $lead_image ? ' has-image' : ' has-no-image'; ?>">
					<a class="lunara-home-front-desk-lead-link" href="<?php echo esc_url( $lead['url'] ); ?>">
						<?php if ( $lead_image ) : ?>
							<span class="lunara-home-front-desk-frame"><?php echo $lead_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<?php endif; ?>
						<div class="lunara-home-front-desk-text">
							<span class="lunara-home-front-desk-label"><?php esc_html_e( 'Now Showing', 'lunara-film' ); ?></span>
							<h2 class="lunara-home-front-desk-title"><?php echo lunara_home_front_door_headline_html( $lead['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
							<?php if ( ! empty( $lead['excerpt'] ) ) : ?>
								<p class="lunara-home-front-desk-copy"><?php echo esc_html( $lead['excerpt'] ); ?></p>
							<?php endif; ?>
							<span class="lunara-home-front-desk-cta"><?php echo esc_html( $lead['cta'] ); ?> <span aria-hidden="true">&rarr;</span></span>
						</div>
					</a>
				</article>
			</div>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

function lunara_home_front_door_css() {
	if ( ! is_front_page() ) {
		return;
	}
	$desktop_logo_width   = lunara_home_brand_number_setting( 'lunara_home_logo_desktop_max_width', 1180, 520, 1600 );
	$desktop_logo_height  = lunara_home_brand_number_setting( 'lunara_home_logo_desktop_max_height', 312, 140, 420 );
	$tablet_logo_width    = min( $desktop_logo_width, 1040 );
	$mobile_logo_width    = lunara_home_brand_number_setting( 'lunara_home_logo_mobile_max_width', 720, 280, 920 );
	$mobile_logo_height   = lunara_home_brand_number_setting( 'lunara_home_logo_mobile_max_height', 148, 88, 240 );
	$identity_logo_gap    = lunara_home_brand_number_setting( 'lunara_home_logo_vertical_gap', 20, 8, 48 );
	$masthead_top_pad     = lunara_home_brand_number_setting( 'lunara_home_masthead_top_padding', 36, 16, 72 );
	$masthead_bottom_pad  = lunara_home_brand_number_setting( 'lunara_home_masthead_bottom_padding', 32, 12, 70 );
	$masthead_bottom_gap  = lunara_home_brand_number_setting( 'lunara_home_masthead_bottom_gap', 26, 10, 72 );
	$route_card_min       = lunara_home_brand_number_setting( 'lunara_home_route_card_min_height', 126, 88, 190 );
	$mobile_top_pad       = max( 18, min( 30, $masthead_top_pad ) );
	$mobile_bottom_pad    = max( 16, min( 28, $masthead_bottom_pad ) );
	?>
	<style id="lunara-home-front-door-vars">
	body.home .lunara-home-masthead {
		--lunara-home-masthead-top-pad-setting: <?php echo esc_html( $masthead_top_pad ); ?>px;
		--lunara-home-masthead-bottom-pad-setting: <?php echo esc_html( $masthead_bottom_pad ); ?>px;
		--lunara-home-masthead-gap-setting: <?php echo esc_html( $masthead_bottom_gap ); ?>px;
		--lunara-home-route-card-min-setting: <?php echo esc_html( $route_card_min ); ?>px;
		--lunara-home-logo-gap-setting: <?php echo esc_html( $identity_logo_gap ); ?>px;
		--lunara-home-logo-desktop-width-setting: <?php echo esc_html( $desktop_logo_width ); ?>px;
		--lunara-home-logo-desktop-height-setting: <?php echo esc_html( $desktop_logo_height ); ?>px;
		--lunara-home-logo-tablet-width-setting: <?php echo esc_html( $tablet_logo_width ); ?>px;
		--lunara-home-logo-mobile-width-setting: <?php echo esc_html( $mobile_logo_width ); ?>px;
		--lunara-home-logo-mobile-height-setting: <?php echo esc_html( $mobile_logo_height ); ?>px;
		--lunara-home-masthead-mobile-gap-setting: <?php echo esc_html( max( 16, min( 34, $masthead_bottom_gap ) ) ); ?>px;
		--lunara-home-masthead-mobile-top-setting: <?php echo esc_html( $mobile_top_pad ); ?>px;
		--lunara-home-masthead-mobile-bottom-setting: <?php echo esc_html( $mobile_bottom_pad ); ?>px;
	}
	</style>
	<?php
}
add_action( 'wp_head', 'lunara_home_front_door_css', 45 );
