<?php
/**
 * The living desk: Journal, Reviews, and the Oscars on one line.
 *
 * Home already opens on the three doors. The Journal archive, the Reviews
 * archive, and the Oscars portal use this same trio so a visitor can see what
 * moved and step to the other two pillars. No new palette, no new logo, no
 * motion. The current pillar is the one the visitor is already reading.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newest published post for a pillar, or null when the query cannot answer.
 *
 * @param string $post_type journal or review.
 * @return WP_Post|null
 */
function lunara_pillar_desk_latest( $post_type ) {
	if ( ! class_exists( 'WP_Query' ) ) {
		return null;
	}
	$query = new WP_Query(
		array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => 1,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);
	$post = ( is_object( $query ) && isset( $query->posts[0] ) ) ? $query->posts[0] : null;
	return $post instanceof WP_Post ? $post : null;
}

/**
 * A freshness line from a MySQL date in the site timezone.
 *
 * @param string $mysql_date Post date.
 * @return string
 */
function lunara_pillar_desk_fresh_label( $mysql_date ) {
	$mysql_date = trim( (string) $mysql_date );
	if ( '' === $mysql_date || '0000-00-00 00:00:00' === $mysql_date ) {
		return '';
	}
	$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
	try {
		$when = new DateTimeImmutable( $mysql_date, $timezone );
	} catch ( Exception $error ) {
		unset( $error );
		return '';
	}
	$today = new DateTimeImmutable( 'now', $timezone );
	if ( $when->format( 'Y-m-d' ) === $today->format( 'Y-m-d' ) ) {
		return __( 'Updated today', 'lunara-film' );
	}
	$yesterday = $today->modify( '-1 day' );
	if ( $when->format( 'Y-m-d' ) === $yesterday->format( 'Y-m-d' ) ) {
		return __( 'Updated yesterday', 'lunara-film' );
	}
	return sprintf(
		/* translators: %s: month and day, for example November 21. */
		__( 'Updated %s', 'lunara-film' ),
		function_exists( 'wp_date' ) ? wp_date( 'F j', $when->getTimestamp() ) : $when->format( 'F j' )
	);
}

/**
 * @param string $post_type journal or review.
 * @param string $path      Fallback path.
 * @return string
 */
function lunara_pillar_desk_url( $post_type, $path ) {
	if ( function_exists( 'get_post_type_archive_link' ) ) {
		$link = get_post_type_archive_link( $post_type );
		if ( is_string( $link ) && '' !== $link ) {
			return $link;
		}
	}
	return function_exists( 'home_url' ) ? home_url( $path ) : $path;
}

/**
 * Three pillars, in the order the doors use: Journal, Reviews, Oscars.
 *
 * @return array<int,array{key:string,label:string,url:string,title:string,fresh:string}>
 */
function lunara_pillar_desk_items() {
	$journal = lunara_pillar_desk_latest( 'journal' );
	$review  = lunara_pillar_desk_latest( 'review' );
	$journal_title = $journal instanceof WP_Post ? trim( wp_strip_all_tags( (string) get_the_title( $journal ) ) ) : '';
	$review_title  = $review instanceof WP_Post ? trim( wp_strip_all_tags( (string) get_the_title( $review ) ) ) : '';
	if ( '' !== $review_title && function_exists( 'lunara_home_front_door_film_name' ) ) {
		$film = trim( (string) lunara_home_front_door_film_name( $review_title ) );
		if ( '' !== $film ) {
			$review_title = $film;
		}
	}

	$oscars_title = '';
	if ( function_exists( 'lunara_get_home_front_door_oscars' ) ) {
		$oscars = lunara_get_home_front_door_oscars();
		if ( is_array( $oscars ) ) {
			$oscars_title = trim( (string) ( $oscars['line'] ?? '' ) );
		}
	}
	if ( '' === $oscars_title ) {
		$oscars_title = __( 'The Lunara Film Oscar Ledger', 'lunara-film' );
	}

	return array(
		array(
			'key'   => 'journal',
			'label' => __( 'Journal', 'lunara-film' ),
			'url'   => lunara_pillar_desk_url( 'journal', '/journal/' ),
			'title' => '' !== $journal_title ? $journal_title : __( 'Film news from the desk.', 'lunara-film' ),
			'fresh' => $journal instanceof WP_Post ? lunara_pillar_desk_fresh_label( (string) ( $journal->post_date ?? '' ) ) : '',
		),
		array(
			'key'   => 'reviews',
			'label' => __( 'Reviews', 'lunara-film' ),
			'url'   => lunara_pillar_desk_url( 'review', '/reviews/' ),
			'title' => '' !== $review_title ? $review_title : __( 'Criticism from the desk.', 'lunara-film' ),
			'fresh' => $review instanceof WP_Post ? lunara_pillar_desk_fresh_label( (string) ( $review->post_date ?? '' ) ) : '',
		),
		array(
			'key'   => 'oscars',
			'label' => __( 'Oscars', 'lunara-film' ),
			'url'   => function_exists( 'home_url' ) ? home_url( '/oscars/' ) : '/oscars/',
			'title' => $oscars_title,
			'fresh' => __( 'On record', 'lunara-film' ),
		),
	);
}

/**
 * Home dateline addition. Empty unless the Journal or a review moved today.
 *
 * @return string
 */
function lunara_pillar_desk_dateline_freshness() {
	$today = __( 'Updated today', 'lunara-film' );
	foreach ( lunara_pillar_desk_items() as $item ) {
		if ( in_array( $item['key'], array( 'journal', 'reviews' ), true ) && $today === $item['fresh'] ) {
			return $today;
		}
	}
	return '';
}

/**
 * The three-pillar nav. `$current` is journal, reviews, or oscars.
 *
 * @param string $current Pillar key for the page the visitor is on.
 * @return string
 */
function lunara_render_pillar_desk( $current ) {
	$current = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $current ) : strtolower( (string) $current );
	$items   = lunara_pillar_desk_items();

	ob_start();
	?>
	<nav class="lunara-pillar-desk" aria-label="<?php esc_attr_e( 'Journal, Reviews, and the Oscars', 'lunara-film' ); ?>">
		<?php foreach ( $items as $item ) : ?>
			<?php $is_current = $item['key'] === $current; ?>
			<a class="lunara-pillar-desk-link<?php echo $is_current ? ' is-current' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $is_current ? ' aria-current="page"' : ''; ?>>
				<span class="lunara-pillar-desk-kicker"><?php echo esc_html( $item['label'] ); ?></span>
				<span class="lunara-pillar-desk-title"><?php echo esc_html( $item['title'] ); ?></span>
				<?php if ( '' !== $item['fresh'] ) : ?>
					<span class="lunara-pillar-desk-fresh"><?php echo esc_html( $item['fresh'] ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>
	<?php
	return (string) ob_get_clean();
}
