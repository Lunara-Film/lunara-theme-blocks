<?php
/**
 * Home — Stills that Rhyme (Direction D, 3.2.125).
 *
 * The reviewed film and its three. The lead review's landscape still, then
 * the stills of all three Debrief pairings — Theme Echo, Counter Program,
 * Career Context — each under its full role name with the film, the year and
 * the Method question. The Debrief is three films; that trio is the module's
 * point, so it is never cut to one pair (3.2.121–3.2.124 did; Dalton
 * corrected it). Rendered into the `pairing-desk` homepage slot
 * (front-page.php) in place of the Pair It With desk. Owns
 * assets/css/lunara-home-stills-that-rhyme.css.
 *
 * Pairings are read from the same data the Pair It With cards read, in the
 * same precedence: the Relational Trinity movie entity on the review
 * (`theme_echo_movie`, `counter_program_movie`, `career_context_movie`),
 * then the legacy text field (`_lunara_theme_echo`, …) through the shared
 * parser, via lunara_debrief_method_read_pairing(). A pairing's still comes
 * from the linked movie's `tmdb_backdrop_url` (the Film Dossier hero), else
 * the hero still of a published Lunara review of that film, else the Oscars
 * Ledger's TMDB backdrop for its IMDb id. Stills pass through
 * lunara_rightsize_backdrop_url(), the masthead's 16:9 crop. A pairing
 * without a still renders as a typographic card on the surface colour; it is
 * never dropped and never an empty frame.
 *
 * The set comes from the first candidate review that has a still and all
 * three pairings: the lead story, then the review the Pair It With desk
 * shows, then the newest reviews. When no review qualifies the legacy desk
 * renders. The resolved set passes through the
 * `lunara_home_stills_that_rhyme_set` filter before rendering so a Studio
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

		$poster = trim( (string) get_post_meta( $review_id, '_lunara_tmdb_poster_url', true ) );
		if ( '' === $poster && function_exists( 'get_the_post_thumbnail_url' ) ) {
			$poster = trim( (string) get_the_post_thumbnail_url( $review_id, 'large' ) );
		}

		return array(
			'id'     => $review_id,
			'film'   => trim( (string) ( $parts[0] ?? '' ) ),
			'take'   => 3 === count( $parts ) ? trim( (string) $parts[2] ) : '',
			'year'   => lunara_home_stills_that_rhyme_year( get_post_meta( $review_id, '_lunara_year', true ) ),
			'url'    => (string) get_permalink( $review_id ),
			'still'  => lunara_home_stills_that_rhyme_rightsize( $still ),
			'poster' => lunara_home_stills_that_rhyme_poster_size( $poster ),
		);
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_poster_size' ) ) {
	/**
	 * A poster URL at the card size: TMDB posters at w342, anything else as is.
	 *
	 * @param string $url Poster URL.
	 * @return string
	 */
	function lunara_home_stills_that_rhyme_poster_size( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( function_exists( 'lunara_resize_tmdb_image_url' ) && false !== strpos( $url, 'image.tmdb.org' ) ) {
			$sized = (string) lunara_resize_tmdb_image_url( $url, 'w342' );
			return '' !== $sized ? $sized : $url;
		}
		return $url;
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

		// The poster: the Debrief's canonical face for a film. Movie entity
		// meta, then the poster hydrated on a Lunara review of the film, then
		// the shared poster helper by IMDb id (site, Ledger, TMDB).
		$poster      = $movie_id ? trim( (string) get_post_meta( $movie_id, 'tmdb_poster_url', true ) ) : '';
		$poster_html = '';
		if ( '' === $poster && $partner_review_id ) {
			$poster = trim( (string) get_post_meta( $partner_review_id, '_lunara_tmdb_poster_url', true ) );
		}
		if ( '' === $poster && '' !== $tt && function_exists( 'lunara_get_title_poster_html' ) ) {
			$poster_html = trim( (string) lunara_get_title_poster_html( $tt, 'medium', 'lunara-home-stills-media', trim( (string) $pairing['title'] ), 'lazy', '(max-width: 759px) 46vw, 220px' ) );
		}

		return array(
			'title'       => trim( (string) $pairing['title'] ),
			'year'        => lunara_home_stills_that_rhyme_year( $pairing['year'] ?? '' ),
			'tt'          => $tt,
			'movie_id'    => $movie_id,
			'review_id'   => $partner_review_id,
			'url'         => $url,
			'still'       => lunara_home_stills_that_rhyme_rightsize( $still ),
			'poster'      => lunara_home_stills_that_rhyme_poster_size( $poster ),
			'poster_html' => $poster_html,
		);
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_set' ) ) {
	/**
	 * Resolve the set: the first candidate review with a still and all three
	 * Debrief pairings, with each pairing's still when one exists.
	 *
	 * @return array<string,mixed>|null review (id, film, take, year, url,
	 *         still) and pairings (role slug => role (slug, label, question)
	 *         + partner (title, year, tt, movie_id, review_id, url, still));
	 *         null when no review qualifies.
	 */
	function lunara_home_stills_that_rhyme_set() {
		if ( ! function_exists( 'lunara_debrief_method_roles' ) || ! function_exists( 'lunara_debrief_method_read_pairing' ) || ! function_exists( 'get_post_meta' ) || ! function_exists( 'get_permalink' ) || ! function_exists( 'get_the_title' ) ) {
			return null;
		}

		$roles = lunara_debrief_method_roles();
		if ( ! is_array( $roles ) || empty( $roles ) ) {
			return null;
		}

		foreach ( lunara_home_stills_that_rhyme_candidate_review_ids() as $review_id ) {
			$review = lunara_home_stills_that_rhyme_review( $review_id );
			if ( '' === $review['film'] ) {
				continue;
			}

			$pairings = array();
			foreach ( $roles as $slug => $role ) {
				$partner = lunara_home_stills_that_rhyme_partner( $review_id, $role );
				if ( ! is_array( $partner ) ) {
					$pairings = array();
					break;
				}
				$pairings[ (string) $slug ] = array(
					'role'    => array(
						'slug'     => (string) $slug,
						'label'    => (string) ( $role['label'] ?? '' ),
						'question' => (string) ( $role['question'] ?? '' ),
					),
					'partner' => $partner,
				);
			}

			if ( count( $pairings ) === count( $roles ) ) {
				$set          = array(
					'review'   => $review,
					'pairings' => $pairings,
				);
				$set['media'] = lunara_home_stills_that_rhyme_media( $set );
				return $set;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_media' ) ) {
	/**
	 * Which face the set shows (3.2.125). Posters are the Debrief's default:
	 * three films, three posters, always. Stills are the option, taken only
	 * when the reviewed film and all three pairings have one — a film that
	 * has been widely released long enough to have stills. The
	 * `lunara_home_stills_that_rhyme_media` filter can ask for 'posters' or
	 * 'stills'; a request for stills the set cannot honour falls back to
	 * posters.
	 *
	 * @param array<string,mixed> $set Resolved set.
	 * @return string 'posters' or 'stills'.
	 */
	function lunara_home_stills_that_rhyme_media( $set ) {
		$all_stills = '' !== trim( (string) ( $set['review']['still'] ?? '' ) );
		foreach ( (array) ( $set['pairings'] ?? array() ) as $pairing ) {
			if ( '' === trim( (string) ( $pairing['partner']['still'] ?? '' ) ) ) {
				$all_stills = false;
				break;
			}
		}

		$media = $all_stills ? 'stills' : 'posters';
		if ( function_exists( 'apply_filters' ) ) {
			$asked = sanitize_key( (string) apply_filters( 'lunara_home_stills_that_rhyme_media', $media, $set ) );
			if ( 'posters' === $asked ) {
				$media = 'posters';
			} elseif ( 'stills' === $asked && $all_stills ) {
				$media = 'stills';
			}
		}

		return $media;
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_candidate_review_ids' ) ) {
	/**
	 * The reviews the pair may come from, in order (3.2.124): the lead story
	 * first; then the review the Pair It With desk shows (the Studio's manual
	 * pick, or the newest review with pairings); then the newest published
	 * reviews. A lead without a Debrief pairing no longer costs the section;
	 * the pair walks to the newest review that has one. Filterable.
	 *
	 * @return int[] Distinct published review IDs, lead first.
	 */
	function lunara_home_stills_that_rhyme_candidate_review_ids() {
		$ids = array( lunara_home_stills_that_rhyme_lead_review_id() );

		if ( function_exists( 'lunara_get_pairing_desk_review_id' ) ) {
			$ids[] = (int) lunara_get_pairing_desk_review_id();
		}

		if ( class_exists( 'WP_Query' ) ) {
			$recent = new WP_Query(
				array(
					'post_type'              => 'review',
					'post_status'            => 'publish',
					'posts_per_page'         => 8,
					'orderby'                => 'date',
					'order'                  => 'DESC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			foreach ( (array) $recent->posts as $post ) {
				$ids[] = is_object( $post ) ? (int) $post->ID : (int) $post;
			}
		}

		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ), static function ( $id ) {
			return $id > 0 && lunara_home_stills_that_rhyme_is_published( $id, 'review' );
		} ) ) );

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'lunara_home_stills_that_rhyme_candidates', $ids );
			if ( is_array( $filtered ) ) {
				$ids = array_values( array_unique( array_filter( array_map( 'intval', $filtered ) ) ) );
			}
		}

		return $ids;
	}
}

if ( ! function_exists( 'lunara_home_stills_that_rhyme_set_is_complete' ) ) {
	/**
	 * Whether a set (resolved or filtered in) has what the frames need: a
	 * reviewed film with a still and a link, and one pairing per Method role,
	 * each with a title and a link. Pairing stills are optional.
	 *
	 * @param mixed $set Candidate set.
	 * @return bool
	 */
	function lunara_home_stills_that_rhyme_set_is_complete( $set ) {
		if ( ! is_array( $set ) || empty( $set['review'] ) || ! is_array( $set['review'] ) || empty( $set['pairings'] ) || ! is_array( $set['pairings'] ) ) {
			return false;
		}
		$review = $set['review'];
		if ( '' === trim( (string) ( $review['url'] ?? '' ) ) || '' === trim( (string) ( $review['film'] ?? '' ) ) ) {
			return false;
		}
		if ( 'stills' === (string) ( $set['media'] ?? 'posters' ) && '' === trim( (string) ( $review['still'] ?? '' ) ) ) {
			return false;
		}
		$roles = function_exists( 'lunara_debrief_method_roles' ) ? (array) lunara_debrief_method_roles() : array();
		if ( ! empty( $roles ) && count( $set['pairings'] ) !== count( $roles ) ) {
			return false;
		}
		foreach ( $set['pairings'] as $pairing ) {
			$partner = is_array( $pairing ) && isset( $pairing['partner'] ) && is_array( $pairing['partner'] ) ? $pairing['partner'] : array();
			if ( '' === trim( (string) ( $partner['title'] ?? '' ) ) || '' === trim( (string) ( $partner['url'] ?? '' ) ) ) {
				return false;
			}
		}

		return true;
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

if ( ! function_exists( 'lunara_home_stills_that_rhyme_face_html' ) ) {
	/**
	 * A film's face in the chosen media: a 16:9 still, a 2:3 poster, or a
	 * typographic plate on the surface colour when the film has neither.
	 *
	 * @param array<string,mixed> $film  review or partner array.
	 * @param string              $media 'posters' or 'stills'.
	 * @param string              $title The film title for the plate.
	 * @return string
	 */
	function lunara_home_stills_that_rhyme_face_html( $film, $media, $title ) {
		$title = trim( (string) $title );
		if ( 'stills' === $media ) {
			$still = trim( (string) ( $film['still'] ?? '' ) );
			if ( '' !== $still ) {
				return '<span class="lunara-home-stills-frame is-still">' . lunara_home_stills_that_rhyme_still_html( $still ) . '</span>';
			}
		} else {
			$poster = trim( (string) ( $film['poster'] ?? '' ) );
			if ( '' !== $poster ) {
				return '<span class="lunara-home-stills-frame is-poster"><img class="lunara-home-stills-media" src="' . esc_url( $poster ) . '" alt="" width="342" height="513" loading="lazy" decoding="async"></span>';
			}
			$poster_html = trim( (string) ( $film['poster_html'] ?? '' ) );
			if ( '' !== $poster_html ) {
				return '<span class="lunara-home-stills-frame is-poster">' . $poster_html . '</span>';
			}
		}

		return '<span class="lunara-home-stills-frame is-text' . ( 'stills' === $media ? ' is-still' : ' is-poster' ) . '"><span class="lunara-home-stills-text-title">' . esc_html( $title ) . '</span></span>';
	}
}

if ( ! function_exists( 'lunara_render_home_stills_that_rhyme' ) ) {
	/**
	 * Render Stills that Rhyme — the reviewed film and its three — or the
	 * legacy Pair It With desk when no review qualifies.
	 *
	 * @return string
	 */
	function lunara_render_home_stills_that_rhyme() {
		$set = lunara_home_stills_that_rhyme_set();

		/**
		 * Filter the resolved set before it renders.
		 *
		 * @param array<string,mixed>|null $set review (id, film, take, year,
		 *        url, still) and pairings (role slug => role + partner); null
		 *        when nothing resolved. Return an incomplete value to fall back
		 *        to the desk.
		 */
		if ( function_exists( 'apply_filters' ) ) {
			$set = apply_filters( 'lunara_home_stills_that_rhyme_set', $set );
		}

		if ( ! lunara_home_stills_that_rhyme_set_is_complete( $set ) ) {
			return function_exists( 'lunara_render_home_pairing_desk' ) ? (string) lunara_render_home_pairing_desk() : '';
		}

		$review = $set['review'];
		$media  = 'stills' === (string) ( $set['media'] ?? '' ) ? 'stills' : 'posters';
		$take   = trim( (string) ( $review['take'] ?? '' ) );
		$title  = trim( (string) ( $review['film'] ?? '' ) );
		if ( '' !== $take ) {
			$title .= ' – ' . $take;
		}

		ob_start();
		?>
		<section id="pairing-desk" class="lunara-home-section lunara-home-slot-pairing-desk lunara-home-stills-that-rhyme is-media-<?php echo esc_attr( $media ); ?>" data-lunara-site-studio-section="pairing-desk" data-lunara-stills-media="<?php echo esc_attr( $media ); ?>" aria-label="<?php esc_attr_e( 'Stills that Rhyme', 'lunara-film' ); ?>">
			<div class="lunara-home-stills-inner">
				<header class="lunara-home-stills-head">
					<p class="lunara-home-stills-kicker"><?php esc_html_e( 'Stills that Rhyme', 'lunara-film' ); ?></p>
					<h2 class="lunara-home-stills-title"><?php esc_html_e( 'Every review ends with three more films.', 'lunara-film' ); ?></h2>
					<p class="lunara-home-stills-source"><?php esc_html_e( 'Fresh from the debrief of', 'lunara-film' ); ?> <a href="<?php echo esc_url( $review['url'] ); ?>"><?php echo esc_html( $title ); ?></a></p>
				</header>

				<div class="lunara-home-stills-grid">
					<a class="lunara-home-stills-item is-review" href="<?php echo esc_url( $review['url'] ); ?>">
						<?php echo lunara_home_stills_that_rhyme_face_html( $review, $media, $review['film'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="lunara-home-stills-caption">
							<span class="lunara-home-stills-film"><?php echo esc_html( lunara_home_stills_that_rhyme_caption( $review['film'], $review['year'] ?? '' ) ); ?></span>
							<?php if ( '' !== $take ) : ?>
								<em class="lunara-home-stills-take"><?php echo esc_html( $take ); ?></em>
							<?php endif; ?>
						</span>
					</a>
					<div class="lunara-home-stills-three" role="list">
						<?php foreach ( $set['pairings'] as $pairing ) : ?>
							<?php
							$role     = is_array( $pairing['role'] ?? null ) ? $pairing['role'] : array();
							$partner  = $pairing['partner'];
							$slug     = sanitize_key( (string) ( $role['slug'] ?? '' ) );
							$label    = trim( (string) ( $role['label'] ?? '' ) );
							$question = trim( (string) ( $role['question'] ?? '' ) );
							$face     = lunara_home_stills_that_rhyme_face_html( $partner, $media, $partner['title'] );
							?>
							<a class="lunara-home-stills-item is-pairing<?php echo '' !== $slug ? ' is-role-' . esc_attr( $slug ) : ''; ?><?php echo false !== strpos( $face, 'is-text' ) ? ' has-no-art' : ''; ?>" href="<?php echo esc_url( $partner['url'] ); ?>" role="listitem">
								<?php if ( '' !== $label ) : ?>
									<span class="lunara-home-stills-role"><?php echo esc_html( $label ); ?></span>
								<?php endif; ?>
								<?php echo $face; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<span class="lunara-home-stills-caption">
									<span class="lunara-home-stills-film"><?php echo esc_html( lunara_home_stills_that_rhyme_caption( $partner['title'], $partner['year'] ?? '' ) ); ?></span>
									<?php if ( '' !== $question ) : ?>
										<em class="lunara-home-stills-question-text"><?php echo esc_html( $question ); ?></em>
									<?php endif; ?>
								</span>
							</a>
						<?php endforeach; ?>
					</div>
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
