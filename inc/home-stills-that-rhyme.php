<?php
/**
 * Home — Stills that Rhyme (Direction D, 3.2.120).
 *
 * Two films, one image idea: the lead review's landscape still beside the
 * still of its Theme Echo pairing from the Debrief, each captioned "Film,
 * Year." Rendered into the `pairing-desk` homepage slot (front-page.php) in
 * place of the Pair It With desk. Owns assets/css/lunara-home-stills-that-rhyme.css.
 *
 * The pair is read from the same data the Pair It With cards read, in the
 * same precedence: the Relational Trinity movie entity on the review
 * (`theme_echo_movie`, `counter_program_movie`, `career_context_movie`),
 * then the legacy text field (`_lunara_theme_echo`, …) through the shared
 * parser, via lunara_debrief_method_read_pairing(). Theme Echo is the "same
 * image idea" role; when it has no still the other roles are tried in Method
 * order. A partner still comes from the linked movie's `tmdb_backdrop_url`
 * (the Film Dossier hero), else the hero still of a published Lunara review
 * of that film, else the Oscars Ledger's TMDB backdrop for its IMDb id. Both
 * stills pass through lunara_rightsize_backdrop_url(), the masthead's 16:9
 * crop.
 *
 * When the lead has no pairing, or either still is missing, the module hands
 * back the legacy Pair It With desk: never an empty frame, never a
 * placeholder image. The resolved pair passes through the
 * `lunara_home_stills_that_rhyme_pair` filter before rendering so a Studio
 * control can supply or replace it later.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_is_published' ) ) {
	/**
	 * Whether a post is a published, unprotected post of the given type.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Post type.
	 * @return bool
	 */
	function lunara_home_stills_that_rhyme_is_published( $post_id, $type ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! function_exists( 'get_post' ) ) {
			return false;
		}
		$post = get_post( $post_id );

		return is_object( $post )
			&& $type === (string) ( $post->post_type ?? '' )
			&& 'publish' === (string) ( $post->post_status ?? '' )
			&& empty( $post->post_password );
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_is_internal_url' ) ) {
	/**
	 * Whether a URL points at this site (or is site-relative).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	function lunara_home_stills_that_rhyme_is_internal_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return false;
		}
		$parse = function_exists( 'wp_parse_url' ) ? 'wp_parse_url' : 'parse_url';
		$host  = strtolower( (string) $parse( $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return '/' === substr( $url, 0, 1 ) && '//' !== substr( $url, 0, 2 );
		}
		$home = function_exists( 'home_url' ) ? strtolower( (string) $parse( home_url( '/' ), PHP_URL_HOST ) ) : '';

		return '' !== $home && $home === $host;
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_year' ) ) {
	/**
	 * The four-digit year inside a stored year value, or ''.
	 *
	 * @param mixed $year Stored year.
	 * @return string
	 */
	function lunara_home_stills_that_rhyme_year( $year ) {
		return preg_match( '/\b((?:18|19|20)\d{2})\b/', (string) $year, $m ) ? $m[1] : '';
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_rightsize' ) ) {
	/**
	 * A still URL through the masthead's 16:9 right-sizing, or ''.
	 *
	 * @param string $url Image URL.
	 * @return string
	 */
	function lunara_home_stills_that_rhyme_rightsize( $url ) {
		$url = trim( (string) $url );
		if ( '' !== $url && function_exists( 'lunara_rightsize_backdrop_url' ) ) {
			$url = trim( (string) lunara_rightsize_backdrop_url( $url ) );
		}

		return $url;
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_lead_review_id' ) ) {
	/**
	 * The review behind the Home lead story, or the newest published review.
	 *
	 * The front-door lead is a URL (a hero slide may be a Journal entry); the
	 * newest review is checked first so the usual case costs no rewrite parse.
	 *
	 * @return int 0 when there is no published review.
	 */
	function lunara_home_stills_that_rhyme_lead_review_id() {
		$lead_url = '';
		if ( function_exists( 'lunara_get_home_front_door_lead' ) ) {
			$lead     = lunara_get_home_front_door_lead();
			$lead_url = is_array( $lead ) ? trim( (string) ( $lead['url'] ?? '' ) ) : '';
		}

		$latest_id = 0;
		if ( function_exists( 'lunara_get_latest_review_post' ) ) {
			$latest    = lunara_get_latest_review_post();
			$latest_id = is_object( $latest ) && ! empty( $latest->ID ) ? (int) $latest->ID : 0;
		}

		if ( '' !== $lead_url ) {
			if ( $latest_id && function_exists( 'get_permalink' ) && untrailingslashit( (string) get_permalink( $latest_id ) ) === untrailingslashit( $lead_url ) ) {
				return $latest_id;
			}
			if ( function_exists( 'url_to_postid' ) ) {
				$lead_id = (int) url_to_postid( $lead_url );
				if ( lunara_home_stills_that_rhyme_is_published( $lead_id, 'review' ) ) {
					return $lead_id;
				}
			}
		}

		return $latest_id;
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_review' ) ) {
	/**
	 * The lead review's side of the pair: film, take, year, link and still.
	 *
	 * @param int $review_id Review post ID.
	 * @return array<string,mixed>
	 */
	function lunara_home_stills_that_rhyme_review( $review_id ) {
		$review_id = (int) $review_id;
		$title     = (string) get_the_title( $review_id );
		$parts     = function_exists( 'lunara_split_review_headline' )
			? lunara_split_review_headline( $title )
			: array( html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$still     = function_exists( 'lunara_get_review_hero_image_url' )
			? (string) lunara_get_review_hero_image_url( $review_id )
			: '';

		return array(
			'id'    => $review_id,
			'film'  => trim( (string) ( $parts[0] ?? '' ) ),
			'take'  => 3 === count( $parts ) ? trim( (string) $parts[2] ) : '',
			'year'  => lunara_home_stills_that_rhyme_year( get_post_meta( $review_id, '_lunara_year', true ) ),
			'url'   => (string) get_permalink( $review_id ),
			'still' => lunara_home_stills_that_rhyme_rightsize( $still ),
		);
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_linked_movie_id' ) ) {
	/**
	 * The published movie entity a review links in a role (Relational Trinity).
	 *
	 * @param int                  $review_id Review post ID.
	 * @param array<string,string> $role      Role definition from lunara_debrief_method_roles().
	 * @return int 0 when the role has no published linked movie.
	 */
	function lunara_home_stills_that_rhyme_linked_movie_id( $review_id, $role ) {
		$movie_id = (int) get_post_meta( (int) $review_id, (string) ( $role['relation'] ?? '' ) . '_movie', true );

		return lunara_home_stills_that_rhyme_is_published( $movie_id, 'movie' ) ? $movie_id : 0;
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_ledger_backdrop' ) ) {
	/**
	 * The Oscars Ledger's TMDB backdrop for an IMDb title id, or ''.
	 *
	 * @param string $tt IMDb title id.
	 * @return string
	 */
	function lunara_home_stills_that_rhyme_ledger_backdrop( $tt ) {
		$tt = strtolower( trim( (string) $tt ) );
		if ( ! preg_match( '/^tt\d{7,8}$/', $tt ) || ! class_exists( 'Academy_Awards_Table' ) ) {
			return '';
		}
		$aat = Academy_Awards_Table::get_instance();
		if ( ! $aat || ! method_exists( $aat, 'get_title_visual_package' ) ) {
			return '';
		}
		$visual = $aat->get_title_visual_package( $tt, 'medium' );

		return is_array( $visual ) ? trim( (string) ( $visual['backdrop_url'] ?? '' ) ) : '';
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_partner' ) ) {
	/**
	 * The paired film in one role: identity from the Debrief resolver, then
	 * a landscape still and an internal destination.
	 *
	 * @param int                  $review_id Review post ID.
	 * @param array<string,string> $role      Role definition from lunara_debrief_method_roles().
	 * @return array<string,mixed>|null Null when the role has no pairing.
	 */
	function lunara_home_stills_that_rhyme_partner( $review_id, $role ) {
		$review_id = (int) $review_id;
		$pairing   = lunara_debrief_method_read_pairing( $review_id, $role );
		if ( ! is_array( $pairing ) || '' === trim( (string) ( $pairing['title'] ?? '' ) ) ) {
			return null;
		}

		$tt       = strtolower( trim( (string) ( $pairing['tt'] ?? '' ) ) );
		$href     = trim( (string) ( $pairing['href'] ?? '' ) );
		$internal = lunara_home_stills_that_rhyme_is_internal_url( $href );
		$movie_id = lunara_home_stills_that_rhyme_linked_movie_id( $review_id, $role );

		// A published Lunara review of the paired film: through the movie
		// entity, or the review destination the legacy parser resolved.
		$partner_review_id = 0;
		if ( $movie_id && function_exists( 'lunara_entity_review_for_movie' ) ) {
			$partner_review_id = (int) lunara_entity_review_for_movie( $movie_id );
		}
		if ( ! $partner_review_id && $internal && false !== strpos( $href, '/reviews/' ) && function_exists( 'url_to_postid' ) ) {
			$partner_review_id = (int) url_to_postid( $href );
		}
		if ( ! lunara_home_stills_that_rhyme_is_published( $partner_review_id, 'review' ) ) {
			$partner_review_id = 0;
		}

		$still = $movie_id ? trim( (string) get_post_meta( $movie_id, 'tmdb_backdrop_url', true ) ) : '';
		if ( '' === $still && $partner_review_id && function_exists( 'lunara_get_review_hero_image_url' ) ) {
			$still = trim( (string) lunara_get_review_hero_image_url( $partner_review_id ) );
		}
		if ( '' === $still && '' !== $tt ) {
			$still = lunara_home_stills_that_rhyme_ledger_backdrop( $tt );
		}

		// Destination: the internal page the resolver named (Film Dossier,
		// Lunara review or Ledger record); otherwise the lead review, where
		// the Debrief lives. External sources never leave the homepage.
		$url = $internal ? $href : '';
		if ( '' === $url && $partner_review_id ) {
			$url = (string) get_permalink( $partner_review_id );
		}
		if ( '' === $url ) {
			$url = (string) get_permalink( $review_id );
		}

		return array(
			'title'     => trim( (string) $pairing['title'] ),
			'year'      => lunara_home_stills_that_rhyme_year( $pairing['year'] ?? '' ),
			'tt'        => $tt,
			'movie_id'  => $movie_id,
			'review_id' => $partner_review_id,
			'url'       => $url,
			'still'     => lunara_home_stills_that_rhyme_rightsize( $still ),
		);
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_pair' ) ) {
	/**
	 * Resolve the pair: the lead review and the first Method role, Theme Echo
	 * first, whose paired film has a landscape still.
	 *
	 * @return array<string,mixed>|null Null when no complete pair exists.
	 */
	function lunara_home_stills_that_rhyme_pair() {
		if ( ! function_exists( 'lunara_debrief_method_roles' ) || ! function_exists( 'lunara_debrief_method_read_pairing' ) || ! function_exists( 'get_post_meta' ) || ! function_exists( 'get_permalink' ) || ! function_exists( 'get_the_title' ) ) {
			return null;
		}

		$review_id = lunara_home_stills_that_rhyme_lead_review_id();
		if ( $review_id <= 0 ) {
			return null;
		}

		$review = lunara_home_stills_that_rhyme_review( $review_id );
		if ( '' === $review['still'] || '' === $review['film'] ) {
			return null;
		}

		foreach ( lunara_debrief_method_roles() as $slug => $role ) {
			$partner = lunara_home_stills_that_rhyme_partner( $review_id, $role );
			if ( is_array( $partner ) && '' !== $partner['still'] ) {
				return array(
					'role'    => array(
						'slug'     => (string) $slug,
						'label'    => (string) ( $role['label'] ?? '' ),
						'question' => (string) ( $role['question'] ?? '' ),
					),
					'review'  => $review,
					'partner' => $partner,
				);
			}
		}

		return null;
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_pair_is_complete' ) ) {
	/**
	 * Whether a pair (resolved or filtered in) has everything the frames need.
	 *
	 * @param mixed $pair Candidate pair.
	 * @return bool
	 */
	function lunara_home_stills_that_rhyme_pair_is_complete( $pair ) {
		if ( ! is_array( $pair ) || empty( $pair['review'] ) || empty( $pair['partner'] ) || ! is_array( $pair['review'] ) || ! is_array( $pair['partner'] ) ) {
			return false;
		}
		foreach ( array( $pair['review'], $pair['partner'] ) as $side ) {
			if ( '' === trim( (string) ( $side['still'] ?? '' ) ) || '' === trim( (string) ( $side['url'] ?? '' ) ) ) {
				return false;
			}
		}

		return '' !== trim( (string) ( $pair['review']['film'] ?? '' ) ) && '' !== trim( (string) ( $pair['partner']['title'] ?? '' ) );
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_caption' ) ) {
	/**
	 * "Film, Year." or "Film." as plain text.
	 *
	 * @param string $title Film title.
	 * @param string $year  Year, may be ''.
	 * @return string
	 */
	function lunara_home_stills_that_rhyme_caption( $title, $year ) {
		$title = trim( (string) $title );
		$year  = trim( (string) $year );

		return '' !== $year ? $title . ', ' . $year . '.' : $title . '.';
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_still_html' ) ) {
	/**
	 * A lazy, dimensioned still. A local upload renders through WordPress for
	 * its srcset; anything else is a plain image at the 16:9 crop size.
	 *
	 * @param string $url Still URL.
	 * @return string
	 */
	function lunara_home_stills_that_rhyme_still_html( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$attachment_id = function_exists( 'attachment_url_to_postid' )
			? (int) attachment_url_to_postid( (string) preg_replace( '/\?.*$/', '', $url ) )
			: 0;
		if ( $attachment_id > 0 && function_exists( 'wp_get_attachment_image' ) ) {
			$html = wp_get_attachment_image(
				$attachment_id,
				'lunara-hero-spotlight',
				false,
				array(
					'class'    => 'lunara-home-stills-media',
					'alt'      => '',
					'loading'  => 'lazy',
					'decoding' => 'async',
					'sizes'    => '(max-width: 759px) calc(100vw - 32px), (max-width: 1360px) calc(50vw - 44px), 648px',
				)
			);
			if ( '' !== trim( (string) $html ) ) {
				return (string) $html;
			}
		}

		return sprintf(
			'<img class="lunara-home-stills-media" src="%s" alt="" width="1600" height="900" loading="lazy" decoding="async">',
			esc_url( $url )
		);
	}
}

if ( ! function_exists( 'lunara_render_home_stills_that_rhyme' ) ) {
	/**
	 * Render Stills that Rhyme, or the legacy Pair It With desk when no
	 * complete pair exists.
	 *
	 * @return string
	 */
	function lunara_render_home_stills_that_rhyme() {
		$pair = lunara_home_stills_that_rhyme_pair();

		/**
		 * Filter the resolved pair before it renders.
		 *
		 * @param array<string,mixed>|null $pair role (slug, label, question),
		 *        review (id, film, take, year, url, still) and partner (title,
		 *        year, tt, movie_id, review_id, url, still); null when nothing
		 *        resolved. Return an incomplete value to fall back to the desk.
		 */
		if ( function_exists( 'apply_filters' ) ) {
			$pair = apply_filters( 'lunara_home_stills_that_rhyme_pair', $pair );
		}

		if ( ! lunara_home_stills_that_rhyme_pair_is_complete( $pair ) ) {
			return function_exists( 'lunara_render_home_pairing_desk' ) ? (string) lunara_render_home_pairing_desk() : '';
		}

		$role     = is_array( $pair['role'] ?? null ) ? $pair['role'] : array();
		$label    = trim( (string) ( $role['label'] ?? '' ) );
		$question = trim( (string) ( $role['question'] ?? '' ) );
		$review   = $pair['review'];
		$partner  = $pair['partner'];
		$take     = trim( (string) ( $review['take'] ?? '' ) );

		ob_start();
		?>
		<section id="pairing-desk" class="lunara-home-section lunara-home-slot-pairing-desk lunara-home-stills-that-rhyme<?php echo '' !== (string) ( $role['slug'] ?? '' ) ? ' is-role-' . esc_attr( sanitize_key( (string) $role['slug'] ) ) : ''; ?>" data-lunara-site-studio-section="pairing-desk" aria-label="<?php esc_attr_e( 'Stills that Rhyme', 'lunara-film' ); ?>">
			<div class="lunara-home-stills-inner">
				<header class="lunara-home-stills-head">
					<p class="lunara-home-stills-kicker"><?php esc_html_e( 'Stills that Rhyme', 'lunara-film' ); ?></p>
					<h2 class="lunara-home-stills-title"><?php esc_html_e( 'Two films, one image idea.', 'lunara-film' ); ?></h2>
					<?php if ( '' !== $label || '' !== $question ) : ?>
						<p class="lunara-home-stills-question">
							<?php if ( '' !== $label ) : ?>
								<span class="lunara-home-stills-role"><?php echo esc_html( $label ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $question ) : ?>
								<em class="lunara-home-stills-question-text"><?php echo esc_html( $question ); ?></em>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</header>

				<div class="lunara-home-stills-grid">
					<a class="lunara-home-stills-item is-review" href="<?php echo esc_url( $review['url'] ); ?>">
						<span class="lunara-home-stills-frame"><?php echo lunara_home_stills_that_rhyme_still_html( $review['still'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="lunara-home-stills-caption">
							<span class="lunara-home-stills-film"><?php echo esc_html( lunara_home_stills_that_rhyme_caption( $review['film'], $review['year'] ?? '' ) ); ?></span>
							<?php if ( '' !== $take ) : ?>
								<em class="lunara-home-stills-take"><?php echo esc_html( $take ); ?></em>
							<?php endif; ?>
						</span>
					</a>
					<a class="lunara-home-stills-item is-partner" href="<?php echo esc_url( $partner['url'] ); ?>">
						<span class="lunara-home-stills-frame"><?php echo lunara_home_stills_that_rhyme_still_html( $partner['still'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="lunara-home-stills-caption">
							<span class="lunara-home-stills-film"><?php echo esc_html( lunara_home_stills_that_rhyme_caption( $partner['title'], $partner['year'] ?? '' ) ); ?></span>
						</span>
					</a>
				</div>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_styles' ) ) {
	/**
	 * The section's stylesheet, printed after the Home module styles (44) and
	 * the masthead variables (45) on the front page.
	 */
	function lunara_home_stills_that_rhyme_styles() {
		if ( ! function_exists( 'is_front_page' ) || ! is_front_page() || ! function_exists( 'lunara_print_cacheable_stylesheet' ) ) {
			return;
		}
		lunara_print_cacheable_stylesheet( 'lunara-home-stills-that-rhyme', 'assets/css/lunara-home-stills-that-rhyme.css' );
	}
}
add_action( 'wp_head', 'lunara_home_stills_that_rhyme_styles', 46 );
