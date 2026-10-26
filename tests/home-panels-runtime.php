<?php
/**
 * Behavior contract for Home's alternating panels (3.2.136).
 *
 * inc/home-panels.php decides which rendered Home sections sit on a panel of
 * the Ledger band's navy and marks them for the stylesheet. The plan is
 * checked as rules over every order of the eight Home slots, then the
 * marking is checked on realistic section markup.
 *
 * Run: php tests/home-panels-runtime.php
 *      php tests/home-panels-runtime.php --mark < sections.json
 *      (the browser contract marks real section fixtures this way:
 *      a JSON list of {slug, html} in, a JSON list of marked HTML out)
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['lunara_test_filters'] = array();
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
	$GLOBALS['lunara_test_filters'][ $hook ][] = $callback;
}
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['lunara_test_filters'][ $hook ] ?? array() as $callback ) {
		$value = call_user_func( $callback, $value, ...$args );
	}
	return $value;
}

require dirname( __DIR__ ) . '/inc/home-panels.php';

if ( in_array( '--mark', $argv ?? array(), true ) ) {
	$sections = json_decode( (string) stream_get_contents( STDIN ), true );
	if ( ! is_array( $sections ) ) {
		fwrite( STDERR, "--mark expects a JSON list of {slug, html} on STDIN\n" );
		exit( 1 );
	}
	echo json_encode( lunara_home_mark_panels( $sections ) );
	exit( 0 );
}

$checks = 0;
function lunara_test_assert( $condition, string $message ): void {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

/*
 * The plan, as rules over every order of the Home slots and every subset
 * that renders: the band is always a panel; two panels never touch; the
 * first section is a panel unless the band follows it; and a ground section
 * follows a ground section only when the band comes next.
 */
function lunara_test_permutations( array $items ): array {
	if ( count( $items ) <= 1 ) {
		return array( $items );
	}
	$out = array();
	foreach ( $items as $index => $item ) {
		$rest = $items;
		unset( $rest[ $index ] );
		foreach ( lunara_test_permutations( array_values( $rest ) ) as $tail ) {
			$out[] = array_merge( array( $item ), $tail );
		}
	}
	return $out;
}

$slots  = array( 'pairing-desk', 'featured', 'ledger', 'dispatch', 'latest-reviews', 'oscar-picks', 'oscar-facts' );
$orders = 0;
foreach ( array( $slots, array_slice( $slots, 0, 5 ), array( 'featured', 'ledger', 'dispatch' ), array( 'pairing-desk', 'dispatch', 'oscar-picks', 'oscar-facts' ) ) as $set ) {
	foreach ( lunara_test_permutations( $set ) as $order ) {
		++$orders;
		$plan  = lunara_home_panel_plan( $order );
		$label = implode( ',', $order );
		lunara_test_assert( array_keys( $plan ) === array_keys( $order ), "one flag per section ({$label})" );
		foreach ( $order as $i => $slug ) {
			$next_is_band = 'ledger' === ( $order[ $i + 1 ] ?? '' );
			if ( 'ledger' === $slug ) {
				lunara_test_assert( true === $plan[ $i ], "the band is always a panel ({$label})" );
			}
			if ( $i > 0 ) {
				lunara_test_assert( ! ( $plan[ $i - 1 ] && $plan[ $i ] ), "two panels never touch ({$label}, at {$i})" );
				if ( ! $plan[ $i - 1 ] && ! $plan[ $i ] ) {
					lunara_test_assert( $next_is_band, "two ground sections meet only before the band ({$label}, at {$i})" );
				}
			} else {
				lunara_test_assert( $plan[0] === ! $next_is_band, "the first section is a panel unless the band follows it ({$label})" );
			}
		}
	}
}
lunara_test_assert( $orders > 5000, 'the rules ran over every order' );

// The live order (3.2.138): the Debrief showcase, the Journal, the band,
// the Running Order, Oscar Picks.
lunara_test_assert( array( true, false, true, false, true ) === lunara_home_panel_plan( array( 'pairing-desk', 'dispatch', 'ledger', 'featured', 'oscar-picks' ) ), 'the live order alternates panel, ground, band, ground, panel' );
lunara_test_assert( array( false, true, false, true, false ) === lunara_home_panel_plan( array( 'pairing-desk', 'ledger', 'featured', 'dispatch', 'oscar-picks' ) ), 'with the band second, the first section stays on the ground' );
lunara_test_assert( array( true ) === lunara_home_panel_plan( array( 'dispatch' ) ) && array() === lunara_home_panel_plan( array() ), 'one section is a panel; no sections, no plan' );

/*
 * Marking. The root a renderer returns gets the classes; a stylesheet link,
 * a comment, a nested section and look-alike class names do not.
 */
$debrief = '<!-- debrief --><link rel="stylesheet" href="x.css"><section id="pairing-desk" class="lunara-home-section lunara-home-slot-pairing-desk lunara-home-stills-that-rhyme" data-lunara-site-studio-section="pairing-desk" aria-label="The Lunara Film Debrief"><section class="lunara-home-section-inner">Three films</section></section>';
$marked  = lunara_home_add_section_classes( $debrief, array( 'is-home-panel' ) );
lunara_test_assert( '<!-- debrief --><link rel="stylesheet" href="x.css"><section id="pairing-desk" class="lunara-home-section lunara-home-slot-pairing-desk lunara-home-stills-that-rhyme is-home-panel" data-lunara-site-studio-section="pairing-desk" aria-label="The Lunara Film Debrief"><section class="lunara-home-section-inner">Three films</section></section>' === $marked, 'the root section takes the class; the comment, the link and the nested section are untouched' );
lunara_test_assert( $marked === lunara_home_add_section_classes( $marked, array( 'is-home-panel' ) ), 'marking twice adds nothing' );
$lookalikes = '<section data-class="lunara-home-section" class="lunara-home-section-head">Head</section>';
lunara_test_assert( $lookalikes === lunara_home_add_section_classes( $lookalikes, array( 'is-home-panel' ) ) && null === lunara_home_section_root( $lookalikes ), 'a data attribute or a longer class name is not a Home section root' );
lunara_test_assert( '<div class="lunara-home-section">x</div>' === lunara_home_add_section_classes( '<div class="lunara-home-section">x</div>', array( 'is-home-panel' ) ), 'only a section element is a root' );

function lunara_test_section( string $slug, string $extra = '' ): array {
	return array(
		'slug' => $slug,
		'html' => "\n\t<section class=\"lunara-home-section lunara-home-slot-{$slug}{$extra}\" data-lunara-site-studio-section=\"{$slug}\">{$slug}</section>\n",
	);
}
function lunara_test_classes( string $html ): array {
	preg_match( '/<section\b[^>]*\sclass="([^"]*)"/', $html, $m );
	return array_values( array_diff( preg_split( '/\s+/', trim( $m[1] ?? '' ) ), array( '' ) ) );
}
function lunara_test_panel_classes( array $out ): array {
	return array_map(
		static function ( $html ) {
			return implode( ' ', array_values( array_filter( lunara_test_classes( $html ), static function ( $c ) { return 0 === strpos( $c, 'is-' ) && false !== strpos( $c, 'panel' ); } ) ) );
		},
		$out
	);
}

$live = array( lunara_test_section( 'pairing-desk' ), lunara_test_section( 'dispatch' ), lunara_test_section( 'ledger', ' lunara-home-ledger-band' ), lunara_test_section( 'featured' ), lunara_test_section( 'oscar-picks' ) );
$out  = lunara_home_mark_panels( $live );
lunara_test_assert( array( 'is-home-panel', 'is-after-home-panel', 'is-home-panel', 'is-after-home-panel', 'is-home-panel is-home-panel-last' ) === lunara_test_panel_classes( $out ), 'the live order is marked panel, after, band, after, last panel' );
lunara_test_assert( 5 === count( $out ), 'five sections in, five out' );
foreach ( $live as $i => $section ) {
	$restored = preg_replace( '/ (?:is-home-panel-last|is-home-panel|is-after-home-panel)(?=[ "])/', '', $out[ $i ] );
	lunara_test_assert( $restored === $section['html'], "the {$section['slug']} section keeps its place and its markup; only the panel classes are added" );
}

// A section that renders nothing takes no part: without the band the
// remaining four simply alternate, and the band's slot leaves no gap.
$no_band    = $live;
$no_band[2] = array( 'slug' => 'ledger', 'html' => "  \n" );
lunara_test_assert( array( 'is-home-panel', 'is-after-home-panel', 'is-home-panel', 'is-after-home-panel' ) === lunara_test_panel_classes( lunara_home_mark_panels( $no_band ) ), 'an empty section is dropped and the rest alternate' );

// The band second: the first section stays on the ground and keeps its
// hairline; the last section is ground, so no panel meets the footer.
$band_second = array( $live[0], $live[2], $live[1], $live[3], $live[4] );
lunara_test_assert( array( '', 'is-home-panel', 'is-after-home-panel', 'is-home-panel', 'is-after-home-panel' ) === lunara_test_panel_classes( lunara_home_mark_panels( $band_second ) ), 'with the band second: ground, band, after, panel, after' );

// The hero slot is an opener: unmarked, and it does not count.
$with_hero = array_merge( array( array( 'slug' => 'hero', 'html' => '<section class="lunara-home-hero lunara-home-slot-hero">hero</section>' ) ), array( $live[0], $live[1] ) );
$hero_out  = lunara_home_mark_panels( $with_hero );
lunara_test_assert( array( 'lunara-home-hero', 'lunara-home-slot-hero' ) === lunara_test_classes( $hero_out[0] ) && array( '', 'is-home-panel', 'is-after-home-panel' ) === lunara_test_panel_classes( $hero_out ), 'the hero stays unmarked and the first section after it is a panel' );

// Output without a Home section root passes through and breaks no rule.
$odd = array( $live[0], array( 'slug' => 'dispatch', 'html' => '<div class="legacy">Journal</div>' ), $live[1] );
$odd_out = lunara_home_mark_panels( $odd );
lunara_test_assert( '<div class="legacy">Journal</div>' === $odd_out[1] && array( 'is-home-panel', '', '' ) === lunara_test_panel_classes( $odd_out ), 'unmarkable output passes through; the section after it is not marked as following a panel' );

// The filter turns the panels off: every section renders as before 3.2.136.
add_filter( 'lunara_home_panels_enabled', static function () { return false; } );
lunara_test_assert( array_column( $live, 'html' ) === lunara_home_mark_panels( $live ), 'with lunara_home_panels_enabled off, the sections come back unchanged' );
$GLOBALS['lunara_test_filters'] = array();

fwrite( STDOUT, "Home panels runtime passed: {$checks} checks over {$orders} section orders.\n" );
