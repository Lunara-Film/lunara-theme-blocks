<?php
/**
 * Home — alternating panels (3.2.136).
 *
 * Dalton marked the Oscar Ledger band and asked for its colour (2026-10-26),
 * then chose alternating panels: Home's sections alternate between the page
 * ground (#0a1520) and full-bleed panels in the band's surface navy
 * (#0f1d2e). front-page.php renders each section, then this file decides
 * which sections sit on a panel and adds the classes that
 * assets/css/lunara-home-modules.css reads:
 *
 * - `is-home-panel`: the section sits on a panel;
 * - `is-home-panel-last`: that panel is the last section, so it meets the
 *   footer's rule;
 * - `is-after-home-panel`: a ground section that follows a panel and opens
 *   on the panel's rule instead of a hairline of its own.
 *
 * The plan counts only the sections that render, in the saved Section Order.
 * The first section after the opener is a panel, and panels then alternate
 * with the ground. The Oscar Ledger band paints its own surface, so it is
 * always a panel; two panels never touch, so the section before the band
 * stays on the ground. The hero slot is an opener, never a panel.
 *
 * `lunara_home_panels_enabled` (default true) turns the panels off; every
 * section then renders as it did before 3.2.136.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lunara_home_panels_enabled' ) ) {
	/**
	 * Whether Home alternates its sections with panels.
	 *
	 * @return bool
	 */
	function lunara_home_panels_enabled() {
		return (bool) apply_filters( 'lunara_home_panels_enabled', true );
	}
}

if ( ! function_exists( 'lunara_home_panel_plan' ) ) {
	/**
	 * Which sections sit on a panel.
	 *
	 * @param string[] $slugs Slugs of the sections that render, in page order.
	 * @return bool[] One flag per slug, in the same order: true for a panel.
	 */
	function lunara_home_panel_plan( array $slugs ) {
		$slugs = array_values( array_map( 'strval', $slugs ) );
		$count = count( $slugs );
		$plan  = array();

		for ( $i = 0; $i < $count; $i++ ) {
			if ( 'ledger' === $slugs[ $i ] ) {
				$plan[ $i ] = true;
				continue;
			}

			$after_panel = $i > 0 && $plan[ $i - 1 ];
			$before_band = $i + 1 < $count && 'ledger' === $slugs[ $i + 1 ];
			$plan[ $i ]  = ! $after_panel && ! $before_band;
		}

		return $plan;
	}
}

if ( ! function_exists( 'lunara_home_section_root' ) ) {
	/**
	 * Find the first `<section>` tag whose class list holds
	 * `lunara-home-section`: the root a Home section renderer returns.
	 *
	 * @param string $html One section renderer's output.
	 * @return array|null array( tag markup, tag offset, class value, class
	 *                    value offset inside the tag ), or null.
	 */
	function lunara_home_section_root( $html ) {
		$html = (string) $html;
		if ( ! preg_match_all( '/<section\b[^>]*>/i', $html, $tags, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		foreach ( $tags[0] as $tag ) {
			if ( ! preg_match( '/\sclass="([^"]*)"/i', $tag[0], $attribute, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			$names = preg_split( '/\s+/', trim( $attribute[1][0] ), -1, PREG_SPLIT_NO_EMPTY );
			if ( in_array( 'lunara-home-section', $names, true ) ) {
				return array( $tag[0], $tag[1], $attribute[1][0], $attribute[1][1] );
			}
		}

		return null;
	}
}

if ( ! function_exists( 'lunara_home_add_section_classes' ) ) {
	/**
	 * Add classes to a Home section's root tag. Output without a Home
	 * section root comes back unchanged.
	 *
	 * @param string   $html    One section renderer's output.
	 * @param string[] $classes Classes to add.
	 * @return string
	 */
	function lunara_home_add_section_classes( $html, array $classes ) {
		$html = (string) $html;
		$root = lunara_home_section_root( $html );
		if ( null === $root || empty( $classes ) ) {
			return $html;
		}

		list( $tag, $tag_offset, $value, $value_offset ) = $root;
		$names = preg_split( '/\s+/', trim( $value ), -1, PREG_SPLIT_NO_EMPTY );
		foreach ( $classes as $class ) {
			$class = (string) $class;
			if ( '' !== $class && ! in_array( $class, $names, true ) ) {
				$names[] = $class;
			}
		}

		$updated = substr_replace( $tag, implode( ' ', $names ), $value_offset, strlen( $value ) );
		return substr_replace( $html, $updated, $tag_offset, strlen( $tag ) );
	}
}

if ( ! function_exists( 'lunara_home_mark_panels' ) ) {
	/**
	 * Mark the rendered Home sections with the panel classes.
	 *
	 * @param array[] $sections array( 'slug' => string, 'html' => string ) per
	 *                          section, in page order. Empty output is dropped.
	 * @return string[] The sections' HTML in the same order, marked.
	 */
	function lunara_home_mark_panels( array $sections ) {
		$items = array();
		foreach ( $sections as $section ) {
			$html = isset( $section['html'] ) ? (string) $section['html'] : '';
			if ( '' === trim( $html ) ) {
				continue;
			}

			$slug    = isset( $section['slug'] ) ? (string) $section['slug'] : '';
			$items[] = array(
				'slug'    => $slug,
				'html'    => $html,
				// The hero slot and output without a Home section root take
				// no part in the alternation.
				'planned' => 'hero' !== $slug && null !== lunara_home_section_root( $html ),
			);
		}

		if ( ! lunara_home_panels_enabled() ) {
			return array_column( $items, 'html' );
		}

		$planned = array_keys( array_filter( array_column( $items, 'planned' ) ) );
		$slugs   = array();
		foreach ( $planned as $index ) {
			$slugs[] = $items[ $index ]['slug'];
		}

		$panel = array_fill( 0, count( $items ), false );
		foreach ( lunara_home_panel_plan( $slugs ) as $position => $is_panel ) {
			$panel[ $planned[ $position ] ] = $is_panel;
		}

		$last = count( $items ) - 1;
		$out  = array();
		foreach ( $items as $index => $item ) {
			$classes = array();
			if ( $panel[ $index ] ) {
				$classes[] = 'is-home-panel';
				if ( $index === $last ) {
					$classes[] = 'is-home-panel-last';
				}
			} elseif ( $item['planned'] && $index > 0 && $panel[ $index - 1 ] ) {
				$classes[] = 'is-after-home-panel';
			}

			$out[] = $classes ? lunara_home_add_section_classes( $item['html'], $classes ) : $item['html'];
		}

		return $out;
	}
}
