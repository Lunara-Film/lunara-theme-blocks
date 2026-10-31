<?php
/**
 * Home sections do not render for an asset scan (Theme 3.2.142).
 *
 * On a classic theme, GutenKit renders the whole post content twice before
 * the page starts, only to find its own blocks' CSS and modules: once on
 * enqueue_block_assets (Gutenkit\Config\Modules::block_assets) and once on
 * wp_enqueue_scripts (Gutenkit\Libs\AssetGenerator::enqueue_scripts). Both
 * passes throw the HTML away. On Home every section rendered three times;
 * the 3.2.141 timer measured the two scan passes at 637 ms and 258 ms of a
 * cold Home render (2026-10-31), including the hero block that Home leaves
 * out of its own render.
 *
 * The Home section blocks (lunara_home_section_block_map()) are
 * server-rendered, hold no inner blocks, and enqueue nothing while they
 * render: each section's stylesheet prints in wp_head by template. So a scan
 * learns nothing from rendering one. While an asset hook runs on a public
 * page, a Home section block gives the scan an empty string instead of
 * rendering. The page's own render happens in the template body, after those
 * hooks, and does not change. Every other block still renders for the scan:
 * GutenKit finds its own blocks, and a block that enqueues a stylesheet while
 * it renders (Pairing Showcase does) still gets it into the head.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lunara_block_render_is_asset_scan' ) ) {
	/**
	 * True while a public page runs an asset hook, where rendered HTML can
	 * never reach the page.
	 *
	 * @return bool
	 */
	function lunara_block_render_is_asset_scan() {
		if ( ( function_exists( 'is_admin' ) && is_admin() ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		return doing_action( 'enqueue_block_assets' ) || doing_action( 'wp_enqueue_scripts' );
	}
}

if ( ! function_exists( 'lunara_skip_block_render_for_asset_scan' ) ) {
	/**
	 * pre_render_block: during an asset scan, a Home section block without
	 * inner blocks returns '' instead of rendering.
	 *
	 * @param string|null $pre_render   A replacement from an earlier filter, or null.
	 * @param array       $parsed_block The block about to render.
	 * @return string|null
	 */
	function lunara_skip_block_render_for_asset_scan( $pre_render, $parsed_block = array() ) {
		if ( null !== $pre_render || ! is_array( $parsed_block ) || ! empty( $parsed_block['innerBlocks'] ) || ! function_exists( 'lunara_home_section_block_map' ) ) {
			return $pre_render;
		}
		$name = isset( $parsed_block['blockName'] ) ? (string) $parsed_block['blockName'] : '';
		if ( '' === $name || ! in_array( $name, lunara_home_section_block_map(), true ) ) {
			return $pre_render;
		}
		return lunara_block_render_is_asset_scan() ? '' : $pre_render;
	}
}
// 3.2.143: last, so no later filter can discard the answer. Core's own
// _wp_add_block_level_preset_styles() runs on pre_render_block at 10 and
// returns null whatever it is given, so at 5 (3.2.142) the empty string never
// reached render_block() and the scans kept rendering every Home section.
add_filter( 'pre_render_block', 'lunara_skip_block_render_for_asset_scan', PHP_INT_MAX - 1, 2 );
