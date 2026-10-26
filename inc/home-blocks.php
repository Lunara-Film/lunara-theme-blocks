<?php
/**
 * Hybrid Homepage Composition (3.1.50).
 *
 * The Home page's blocks ARE the homepage: block order is section order,
 * block presence is section visibility, while compact editor cards link to
 * each public section without rendering the full site in the canvas.
 * Homepage Studio stays alive as a macro layer — applying a
 * publication package or changing order/visibility there writes through to
 * the same blocks, so both surfaces always tell the truth.
 *
 * front-page.php asks lunara_home_uses_block_composition(): when the front
 * page contains any Lunara section block, the blocks render; when it
 * contains none, the legacy Customizer-registry rendering runs untouched —
 * which is also the instant rollback (delete the blocks, old system
 * resumes).
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lunara_home_section_block_map' ) ) {
	/**
	 * Registry slug → homepage section block. Only slugs with a genuine
	 * renderer are mapped (mirrors front-page.php's renderer table).
	 */
	function lunara_home_section_block_map() {
		return array(
			'hero'           => 'lunara/cinematic-hero',
			'pairing-desk'   => 'lunara/pairing-desk',
			'featured'       => 'lunara/running-order',
			'ledger'         => 'lunara/ledger-band',
			'latest-reviews' => 'lunara/latest-reviews',
			'dispatch'       => 'lunara/journal-lane',
			'oscar-picks'    => 'lunara/oscar-picks',
			'oscar-facts'    => 'lunara/oscar-facts',
		);
	}
}

if ( ! function_exists( 'lunara_register_direction_d_homepage_blocks' ) ) {
	/**
	 * The two Direction D sections that had no block (3.2.122): The Running
	 * Order (featured slot) and the Oscar Ledger band (ledger slot). Server
	 * render only, like the other section blocks; the editor card lives in
	 * assets/js/lunara-blocks.js. Stills that Rhyme reuses lunara/pairing-desk.
	 */
	function lunara_register_direction_d_homepage_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$common = array(
			'api_version'   => 3,
			'category'      => 'lunara',
			'editor_script' => 'lunara-blocks',
			'supports'      => array( 'html' => false, 'reusable' => false, 'multiple' => false, 'inserter' => true ),
		);

		$blocks = array(
			'lunara/running-order' => array(
				'title'           => __( 'Homepage: The Running Order', 'lunara-film' ),
				'icon'            => 'grid-view',
				'description'     => __( 'The poster wall: every review, newest first, after the three Now Showing carries.', 'lunara-film' ),
				'render_callback' => static function () {
					return function_exists( 'lunara_render_home_running_order' ) ? (string) lunara_render_home_running_order() : '';
				},
			),
			'lunara/ledger-band'   => array(
				'title'           => __( 'Homepage: The Oscar Ledger', 'lunara-film' ),
				'icon'            => 'awards',
				'description'     => __( 'The intermission band: the live ceremony count, Ledger totals, the door to the portal and five Best Picture posters.', 'lunara-film' ),
				'render_callback' => static function () {
					return function_exists( 'lunara_render_home_ledger_band' ) ? (string) lunara_render_home_ledger_band() : '';
				},
			),
		);

		$registry = class_exists( 'WP_Block_Type_Registry' ) ? WP_Block_Type_Registry::get_instance() : null;
		foreach ( $blocks as $name => $args ) {
			if ( $registry && $registry->is_registered( $name ) ) {
				continue;
			}
			register_block_type( $name, array_merge( $common, $args ) );
		}
	}
	add_action( 'init', 'lunara_register_direction_d_homepage_blocks', 101 );
}

if ( ! function_exists( 'lunara_home_composition_revision' ) ) {
	/**
	 * The composition the Home page blocks must carry. Bump when the block map
	 * gains a section that an existing block-mode Home page needs written in.
	 *   2 — 3.2.122: lunara/running-order and lunara/ledger-band (Direction D).
	 */
	function lunara_home_composition_revision() {
		return 2;
	}
}

if ( ! function_exists( 'lunara_home_migrate_block_composition' ) ) {
	/**
	 * One-time write-through after a deploy that added section blocks: in
	 * block mode, recompose the Home page from the saved order and visibility
	 * (the same write Homepage Studio performs on save), so the new sections
	 * appear without an editor visit. Runs on admin_init for a theme editor,
	 * once per composition revision; a registry-mode Home is left alone.
	 *
	 * @return bool|WP_Error True when the page changed, false when nothing
	 *                       needed doing, or the write error.
	 */
	function lunara_home_migrate_block_composition() {
		$target = lunara_home_composition_revision();
		$done   = (int) get_option( 'lunara_home_composition_revision', 0 );
		if ( $done >= $target ) {
			return false;
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return false;
		}

		if ( ! lunara_home_uses_block_composition() ) {
			update_option( 'lunara_home_composition_revision', $target, false );
			return false;
		}

		$result = lunara_sync_home_section_blocks_from_settings();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_option( 'lunara_home_composition_revision', $target, false );
		return $result;
	}
	add_action( 'admin_init', 'lunara_home_migrate_block_composition', 20 );
}

if ( ! function_exists( 'lunara_home_front_page_id' ) ) {
	function lunara_home_front_page_id() {
		return 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
	}
}

if ( ! function_exists( 'lunara_home_front_page_content' ) ) {
	/** Read public front-page content through the request-local preview seam. */
	function lunara_home_front_page_content( $page_id ) {
		$page_id = (int) $page_id;
		$content = (string) get_post_field( 'post_content', $page_id );
		return (string) apply_filters( 'lunara_home_front_page_content', $content, $page_id );
	}
}

if ( ! function_exists( 'lunara_home_uses_block_composition' ) ) {
	/**
	 * True when the front page's content carries at least one Lunara section
	 * block — the signal that blocks own homepage composition.
	 */
	function lunara_home_uses_block_composition() {
		$page_id = lunara_home_front_page_id();
		if ( $page_id <= 0 ) {
			return false;
		}

		$content = lunara_home_front_page_content( $page_id );
		if ( '' === trim( $content ) ) {
			return false;
		}

		foreach ( lunara_home_section_block_map() as $block_name ) {
			if ( has_block( $block_name, $content ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'lunara_render_home_block_composition' ) ) {
	/**
	 * Render the front page's blocks. do_blocks() only — no third-party
	 * the_content appendages (sharing bars, related posts) can leak into
	 * the front door, and the output stays exactly the section renderers'.
	 */
	function lunara_render_home_block_composition( $excluded_block_names = array() ) {
		$page_id = lunara_home_front_page_id();
		if ( $page_id <= 0 ) {
			return '';
		}

		$content = lunara_home_front_page_content( $page_id );
		if ( '' === trim( $content ) ) {
			return '';
		}

		$excluded_block_names = array_filter( array_map( 'sanitize_text_field', (array) $excluded_block_names ) );
		if ( empty( $excluded_block_names ) ) {
			return do_blocks( $content );
		}

		$renderable_blocks = array();
		foreach ( parse_blocks( $content ) as $block ) {
			$block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			if ( '' !== $block_name && in_array( $block_name, $excluded_block_names, true ) ) {
				continue;
			}

			$renderable_blocks[] = serialize_block( $block );
		}

		return do_blocks( implode( "\n\n", $renderable_blocks ) );
	}
}

if ( ! function_exists( 'lunara_home_block_composition_sections' ) ) {
	/**
	 * The front page's blocks rendered one at a time, in page order, so
	 * front-page.php can mark Home's alternating panels on them (3.2.137).
	 * Each top-level block renders exactly as do_blocks() renders it; a
	 * Lunara section block carries its registry slug, and any other block
	 * (core blocks, the whitespace between blocks) carries ''.
	 *
	 * @param string[] $excluded_block_names Block names to leave out.
	 * @return array[] array( 'slug' => string, 'html' => string ) per block.
	 */
	function lunara_home_block_composition_sections( $excluded_block_names = array() ) {
		$page_id = lunara_home_front_page_id();
		if ( $page_id <= 0 ) {
			return array();
		}

		$content = lunara_home_front_page_content( $page_id );
		if ( '' === trim( $content ) ) {
			return array();
		}

		$excluded_block_names = array_filter( array_map( 'sanitize_text_field', (array) $excluded_block_names ) );
		$slug_by_block        = array_flip( lunara_home_section_block_map() );
		$sections             = array();
		foreach ( parse_blocks( $content ) as $block ) {
			$block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			if ( '' !== $block_name && in_array( $block_name, $excluded_block_names, true ) ) {
				continue;
			}

			$sections[] = array(
				'slug' => isset( $slug_by_block[ $block_name ] ) ? (string) $slug_by_block[ $block_name ] : '',
				'html' => (string) render_block( $block ),
			);
		}

		return $sections;
	}
}

if ( ! function_exists( 'lunara_compose_home_section_blocks' ) ) {
	/**
	 * Purely compose Home page content from the given ordered slug list.
	 * Existing per-block attributes are preserved: if the current content
	 * already holds an instance of a section's block, that exact block
	 * markup is reused (so Latest Reviews overrides, hero fallback fields,
	 * etc. survive reordering).
	 *
	 * @param string   $content Existing post content.
	 * @param string[] $slugs Ordered registry slugs to compose.
	 * @return string|WP_Error Composed candidate content.
	 */
	function lunara_compose_home_section_blocks( $content, $slugs ) {
		if ( ! is_string( $content ) || ! is_array( $slugs ) ) {
			return new WP_Error( 'lunara_home_composition_invalid', __( 'The Homepage section order could not be composed.', 'lunara-film' ) );
		}

		$map     = lunara_home_section_block_map();
		$current = $content;

		// Harvest existing instances so their attributes survive.
		$existing = array();
		$blocks   = parse_blocks( $current );
		foreach ( $blocks as $block ) {
			$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			if ( '' !== $name && in_array( $name, $map, true ) && ! isset( $existing[ $name ] ) ) {
				$existing[ $name ] = serialize_block( $block );
			}
		}

		$desired = array();
		foreach ( (array) $slugs as $slug ) {
			if ( ! isset( $map[ $slug ] ) ) {
				continue;
			}
			$block_name = $map[ $slug ];
			$desired[]  = isset( $existing[ $block_name ] )
				? $existing[ $block_name ]
				: '<!-- wp:' . $block_name . ' /-->';
		}

		// Replace only canonical lane slots. Every unknown/core block is copied
		// back into its exact stream position, so a Structure save can reorder
		// the six owned lanes without deleting editorial content or plugin blocks.
		$pieces        = array();
		$desired_index = 0;
		foreach ( $blocks as $block ) {
			$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			if ( '' !== $name && in_array( $name, $map, true ) ) {
				if ( isset( $desired[ $desired_index ] ) ) {
					$pieces[] = $desired[ $desired_index ];
					++$desired_index;
				}
				continue;
			}

			$pieces[] = serialize_block( $block );
		}

		while ( isset( $desired[ $desired_index ] ) ) {
			$pieces[] = $desired[ $desired_index ];
			++$desired_index;
		}

		return implode( "\n\n", $pieces );
	}
}

if ( ! function_exists( 'lunara_write_home_section_blocks' ) ) {
	/**
	 * Rewrite the current Home page through the pure composer.
	 *
	 * @param string[] $slugs Ordered registry slugs to compose.
	 * @return bool|WP_Error Whether content changed, or the exact write error.
	 */
	function lunara_write_home_section_blocks( $slugs ) {
		$page_id = lunara_home_front_page_id();
		if ( $page_id <= 0 ) {
			return false;
		}

		$current     = (string) get_post_field( 'post_content', $page_id );
		$new_content = lunara_compose_home_section_blocks( $current, is_array( $slugs ) ? $slugs : array() );
		if ( is_wp_error( $new_content ) ) {
			return $new_content;
		}
		if ( $new_content === $current ) {
			return false;
		}

		$updated = wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => wp_slash( $new_content ),
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		if ( $new_content !== (string) get_post_field( 'post_content', $page_id ) ) {
			return new WP_Error( 'lunara_home_composition_readback_failed', __( 'The Homepage content could not be verified.', 'lunara-film' ) );
		}

		return true;
	}
}

if ( ! function_exists( 'lunara_sync_home_section_blocks_from_settings' ) ) {
	/**
	 * Homepage Studio write-through: recompose the Home page's blocks from
	 * the just-saved order + visibility settings. Runs only when block
	 * composition is already active — Studio never force-converts a
	 * registry-mode homepage.
	 */
	function lunara_sync_home_section_blocks_from_settings() {
		if ( ! lunara_home_uses_block_composition() ) {
			return false;
		}

		$map   = lunara_home_section_block_map();
		$slugs = array_keys( $map );

		$order_map = function_exists( 'lunara_get_home_section_order_map' ) ? lunara_get_home_section_order_map() : array();
		usort(
			$slugs,
			static function ( $a, $b ) use ( $order_map ) {
				$order_a = isset( $order_map[ $a ] ) ? (int) $order_map[ $a ] : 99;
				$order_b = isset( $order_map[ $b ] ) ? (int) $order_map[ $b ] : 99;
				return $order_a <=> $order_b;
			}
		);

		$enabled = array();
		foreach ( $slugs as $slug ) {
			$on = function_exists( 'lunara_home_section_is_enabled' ) ? lunara_home_section_is_enabled( $slug ) : true;
			if ( $on ) {
				$enabled[] = $slug;
			}
		}

		return lunara_write_home_section_blocks( $enabled );
	}
}
