<?php
/**
 * Behavior contract for the Home section order (3.2.121).
 *
 * A chosen Homepage Studio preset is a named order: the public order follows
 * the preset as the code defines it today, so a preset change in code reaches
 * the site without the preset being saved again. With no preset chosen, the
 * stored custom order decides. Either way, unknown slugs are dropped and
 * missing slugs are appended, so every registry slot has a position.
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['lunara_test'] = array( 'mods' => array() );

function lunara_test_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function __( $text, $domain = null ) { return $text; }
function get_theme_mod( $key, $default = false ) { return $GLOBALS['lunara_test']['mods'][ $key ] ?? $default; }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) ); }
function sanitize_title( $title ) { return strtolower( trim( preg_replace( '/[^a-z0-9\-]+/i', '-', (string) $title ), '-' ) ); }
function apply_filters( $hook, $value ) { return $value; }

require dirname( __DIR__ ) . '/inc/helpers.php';

// The preset specs as the Control Desk defines them; the test asserts the
// seam, so a small stand-in is enough.
function lunara_control_desk_homepage_order_preset_specs() {
	return array(
		'editorial-default' => array(
			'desktop_order' => array( 'hero', 'pairing-desk', 'featured', 'ledger', 'dispatch' ),
			'mobile_order'  => array( 'hero', 'dispatch', 'pairing-desk', 'featured', 'ledger' ),
		),
	);
}

function lunara_test_order( array $map ): array {
	asort( $map );
	return array_keys( $map );
}

$all = lunara_get_home_section_slugs();
lunara_test_assert( in_array( 'featured', $all, true ) && in_array( 'ledger', $all, true ) && in_array( 'pairing-desk', $all, true ), 'the registry knows the three Direction D slots' );

// No preset, no saved order: the registry order.
$map = lunara_get_home_section_order_map();
lunara_test_assert( $all === lunara_test_order( $map ), 'with nothing saved the registry order stands' );

// No preset, a saved custom order: the saved order leads, missing slugs follow.
$GLOBALS['lunara_test']['mods']['lunara_home_section_order'] = 'ledger,featured,hero';
$order = lunara_test_order( lunara_get_home_section_order_map() );
lunara_test_assert( array( 'ledger', 'featured', 'hero' ) === array_slice( $order, 0, 3 ) && count( $order ) === count( $all ), 'a custom order leads and every other slot is appended' );

// A chosen preset wins over the stale saved string.
$GLOBALS['lunara_test']['mods']['lunara_home_section_order_preset'] = 'editorial-default';
$order = lunara_test_order( lunara_get_home_section_order_map() );
lunara_test_assert( array( 'hero', 'pairing-desk', 'featured', 'ledger', 'dispatch' ) === array_slice( $order, 0, 5 ) && count( $order ) === count( $all ), 'a chosen preset decides the order as the code defines it today' );
lunara_test_assert( 'hero,pairing-desk,featured,ledger,dispatch' === lunara_get_home_section_order_raw( 'desktop' ), 'the preset raw order is the desktop order' );
lunara_test_assert( 'hero,dispatch,pairing-desk,featured,ledger' === lunara_get_home_section_order_raw( 'mobile' ), 'the preset raw order knows the mobile order' );

// Phones follow the preset too; without one, the saved mobile string.
$mobile = lunara_test_order( lunara_get_home_section_mobile_order_map() );
lunara_test_assert( array( 'hero', 'dispatch', 'pairing-desk', 'featured', 'ledger' ) === array_slice( $mobile, 0, 5 ) && count( $mobile ) === count( $all ), 'a chosen preset decides the phone order as well' );
$GLOBALS['lunara_test']['mods']['lunara_home_section_order_preset'] = '';
$GLOBALS['lunara_test']['mods']['lunara_home_section_mobile_order']  = 'featured,hero';
$mobile = lunara_test_order( lunara_get_home_section_mobile_order_map() );
lunara_test_assert( array( 'featured', 'hero' ) === array_slice( $mobile, 0, 2 ) && count( $mobile ) === count( $all ), 'without a preset the saved phone order decides and every slot is placed' );
$GLOBALS['lunara_test']['mods']['lunara_home_section_order_preset'] = 'editorial-default';

// An unknown preset falls back to the saved custom order.
$GLOBALS['lunara_test']['mods']['lunara_home_section_order_preset'] = 'retired-preset';
$order = lunara_test_order( lunara_get_home_section_order_map() );
lunara_test_assert( '' === lunara_get_home_section_order_raw() && array( 'ledger', 'featured', 'hero' ) === array_slice( $order, 0, 3 ), 'an unknown preset leaves the saved order in charge' );

// Junk in the saved order is dropped, never fataled on.
$GLOBALS['lunara_test']['mods']['lunara_home_section_order_preset'] = '';
$GLOBALS['lunara_test']['mods']['lunara_home_section_order'] = 'dispatch, nope, Featured , dispatch';
$order = lunara_test_order( lunara_get_home_section_order_map() );
lunara_test_assert( 'dispatch' === $order[0] && 'featured' === $order[1] && ! in_array( 'nope', $order, true ) && count( $order ) === count( $all ), 'unknown and repeated slugs are dropped from a saved order' );

fwrite( STDOUT, "Home section order: preset decides, custom order otherwise, every slot placed.\n" );
