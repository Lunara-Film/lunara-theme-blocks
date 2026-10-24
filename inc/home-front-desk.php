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
 * The two newest reviews after the lead story, each with its still.
 *
 * @param string $lead_url Permalink of the lead story, skipped here.
 * @param int    $count    How many companions.
 * @return array<int,array{title:string,url:string,image:string}>
 */
function lunara_get_home_front_door_companions( $lead_url, $count = 2 ) {
	$query = new WP_Query(
		array(
			'post_type'           => 'review',
			'post_status'         => 'publish',
			'posts_per_page'      => absint( $count ) + 1,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);

	$companions = array();
	foreach ( (array) $query->posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		$url = (string) get_permalink( $post );
		if ( '' !== $lead_url && untrailingslashit( $url ) === untrailingslashit( (string) $lead_url ) ) {
			continue;
		}
		$companions[] = array(
			'title' => (string) get_the_title( $post ),
			'url'   => $url,
			'image' => lunara_home_front_door_review_still_url( $post->ID ),
		);
		if ( count( $companions ) >= $count ) {
			break;
		}
	}

	return $companions;
}

/**
 * Render a front-desk still. The lead is the first-viewport LCP candidate;
 * companions load lazily.
 *
 * @param string $image_url Image URL.
 * @param bool   $is_lead   Whether this is the lead still.
 * @return string
 */
function lunara_home_front_door_still( $image_url, $is_lead ) {
	$image_url = trim( (string) $image_url );
	if ( '' === $image_url ) {
		return '';
	}

	$class = $is_lead ? 'lunara-home-front-desk-lead-media skip-lazy no-lazy' : 'lunara-home-front-desk-companion-media';
	$sizes = $is_lead ? '(max-width: 1100px) calc(100vw - 32px), 860px' : '(max-width: 1100px) calc(100vw - 32px), 420px';
	$attrs = array(
		'class'    => $class,
		'alt'      => '',
		'loading'  => $is_lead ? 'eager' : 'lazy',
		'decoding' => 'async',
		'sizes'    => $sizes,
	);
	if ( $is_lead ) {
		$attrs['fetchpriority'] = 'high';
	}

	$attachment_id = function_exists( 'attachment_url_to_postid' )
		? absint( attachment_url_to_postid( (string) preg_replace( '/\?.*$/', '', $image_url ) ) )
		: 0;

	if ( $attachment_id ) {
		$image_html = wp_get_attachment_image( $attachment_id, 'lunara-hero-spotlight', false, $attrs );
		if ( '' !== trim( (string) $image_html ) ) {
			return (string) $image_html;
		}
	}

	return sprintf(
		'<img class="%1$s" src="%2$s" alt="" width="1920" height="1080" loading="%3$s" decoding="async"%4$s sizes="%5$s">',
		esc_attr( $class ),
		esc_url( $image_url ),
		$is_lead ? 'eager' : 'lazy',
		$is_lead ? ' fetchpriority="high"' : '',
		esc_attr( $sizes )
	);
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
	$lead_image  = lunara_home_front_door_still( $lead['image'], true );
	$companions  = lunara_get_home_front_door_companions( $lead['url'], 2 );
	$counts      = function_exists( 'wp_count_posts' ) ? wp_count_posts( 'review' ) : null;
	$review_total = is_object( $counts ) && isset( $counts->publish ) ? absint( $counts->publish ) : 0;
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

	$routes = array(
		array(
			'label' => __( 'Criticism', 'lunara-film' ),
			'title' => __( 'Read Reviews', 'lunara-film' ),
			'copy'  => __( 'Arguments, scores, trailers, Debrief notes, and spoiler files when the film needs a second door.', 'lunara-film' ),
			'url'   => $reviews_url,
		),
		array(
			'label' => __( 'Journal', 'lunara-film' ),
			'title' => __( 'Open Journal', 'lunara-film' ),
			'copy'  => __( 'Industry movement, trailer files, quick reactions, and larger work from the desk.', 'lunara-film' ),
			'url'   => $journal_url,
		),
		array(
			'label' => __( 'Oscar Ledger', 'lunara-film' ),
			'title' => __( 'Explore Ledger', 'lunara-film' ),
			'copy'  => __( 'Films, people, categories, and ceremonies connected across the record.', 'lunara-film' ),
			'url'   => home_url( '/oscars/' ),
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
	<section class="lunara-home-masthead lunara-home-front-desk lunara-home-program<?php echo $cover ? ' is-cover' : ''; ?> is-density-<?php echo esc_attr( $density ); ?> is-route-<?php echo esc_attr( $prominence ); ?>" aria-labelledby="lunara-home-masthead-title">
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
				<p class="lunara-home-masthead-dek"><?php esc_html_e( 'Reviews, Journal files, and the Oscar Ledger, edited as one publication in motion.', 'lunara-film' ); ?></p>
			</div>

			<div class="lunara-home-front-desk-grid<?php echo empty( $companions ) ? ' has-no-companions' : ''; ?>">
				<article class="lunara-home-front-desk-lead<?php echo $lead_image ? ' has-image' : ' has-no-image'; ?>">
					<a class="lunara-home-front-desk-lead-link" href="<?php echo esc_url( $lead['url'] ); ?>">
						<?php if ( $lead_image ) : ?>
							<span class="lunara-home-front-desk-frame"><?php echo $lead_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<?php endif; ?>
						<span class="lunara-home-front-desk-label"><?php esc_html_e( 'Now Showing', 'lunara-film' ); ?></span>
						<h2 class="lunara-home-front-desk-title"><?php echo lunara_home_front_door_headline_html( $lead['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
						<?php if ( ! empty( $lead['excerpt'] ) ) : ?>
							<p class="lunara-home-front-desk-copy"><?php echo esc_html( $lead['excerpt'] ); ?></p>
						<?php endif; ?>
						<span class="lunara-home-front-desk-cta"><?php echo esc_html( $lead['cta'] ); ?> <span aria-hidden="true">&rarr;</span></span>
					</a>
				</article>

				<?php if ( ! empty( $companions ) ) : ?>
					<div class="lunara-home-front-desk-stack" aria-label="<?php esc_attr_e( 'Also showing', 'lunara-film' ); ?>">
						<?php foreach ( $companions as $companion ) : ?>
							<?php $companion_image = lunara_home_front_door_still( $companion['image'], false ); ?>
							<a class="lunara-home-front-desk-companion<?php echo $companion_image ? ' has-image' : ' has-no-image'; ?>" href="<?php echo esc_url( $companion['url'] ); ?>">
								<?php if ( $companion_image ) : ?>
									<span class="lunara-home-front-desk-frame"><?php echo $companion_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php endif; ?>
								<span class="lunara-home-front-desk-label"><?php esc_html_e( 'Review', 'lunara-film' ); ?></span>
								<strong class="lunara-home-front-desk-companion-title"><?php echo lunara_home_front_door_headline_html( $companion['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<nav class="lunara-home-masthead-routes" aria-label="<?php esc_attr_e( 'Lunara Film section doors', 'lunara-film' ); ?>">
				<?php foreach ( $routes as $route ) : ?>
					<a class="lunara-home-masthead-route" href="<?php echo esc_url( $route['url'] ); ?>">
						<span class="lunara-home-masthead-route-label"><?php echo esc_html( $route['label'] ); ?></span>
						<strong><?php echo esc_html( $route['title'] ); ?></strong>
						<span><?php echo esc_html( $route['copy'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
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
