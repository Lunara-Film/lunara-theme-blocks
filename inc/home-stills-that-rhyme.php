<?php
/**
 * Home — Stills that Rhyme (Direction D, 3.2.120).
 *
 * Two films, one image idea: the lead review's still beside the still of its
 * Theme Echo pairing from the Debrief. Rendered into the `pairing-desk`
 * homepage slot (front-page.php), replacing the Pair It With desk there. Owns
 * its own stylesheet (assets/css/lunara-home-stills-that-rhyme.css).
 *
 * Contract: lunara_render_home_stills_that_rhyme() returns a self-contained
 * <section class="lunara-home-section lunara-home-slot-pairing-desk …"> and
 * keeps data-lunara-site-studio-section="pairing-desk" so Site Studio still
 * finds the section. Until the pair renderer exists it hands back the legacy
 * Pair It With desk so Home never loses the module.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lunara_render_home_stills_that_rhyme' ) ) {
	/**
	 * Render Stills that Rhyme.
	 *
	 * @return string
	 */
	function lunara_render_home_stills_that_rhyme() {
		return function_exists( 'lunara_render_home_pairing_desk' ) ? (string) lunara_render_home_pairing_desk() : '';
	}
}
