<?php
/**
 * The program order Home ships with (3.2.138), read from the real Editorial
 * default preset in inc/control-desk.php, the preset the live Home uses.
 *
 * Dalton, 2026-10-26: the Journal moves up to second, under the Debrief
 * showcase, and The Running Order follows the Oscar Ledger band. Phones read
 * the same order. In that order the sections the live Home renders alternate
 * cleanly with the Ledger navy (inc/home-panels.php): panel, ground, band,
 * ground, panel.
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

function __( $text, $domain = '' ) { return $text; }
function apply_filters( $hook, $value, ...$args ) { return $value; }

/** Pull one function's source out of a file by counting its braces. */
function lunara_test_extract( string $source, string $name ): string {
	if ( ! preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\(/', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		throw new RuntimeException( "{$name} not found" );
	}
	$start = $m[0][1];
	$depth = 0;
	$len   = strlen( $source );
	for ( $i = strpos( $source, '{', $start ); $i < $len; $i++ ) {
		if ( '{' === $source[ $i ] ) {
			++$depth;
		} elseif ( '}' === $source[ $i ] && 0 === --$depth ) {
			return substr( $source, $start, $i - $start + 1 );
		}
	}
	throw new RuntimeException( "{$name} is not closed" );
}

eval( lunara_test_extract( (string) file_get_contents( dirname( __DIR__ ) . '/inc/control-desk.php' ), 'lunara_control_desk_homepage_order_preset_specs' ) );
require dirname( __DIR__ ) . '/inc/home-panels.php';

$checks = 0;
function lunara_test_assert( $condition, string $message ): void {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$preset = lunara_control_desk_homepage_order_preset_specs()['editorial-default'] ?? array();
lunara_test_assert( array( 'hero', 'pairing-desk', 'dispatch', 'ledger', 'featured' ) === array_slice( $preset['desktop_order'] ?? array(), 0, 5 ), 'the program runs: the Debrief showcase, the Journal, the Ledger band, then The Running Order' );
lunara_test_assert( ( $preset['desktop_order'] ?? null ) === ( $preset['mobile_order'] ?? array() ), 'phones read the same order as desktop' );
lunara_test_assert( count( array_unique( $preset['desktop_order'] ) ) === count( $preset['desktop_order'] ), 'each section appears once' );

// The sections the live Home renders, in the preset's order, alternate cleanly.
$live = array_values( array_intersect( $preset['desktop_order'], array( 'pairing-desk', 'dispatch', 'ledger', 'featured', 'oscar-picks' ) ) );
lunara_test_assert( array( 'pairing-desk', 'dispatch', 'ledger', 'featured', 'oscar-picks' ) === $live && array( true, false, true, false, true ) === lunara_home_panel_plan( $live ), 'in the program order the live sections alternate: panel, ground, band, ground, panel' );

fwrite( STDOUT, "Home program order: {$checks} checks passed.\n" );
