<?php
/**
 * Behavior contract for the asset-scan guard (3.2.142, inc/block-scan-guard.php).
 *
 * The live head runs the way WordPress core runs it: wp_head runs
 * wp_enqueue_scripts, whose core callback runs enqueue_block_assets. A
 * stand-in for GutenKit renders the whole page content inside both hooks, as
 * Gutenkit\Config\Modules::block_assets and
 * Gutenkit\Libs\AssetGenerator::enqueue_scripts do on a classic theme. Then
 * the template body renders the page. The checks:
 *
 * - inside an asset hook, a Home section block does not run its renderer;
 * - every other block still renders for the scan: a GutenKit block, a Lunara
 *   block that is not a Home section (Pairing Showcase enqueues its
 *   stylesheet while it renders), and a Home block with inner blocks;
 * - the body render runs each Home section's renderer once and prints it;
 * - admin screens, Ajax and an earlier filter's answer are left alone.
 *
 * Run: php tests/block-scan-guard-runtime.php
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$checks = 0;
function lunara_test_assert( $condition, string $message ): void {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$GLOBALS['lunara_test_filters'] = array();
$GLOBALS['wp_current_filter']   = array();
$GLOBALS['lunara_test_admin']   = false;
$GLOBALS['lunara_test_ajax']    = false;

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['lunara_test_filters'][ $hook ][ $priority ][] = array( $callback, $accepted_args );
	ksort( $GLOBALS['lunara_test_filters'][ $hook ], SORT_NUMERIC );
	return true;
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $hook, $callback, $priority, $accepted_args );
}
function apply_filters( $hook, $value, ...$args ) {
	$GLOBALS['wp_current_filter'][] = $hook;
	foreach ( $GLOBALS['lunara_test_filters'][ $hook ] ?? array() as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) );
		}
	}
	array_pop( $GLOBALS['wp_current_filter'] );
	return $value;
}
function do_action( $hook, ...$args ) {
	apply_filters( $hook, '', ...$args );
}
function doing_action( $hook ) { return in_array( $hook, $GLOBALS['wp_current_filter'], true ); }
function is_admin() { return $GLOBALS['lunara_test_admin']; }
function wp_doing_ajax() { return $GLOBALS['lunara_test_ajax']; }

require dirname( __DIR__ ) . '/inc/home-blocks.php';
require dirname( __DIR__ ) . '/inc/block-scan-guard.php';

/*
 * Blocks render as core renders them: pre_render_block may answer instead of
 * the block; otherwise the renderer runs. Each renderer counts its runs.
 */
$GLOBALS['lunara_test_runs'] = array();
function lunara_test_renderer( string $name ): callable {
	return static function () use ( $name ) {
		$GLOBALS['lunara_test_runs'][ $name ] = ( $GLOBALS['lunara_test_runs'][ $name ] ?? 0 ) + 1;
		return '<section data-block="' . $name . '">' . $name . '</section>';
	};
}
$GLOBALS['lunara_test_renderers'] = array();
foreach ( array_merge( array_values( lunara_home_section_block_map() ), array( 'lunara/pairing-showcase', 'gutenkit/advanced-heading', 'core/paragraph' ) ) as $name ) {
	$GLOBALS['lunara_test_renderers'][ $name ] = lunara_test_renderer( $name );
}
function render_block( array $parsed_block ): string {
	$pre = apply_filters( 'pre_render_block', null, $parsed_block, null );
	if ( null !== $pre ) {
		return (string) $pre;
	}
	$name = (string) ( $parsed_block['blockName'] ?? '' );
	return isset( $GLOBALS['lunara_test_renderers'][ $name ] ) ? (string) call_user_func( $GLOBALS['lunara_test_renderers'][ $name ] ) : '';
}
function do_blocks( array $blocks ): string {
	return implode( '', array_map( 'render_block', $blocks ) );
}
function lunara_test_block( string $name, array $inner = array() ): array {
	return array( 'blockName' => $name, 'attrs' => array(), 'innerBlocks' => $inner, 'innerHTML' => '', 'innerContent' => array() );
}

// The Home page as stored: every Home section, a GutenKit block, a Lunara
// block that is not a Home section, and a core block.
$home_sections = array_map( 'lunara_test_block', array_values( lunara_home_section_block_map() ) );
$page          = array_merge( $home_sections, array( lunara_test_block( 'gutenkit/advanced-heading' ), lunara_test_block( 'lunara/pairing-showcase' ), lunara_test_block( 'core/paragraph' ) ) );

// The head, as core runs it, with GutenKit's two scans.
$GLOBALS['lunara_test_scan_html'] = array();
add_action( 'enqueue_block_assets', static function () { $GLOBALS['lunara_test_scan_html'][] = do_blocks( $GLOBALS['lunara_test_page'] ); } );
add_action( 'wp_enqueue_scripts', static function () { do_action( 'enqueue_block_assets' ); }, 10 );
add_action( 'wp_enqueue_scripts', static function () { $GLOBALS['lunara_test_scan_html'][] = do_blocks( $GLOBALS['lunara_test_page'] ); }, PHP_INT_MAX );
add_action( 'wp_head', static function () { do_action( 'wp_enqueue_scripts' ); }, 1 );

function lunara_test_head_then_body( array $page ): string {
	$GLOBALS['lunara_test_page']      = $page;
	$GLOBALS['lunara_test_runs']      = array();
	$GLOBALS['lunara_test_scan_html'] = array();
	do_action( 'wp_head' );
	return do_blocks( $page );
}

// A public page.
$body = lunara_test_head_then_body( $page );
$runs = $GLOBALS['lunara_test_runs'];
lunara_test_assert( 2 === count( $GLOBALS['lunara_test_scan_html'] ), 'the head runs both GutenKit scans' );
foreach ( lunara_home_section_block_map() as $slug => $name ) {
	lunara_test_assert( 1 === ( $runs[ $name ] ?? 0 ), "the {$slug} section ({$name}) renders once, for the page, not for the two scans" );
	lunara_test_assert( false !== strpos( $body, 'data-block="' . $name . '"' ), "the page prints the {$slug} section" );
	foreach ( $GLOBALS['lunara_test_scan_html'] as $scan ) {
		lunara_test_assert( false === strpos( $scan, 'data-block="' . $name . '"' ), "a scan gets nothing from the {$slug} section" );
	}
}
lunara_test_assert( 3 === ( $runs['gutenkit/advanced-heading'] ?? 0 ) && false !== strpos( $GLOBALS['lunara_test_scan_html'][0], 'gutenkit/advanced-heading' ), "GutenKit's own block still renders for both scans, so GutenKit finds its CSS" );
lunara_test_assert( 3 === ( $runs['lunara/pairing-showcase'] ?? 0 ), 'a Lunara block that is not a Home section still renders for the scans (Pairing Showcase enqueues its stylesheet then)' );
lunara_test_assert( 3 === ( $runs['core/paragraph'] ?? 0 ), 'a core block still renders for the scans' );

// A Home section block with inner blocks still renders for a scan.
$nested = array( lunara_test_block( 'lunara/journal-lane', array( lunara_test_block( 'gutenkit/advanced-heading' ) ) ) );
lunara_test_head_then_body( $nested );
lunara_test_assert( 3 === ( $GLOBALS['lunara_test_runs']['lunara/journal-lane'] ?? 0 ), 'a Home section block with inner blocks still renders for the scans' );

// Outside an asset hook nothing is skipped, and an earlier answer stands.
lunara_test_assert( null === apply_filters( 'pre_render_block', null, lunara_test_block( 'lunara/running-order' ), null ), 'outside an asset hook a Home section renders as usual' );
add_filter( 'pre_render_block', static function ( $pre, $block ) { return 'core/paragraph' === $block['blockName'] ? '<p>cached</p>' : $pre; }, 1, 2 );
$GLOBALS['wp_current_filter'] = array( 'wp_enqueue_scripts' );
lunara_test_assert( '<p>cached</p>' === apply_filters( 'pre_render_block', null, lunara_test_block( 'core/paragraph' ), null ), 'an answer from an earlier filter passes through' );
lunara_test_assert( '' === apply_filters( 'pre_render_block', null, lunara_test_block( 'lunara/oscar-picks' ), null ), 'inside wp_enqueue_scripts a Home section answers with an empty string' );
$GLOBALS['wp_current_filter'] = array( 'wp_footer' );
lunara_test_assert( null === apply_filters( 'pre_render_block', null, lunara_test_block( 'lunara/oscar-picks' ), null ), 'in the footer a Home section renders as usual' );
$GLOBALS['wp_current_filter'] = array();

// Admin screens and Ajax are left alone: the editor's own asset hooks run
// there, and nothing on them is a public page.
$GLOBALS['lunara_test_admin'] = true;
lunara_test_head_then_body( $page );
lunara_test_assert( 3 === ( $GLOBALS['lunara_test_runs']['lunara/running-order'] ?? 0 ), 'on an admin screen a scan still renders the Home sections' );
$GLOBALS['lunara_test_admin'] = false;
$GLOBALS['lunara_test_ajax']  = true;
lunara_test_head_then_body( $page );
lunara_test_assert( 3 === ( $GLOBALS['lunara_test_runs']['lunara/running-order'] ?? 0 ), 'in an Ajax request a scan still renders the Home sections' );
$GLOBALS['lunara_test_ajax'] = false;

fwrite( STDOUT, "Block scan guard runtime passed: {$checks} checks.\n" );
