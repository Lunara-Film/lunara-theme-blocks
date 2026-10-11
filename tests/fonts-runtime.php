<?php
/**
 * Theme 3.2.101: the font diet.
 *
 * Run: php tests/fonts-runtime.php
 *
 * Measured on 2026-10-08: every page loaded 214-357 KB of fonts in 5-8 files,
 * and 2-4 of them were TTFs. The TTFs came from WordPress core's Font Library
 * (the `<style class="wp-fonts-local">` block), which declared Tiempos Text,
 * Tiempos Headline, GT Sectra and Canela a second time, from
 * /wp-content/uploads/fonts/*.ttf, on top of the theme's own woff2 faces. The
 * three label-face preloads also pointed at a cut the diet drops.
 *
 * The contract:
 *   1. inc/fonts.php unhooks the Font Library's front-end output. The theme is
 *      the only source of @font-face.
 *   2. style.css declares Tiempos as four woff2 files only: Text Regular, Text
 *      Italic, Text Semibold, Headline Semibold. No .ttf anywhere.
 *   3. The two kept weights carry ranges, so every weight the CSS asks for
 *      resolves to a real face and nothing is synthesized.
 *   4. Each Tiempos family has a metric-matched Georgia fallback face
 *      (size-adjust + ascent/descent overrides), and the stacks in
 *      inc/design-tokens.php and style.css name it right after the family.
 *   5. No preload points at a file the CSS no longer declares.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

function lunara_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Assertion failed: {$message}\n" );
		exit( 1 );
	}
}

// ---------------------------------------------------------------------------
// Minimal WordPress surface: record what inc/fonts.php hooks and unhooks.
// ---------------------------------------------------------------------------
$GLOBALS['fonts_test'] = array( 'added' => array(), 'removed' => array(), 'is_admin' => false );
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['fonts_test']['added'][] = array( $hook, $callback, $priority ); }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['fonts_test']['added'][] = array( $hook, $callback, $priority ); }
function remove_action( $hook, $callback, $priority = 10 ) { $GLOBALS['fonts_test']['removed'][] = array( $hook, $callback, $priority ); return true; }
function is_admin() { return $GLOBALS['fonts_test']['is_admin']; }
function apply_filters( $hook, $value ) { return $value; }
function __( $text, $domain = '' ) { return $text; }
function get_option( $name, $default = false ) { return 'lunara_design_tokens' === $name ? ( $GLOBALS['fonts_test']['saved'] ?? array() ) : $default; }
function get_theme_mod( $name, $default = false ) { return $default; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $value ) ? $value : null; }

require ABSPATH . 'inc/fonts.php';
require ABSPATH . 'inc/design-tokens.php';

// 1. The Font Library's front-end output is unhooked, at the point WordPress adds it (wp_head, priority 50).
lunara_test_assert( function_exists( 'lunara_fonts_unhook_font_library' ), 'inc/fonts.php defines lunara_fonts_unhook_font_library().' );
$registered = array_filter( $GLOBALS['fonts_test']['added'], function ( $a ) { return 'init' === $a[0] && 'lunara_fonts_unhook_font_library' === $a[1]; } );
lunara_test_assert( ! empty( $registered ), 'lunara_fonts_unhook_font_library() runs on init.' );
lunara_fonts_unhook_font_library();
$removed = array_map( function ( $r ) { return $r[0] . ':' . $r[1] . ':' . $r[2]; }, $GLOBALS['fonts_test']['removed'] );
lunara_test_assert( in_array( 'wp_head:wp_print_font_faces:50', $removed, true ), 'wp_print_font_faces is removed from wp_head at priority 50.' );
lunara_test_assert( in_array( 'wp_head:wp_print_font_faces_from_style_variations:50', $removed, true ), 'wp_print_font_faces_from_style_variations is removed from wp_head at priority 50.' );
$GLOBALS['fonts_test']['removed']  = array();
$GLOBALS['fonts_test']['is_admin'] = true;
lunara_fonts_unhook_font_library();
lunara_test_assert( empty( $GLOBALS['fonts_test']['removed'] ), 'The editor keeps its Font Library faces; only the front end is unhooked.' );

// 2. style.css declares exactly the four Tiempos woff2 files, and no .ttf anywhere.
$css = (string) file_get_contents( ABSPATH . 'style.css' );
lunara_test_assert( false === stripos( $css, '.ttf' ), 'style.css references no .ttf file.' );
preg_match_all( '/@font-face\s*\{([^}]*)\}/s', $css, $faces );
$tiempos = array();
foreach ( $faces[1] as $decl ) {
	if ( ! preg_match( '/font-family:\s*[\'"]?(Tiempos (?:Text|Headline))[\'"]?\s*;/', $decl, $fam ) ) {
		continue;
	}
	preg_match( '/url\([\'"]?([^\'")]+)/', $decl, $src );
	preg_match( '/font-weight:\s*([^;]+);/', $decl, $weight );
	preg_match( '/font-style:\s*([^;]+);/', $decl, $style );
	$tiempos[] = array( 'family' => $fam[1], 'file' => basename( $src[1] ?? '' ), 'weight' => trim( $weight[1] ?? '' ), 'style' => trim( $style[1] ?? 'normal' ), 'decl' => $decl );
}
$files = array_column( $tiempos, 'file' );
sort( $files );
lunara_test_assert(
	$files === array( 'TiemposText-Italic.woff2', 'TiemposText-Regular.woff2', 'TiemposText-Semibold.woff2', 'hinted-TiemposHeadline-Semibold.woff2' ),
	'Tiempos is exactly four woff2 files: Text Regular, Text Italic, Text Semibold, Headline Semibold (got ' . implode( ', ', $files ) . ').'
);

// 3. Weight ranges: Text Semibold answers 600-900, Headline Semibold answers 400-900.
foreach ( $tiempos as $face ) {
	if ( 'TiemposText-Semibold.woff2' === $face['file'] ) {
		lunara_test_assert( '600 900' === $face['weight'], 'Text Semibold is declared for weights 600-900 (got "' . $face['weight'] . '").' );
	}
	if ( 'hinted-TiemposHeadline-Semibold.woff2' === $face['file'] ) {
		lunara_test_assert( '400 900' === $face['weight'], 'Headline Semibold is declared for weights 400-900 (got "' . $face['weight'] . '").' );
	}
	lunara_test_assert( false !== strpos( $face['decl'], 'font-display: swap' ), $face['file'] . ' keeps font-display: swap.' );
}

// 4. The real synchronous head output contains the local metric-matched faces
// and resolves default/custom font roles before deferred base styles arrive.
$GLOBALS['fonts_test']['is_admin'] = false;
ob_start();
lunara_design_tokens_output_css();
$head = (string) ob_get_clean();
preg_match_all( '/@font-face\s*\{([^}]*)\}/s', $head, $fallback_faces );
$expected_metrics = array(
	'Tiempos Text Fallback' => array( 'size-adjust' => '105.89%', 'ascent-override' => '99.54%', 'descent-override' => '23.23%', 'line-gap-override' => '0%' ),
	'Tiempos Headline Fallback' => array( 'size-adjust' => '105.15%', 'ascent-override' => '74.47%', 'descent-override' => '20.64%', 'line-gap-override' => '19.02%' ),
);
foreach ( array( 'Tiempos Text Fallback', 'Tiempos Headline Fallback' ) as $fallback ) {
	$found = false;
	foreach ( $fallback_faces[1] as $decl ) {
		if ( false === strpos( $decl, "'" . $fallback . "'" ) && false === strpos( $decl, '"' . $fallback . '"' ) ) {
			continue;
		}
		$found = true;
		lunara_test_assert( preg_match( '/src:\s*local\(/', $decl ), $fallback . ' is a local() face (Georgia), never a download.' );
		foreach ( $expected_metrics[ $fallback ] as $property => $expected ) {
			preg_match( '/' . preg_quote( $property, '/' ) . ':\s*([^;}]+)/', $decl, $metric );
			lunara_test_assert( trim( $metric[1] ?? '' ) === $expected, $fallback . ' preserves measured ' . $property . ' metrics.' );
		}
	}
	lunara_test_assert( $found, $fallback . ' is available in the synchronous public head output.' );
}
$choices = lunara_design_token_font_choices();
foreach ( lunara_design_token_font_role_specs() as $role => $spec ) {
	preg_match( '/' . preg_quote( $spec['var'], '/' ) . ':\s*([^;}]+)/', $head, $role_value );
	lunara_test_assert( trim( $role_value[1] ?? '' ) === $choices[ $spec['default'] ]['stack'], 'The default ' . $role . ' role is resolved before deferred CSS.' );
}
foreach ( array( 'body-font' => 'body', 'heading-font' => 'display', 'label-font' => 'label', 'body-font-stack' => 'body', 'heading-font-stack' => 'display' ) as $alias => $role ) {
	preg_match( '/--lunara-' . $alias . ':\s*([^;}]+)/', $head, $alias_value );
	lunara_test_assert( trim( $alias_value[1] ?? '' ) === 'var(--lunara-font-' . $role . ')', 'The legacy ' . $alias . ' alias shares its resolved canonical role.' );
}
$GLOBALS['fonts_test']['saved'] = array( 'fonts' => array( 'body' => 'georgia', 'display' => 'canela-deck', 'label' => array( 'invalid' ) ) );
ob_start();
lunara_design_tokens_output_css();
$custom_head = (string) ob_get_clean();
foreach ( array( 'body' => 'georgia', 'display' => 'canela-deck', 'label' => 'tiempos-text' ) as $role => $choice ) {
	preg_match( '/--lunara-font-' . $role . ':\s*([^;}]+)/', $custom_head, $role_value );
	lunara_test_assert( trim( $role_value[1] ?? '' ) === $choices[ $choice ]['stack'], 'Saved ' . $role . ' font is preserved, or falls back safely when malformed.' );
}

// 5. No preload points at a file the CSS does not declare.
$frontend = (string) file_get_contents( ABSPATH . 'inc/frontend.php' );
preg_match_all( '#lunara-fonts/v1/([A-Za-z0-9_.-]+\.woff2)#', $frontend, $preloads );
$declared = array_merge( $files, array( 'bebas-neue-latin-400.woff2' ) );
foreach ( array_unique( $preloads[1] ) as $file ) {
	lunara_test_assert( in_array( $file, $declared, true ), "inc/frontend.php preloads {$file}, which style.css no longer declares." );
}

echo "fonts-runtime: all assertions passed.\n";
