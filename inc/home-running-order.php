<?php
/**
 * Home — The Running Order (Direction D, 3.2.121).
 *
 * The poster wall under the program masthead: the newest published reviews,
 * newest first, minus the three the masthead already shows. Rendered into the
 * `featured` homepage slot (front-page.php). Owns its own stylesheet
 * (assets/css/lunara-home-running-order.css) and nothing outside this file.
 *
 * Contract: lunara_render_home_running_order() returns a self-contained
 * <section class="lunara-home-section lunara-home-slot-featured …"> or '' when
 * there is nothing to show. Square corners, no ordinal numerals, 2:3 posters
 * from the review's Card / Poster artwork (TMDB w342), lazy below the fold.
 * A review without a poster is a typographic card, never an empty frame.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many posters the wall shows: 12 by default, 6 to 24 through the
 * `lunara_home_running_order_count` filter.
 *
 * @return int
 */
function lunara_home_running_order_count() {
	return max( 6, min( 24, (int) apply_filters( 'lunara_home_running_order_count', 12 ) ) );
}

/**
 * Permalinks of the reviews the program masthead already shows (the lead
 * story and its two companions), keyed without their trailing slash.
 *
 * @return array<string,true>
 */
function lunara_home_running_order_masthead_urls() {
	$urls = array();

	if ( ! function_exists( 'lunara_get_home_front_door_lead' ) ) {
		return $urls;
	}

	$lead     = lunara_get_home_front_door_lead();
	$lead_url = trim( (string) ( $lead['url'] ?? '' ) );
	if ( '' !== $lead_url ) {
		$urls[ untrailingslashit( $lead_url ) ] = true;
	}

	if ( function_exists( 'lunara_get_home_front_door_companions' ) ) {
		foreach ( (array) lunara_get_home_front_door_companions( $lead_url, 2 ) as $companion ) {
			$url = trim( (string) ( $companion['url'] ?? '' ) );
			if ( '' !== $url ) {
				$urls[ untrailingslashit( $url ) ] = true;
			}
		}
	}

	return $urls;
}

/**
 * The reviews on the wall: newest first, without the masthead's three.
 *
 * @param int $count How many to show.
 * @return WP_Post[]
 */
function lunara_get_home_running_order_reviews( $count ) {
	$count = max( 1, (int) $count );
	$skip  = lunara_home_running_order_masthead_urls();
	$query = new WP_Query(
		array(
			'post_type'           => 'review',
			'post_status'         => 'publish',
			'posts_per_page'      => $count + count( $skip ),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);

	$reviews = array();
	foreach ( (array) $query->posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		if ( isset( $skip[ untrailingslashit( (string) get_permalink( $post ) ) ] ) ) {
			continue;
		}
		$reviews[] = $post;
		if ( count( $reviews ) >= $count ) {
			break;
		}
	}

	return $reviews;
}

/**
 * A review's poster URL at TMDB w342, or '' when it has none or its Card /
 * Poster artwork is switched off in Review Image Studio.
 *
 * @param int $review_id Review post ID.
 * @return string
 */
function lunara_home_running_order_poster_url( $review_id ) {
	$review_id = (int) $review_id;

	if ( class_exists( 'Lunara_Review_Image_Studio' ) ) {
		$source = Lunara_Review_Image_Studio::resolve_slot( $review_id, 'card' );
		$mode   = isset( $source['mode'] ) ? (string) $source['mode'] : '';
		if ( 'off' === $mode || ( 'custom' === $mode && empty( $source['url'] ) ) ) {
			return '';
		}
	}

	$url = '';
	if ( function_exists( 'lunara_get_review_card_image_data' ) ) {
		$image = lunara_get_review_card_image_data( $review_id, 'lunara-review-card', array( 'class' => 'lunara-home-running-order-poster' ) );
		$url   = trim( (string) ( $image['url'] ?? '' ) );
	}
	if ( '' === $url ) {
		$url = trim( (string) get_post_meta( $review_id, '_lunara_tmdb_poster_url', true ) );
	}
	if ( '' === $url ) {
		return '';
	}
	if ( function_exists( 'lunara_resize_tmdb_image_url' ) ) {
		$url = (string) lunara_resize_tmdb_image_url( $url, 'w342' );
	}
	// A WordPress.com upload (the Card Image Override) is cropped to the frame
	// through Site Accelerator rather than served at its archive size.
	if ( function_exists( 'lunara_review_image_can_use_wpcom_resize' ) && function_exists( 'lunara_resize_wpcom_image_url' ) && lunara_review_image_can_use_wpcom_resize( $url ) && false === strpos( $url, 'resize=' ) ) {
		$url = (string) lunara_resize_wpcom_image_url( $url, 342, 513 );
	}

	return $url;
}

/**
 * The lazy 2:3 poster image for the wall. TMDB posters carry a w342/w500
 * srcset for dense screens; an upload arrives already cropped to the frame.
 *
 * @param string $url Poster URL.
 * @return string
 */
function lunara_home_running_order_poster_img( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	$srcset = '';
	if ( function_exists( 'lunara_resize_tmdb_image_url' ) && 1 === preg_match( '#^https://image\.tmdb\.org/t/p/w342/#i', $url ) ) {
		$srcset = $url . ' 342w, ' . lunara_resize_tmdb_image_url( $url, 'w500' ) . ' 500w';
	}

	return sprintf(
		'<img class="lunara-home-running-order-poster" src="%1$s" alt="" width="342" height="513" loading="lazy" decoding="async"%2$s sizes="(max-width: 759px) 30vw, (max-width: 1199px) 22vw, 200px">',
		esc_url( $url ),
		'' !== $srcset ? ' srcset="' . esc_attr( $srcset ) . '"' : ''
	);
}

/**
 * A headline as film and take, escaped for HTML, with the separator kept for
 * assistive technology.
 *
 * @param string $title Review headline.
 * @return string
 */
function lunara_home_running_order_headline_html( $title ) {
	$parts = function_exists( 'lunara_split_review_headline' ) ? lunara_split_review_headline( $title ) : array( (string) $title );
	if ( 3 === count( $parts ) ) {
		return '<span class="lunara-home-running-order-film">' . esc_html( $parts[0] ) . '</span><span class="lunara-screen-reader-text">' . esc_html( $parts[1] ) . '</span><span class="lunara-home-running-order-take">' . esc_html( $parts[2] ) . '</span>';
	}

	return '<span class="lunara-home-running-order-film">' . esc_html( (string) $parts[0] ) . '</span>';
}

/**
 * Render The Running Order poster wall.
 *
 * @return string
 */
function lunara_render_home_running_order() {
	$reviews = lunara_get_home_running_order_reviews( lunara_home_running_order_count() );
	if ( empty( $reviews ) ) {
		return '';
	}

	$archive_url = get_post_type_archive_link( 'review' ) ?: home_url( '/reviews/' );
	$counts      = function_exists( 'wp_count_posts' ) ? wp_count_posts( 'review' ) : null;
	$total       = is_object( $counts ) && isset( $counts->publish ) ? absint( $counts->publish ) : 0;
	$all_label   = $total > 0
		? sprintf(
			/* translators: %s: number of published reviews. */
			_n( 'All %s review', 'All %s reviews', $total, 'lunara-film' ),
			number_format_i18n( $total )
		)
		: __( 'All reviews', 'lunara-film' );

	ob_start();
	?>
	<section class="lunara-home-section lunara-home-slot-featured lunara-home-running-order" data-lunara-site-studio-section="featured" aria-label="<?php esc_attr_e( 'The Running Order', 'lunara-film' ); ?>">
		<div class="lunara-home-running-order-inner">
			<header class="lunara-home-running-order-head">
				<p class="lunara-home-running-order-kicker"><?php esc_html_e( 'The Running Order', 'lunara-film' ); ?></p>
				<h2 class="lunara-home-running-order-title"><?php esc_html_e( 'Every review, newest first.', 'lunara-film' ); ?></h2>
				<a class="lunara-home-running-order-all" href="<?php echo esc_url( $archive_url ); ?>"><?php echo esc_html( $all_label ); ?></a>
			</header>
			<ul class="lunara-home-running-order-grid" role="list">
				<?php foreach ( $reviews as $review ) : ?>
					<?php
					$poster = lunara_home_running_order_poster_img( lunara_home_running_order_poster_url( $review->ID ) );
					$score  = get_post_meta( $review->ID, '_lunara_score', true );
					$stars  = $score && function_exists( 'lunara_render_stars' ) ? trim( (string) lunara_render_stars( $score ) ) : '';
					$title  = lunara_home_running_order_headline_html( get_the_title( $review ) );
					?>
					<li class="lunara-home-running-order-item">
						<a class="lunara-home-running-order-card<?php echo '' !== $poster ? ' has-poster' : ' has-no-poster'; ?>" href="<?php echo esc_url( get_permalink( $review ) ); ?>">
							<?php if ( '' !== $poster ) : ?>
								<span class="lunara-home-running-order-frame"><?php echo $poster; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span class="lunara-home-running-order-headline"><?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php else : ?>
								<span class="lunara-home-running-order-frame is-text"><span class="lunara-home-running-order-headline"><?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></span>
							<?php endif; ?>
							<?php if ( '' !== $stars ) : ?>
								<span class="lunara-home-running-order-score"><?php echo wp_kses_post( $stars ); ?></span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

/**
 * The wall's stylesheet, printed on Home after the home modules (44) and the
 * masthead variables (45).
 */
function lunara_home_running_order_styles() {
	if ( ! is_front_page() ) {
		return;
	}

	lunara_print_cacheable_stylesheet( 'lunara-home-running-order', 'assets/css/lunara-home-running-order.css' );
}
add_action( 'wp_head', 'lunara_home_running_order_styles', 46 );
