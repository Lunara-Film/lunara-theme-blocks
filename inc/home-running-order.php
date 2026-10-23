<?php
/**
 * Home — The Running Order (Direction D, 3.2.120).
 *
 * The poster wall of every published review, newest first, rendered into the
 * `featured` homepage slot (front-page.php). Owns its own stylesheet
 * (assets/css/lunara-home-running-order.css) and nothing outside this file.
 *
 * Contract: lunara_render_home_running_order() returns a self-contained
 * <section class="lunara-home-section lunara-home-slot-featured …"> or '' when
 * there is nothing to show. Square corners, no ordinal numerals, 2:3 posters
 * from the review's TMDB poster meta, lazy below the fold.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lunara_render_home_running_order' ) ) {
	/**
	 * Render The Running Order poster wall.
	 *
	 * @return string
	 */
	function lunara_render_home_running_order() {
		return '';
	}
}
