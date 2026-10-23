<?php
/**
 * Home — Intermission: The Oscar Ledger (Direction D, 3.2.120).
 *
 * The full-bleed band between the review sections: "Walk ninety-eight
 * ceremonies through their posters", a strip of ceremony posters from the
 * Oscars data layer (inc/oscars-data.php), and the door to /oscars/. Rendered
 * into the `ledger` homepage slot (front-page.php). Owns its own stylesheet
 * (assets/css/lunara-home-ledger-band.css).
 *
 * Contract: lunara_render_home_ledger_band() returns a self-contained
 * <section class="lunara-home-section lunara-home-slot-ledger …"> or '' when
 * the Oscars data is empty. Square corners, gold rules top and bottom, no
 * ordinal numerals.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lunara_render_home_ledger_band' ) ) {
	/**
	 * Render the Oscar Ledger band.
	 *
	 * @return string
	 */
	function lunara_render_home_ledger_band() {
		return '';
	}
}
