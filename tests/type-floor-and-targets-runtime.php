<?php
/**
 * Theme 3.2.153: the 13px floor and the 44px targets, where the live audit of
 * Home, Reviews, a review, Journal and Oscars (390 and 1440px) found text
 * under 13px or controls under 44px. Source facts, read from the theme.
 *
 * Run: php tests/type-floor-and-targets-runtime.php
 */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$checks = 0;
function floor_assert( $condition, $message ) { global $checks; ++$checks; if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
function floor_read( $file ) { return (string) file_get_contents( ABSPATH . $file ); }
function floor_block( $css, $selector_pattern, $must_declare = '' ) {
	// The first rule block whose selector matches the pattern (and declares
	// $must_declare when given), body only.
	if ( ! preg_match_all( '/' . $selector_pattern . '[^{]*\{([^}]*)\}/s', $css, $all ) ) { return ''; }
	foreach ( $all[1] as $body ) { if ( '' === $must_declare || false !== strpos( $body, $must_declare ) ) { return $body; } }
	return '';
}
// 3.2.154: the floor is a behaviour (at least 13px, at least 44px), not a string.
function floor_px( $value ) {
	$value = trim( (string) $value );
	if ( preg_match( '/^max\((.*)\)$/s', $value, $m ) ) { return max( array_map( 'floor_px', explode( ',', $m[1] ) ) ); }
	if ( preg_match( '/^([\d.]+)px/', $value, $m ) ) { return (float) $m[1]; }
	if ( preg_match( '/^([\d.]+)(r?em)/', $value, $m ) ) { return (float) $m[1] * 16; }
	return 0.0;
}
function floor_declared( $body, $property ) {
	// The last declaration wins within a block.
	return preg_match_all( '/(?:^|;|\s)' . preg_quote( $property, '/' ) . '\s*:\s*([^;!]+)/', $body, $m ) ? floor_px( end( $m[1] ) ) : 0.0;
}

// ---- The 13px floor ---------------------------------------------------------------------------------
$header = floor_read( 'inc/header-command.php' );
floor_assert( floor_declared( floor_block( $header, preg_quote( '.lunara-header-nav a, .lunara-header-nav-link', '/' ), 'font-size' ), 'font-size' ) >= 13, 'The header navigation links are at least 13px (were 12.8px).' );

$home_modules = floor_read( 'assets/css/lunara-home-modules.css' );
floor_assert( floor_declared( floor_block( $home_modules, preg_quote( 'body.home .lunara-home-program.is-cover .lunara-home-program-dateline-items', '/' ), 'font-size' ), 'font-size' ) >= 13, 'The Home cover dateline is at least 13px (was 12px).' );

$review_single = floor_read( 'assets/css/lunara-review-single.css' );
foreach ( array( 'body.single-review .lunara-review-single-debrief .lunara-debrief-list--signature li strong', 'html body.single-review .lunara-review-single-page .lunara-review-single-debrief .lunara-debrief-list--signature li strong' ) as $selector ) {
	$body = floor_block( $review_single, preg_quote( $selector, '/' ), 'font-size' );
	floor_assert( '' !== $body && floor_declared( $body, 'font-size' ) >= 13, "The Signature list labels (Score, Where to Watch) are at least 13px in `{$selector}`." );
}
$plate = floor_block( $review_single, preg_quote( '.lunara-review-single-debrief-section .lunara-pair-card-plate-title', '/' ), 'font-size' );
floor_assert( '' !== $plate && floor_declared( $plate, 'font-size' ) >= 13, 'A pairing card title plate reads at 13px or more (was 12.8px).' );

$rendering = floor_read( 'inc/review-rendering.php' );
foreach ( array( 'lunara-review-watch-eyebrow', 'lunara-review-watch-meta', 'lunara-review-watch-disclosure' ) as $class ) {
	$body = floor_block( $rendering, 'body\.single-review \.' . preg_quote( $class, '/' ), 'font-size' );
	floor_assert( '' !== $body && floor_declared( $body, 'font-size' ) >= 13, "The where-to-watch chip's {$class} is at least 13px." );
}
$affiliate = floor_block( $rendering, 'body\.single-review \.lunara-review-watch-card\.is-affiliate::after', 'font-size' );
floor_assert( '' !== $affiliate && floor_declared( $affiliate, 'font-size' ) >= 13, 'The chip\'s Affiliate mark is at least 13px.' );
// The public where-to-watch stylesheet (the editor's own meta box keeps its small print).
floor_assert( 1 === preg_match( '/<style id="lunara-review-watch-links-css">(.*?)<\/style>/s', $rendering, $watch_css ), 'The where-to-watch stylesheet is printed as one block.' );
preg_match_all( '/font-size:\s*([^;!]+)/', $watch_css[1], $all_sizes );
$small = array_filter( array_map( 'floor_px', $all_sizes[1] ), static function ( $px ) { return $px > 0 && $px < 13; } );
floor_assert( count( $all_sizes[1] ) >= 4 && empty( $small ), 'No font-size under 13px remains in the public where-to-watch CSS (found ' . implode( ', ', $small ) . ').' );

$portal = floor_read( 'assets/css/lunara-oscars-portal.css' );
$em = floor_block( $portal, preg_quote( 'body.lunara-oscars-portal-page .lunara-oscars-board-call em', '/' ), 'font-size' );
floor_assert( '' !== $em && floor_declared( $em, 'font-size' ) >= 13 && false !== strpos( $em, 'max(' ), 'A film name on the board never drops under 13px: a max() keeps the floor under a smaller em base.' );

// ---- 44px targets ----------------------------------------------------------------------------------
// Every control the audit found under 44px, read from its own rule.
$targets = array(
	array( 'assets/css/lunara-shell.css', '.lunara-archive-pagination .page-numbers', array( 'min-width', 'min-height' ) ),
	array( 'assets/css/lunara-shell-non-portal.css', '.lunara-archive-pagination .page-numbers', array( 'min-width', 'min-height' ) ),
	array( 'style.css', '.lunara-archive-pagination .page-numbers', array( 'min-width', 'min-height' ) ),
	array( 'assets/css/lunara-shell.css', 'body.lunara-oscars-portal-page .lunara-btn', array( 'min-height' ) ),
	array( 'assets/css/lunara-shell.css', '.lunara-reader-toc-link', array( 'min-height' ) ),
	array( 'assets/css/lunara-shell-non-portal.css', '.lunara-reader-toc-link', array( 'min-height' ) ),
	array( 'style.css', '.lunara-reader-toc-link', array( 'min-height' ) ),
	array( 'style.css', '.lunara-archive-sort-link', array( 'min-height' ) ),
	array( 'style.css', '.lunara-nav .lunara-nav-list > li > a', array( 'min-height' ) ),
	array( 'style.css', '.lunara-review-single-debrief .lunara-oscar-ledger-pill', array( 'min-height' ) ),
	array( 'style.css', 'body.single-journal .lunara-review-single-rail-actions .lunara-btn', array( 'min-height' ) ),
	array( 'assets/css/lunara-review-single.css', 'body.single-review .lunara-reader-toc-link', array( 'min-height' ) ),
	array( 'assets/css/lunara-review-archive.css', '.lunara-review-archive-page .lunara-review-archive-retention-actions a', array( 'min-height' ) ),
	// 3.2.156: the Film Index sort links (42px) and the dossier's Ledger action (20px).
	array( 'style.css', '.lunara-entity-sort a', array( 'min-height' ) ),
	array( 'style.css', '.lunara-entity-ledger-link a', array( 'min-height' ) ),
);
foreach ( $targets as list( $file, $selector, $properties ) ) {
	$css  = floor_read( $file );
	$body = floor_block( $css, preg_quote( $selector, '/' ), $properties[0] );
	floor_assert( '' !== $body, "`{$selector}` in {$file} declares {$properties[0]}." );
	foreach ( $properties as $property ) {
		floor_assert( floor_declared( $body, $property ) >= 44, "`{$selector}` in {$file} keeps {$property} at 44px or more." );
	}
}
foreach ( array( 'assets/css/*.css', 'style.css', 'inc/*.php' ) as $pattern ) {
	foreach ( glob( ABSPATH . $pattern ) as $path ) {
		if ( false !== strpos( $path, 'lunara-control-desk' ) ) { continue; }
		$css = (string) file_get_contents( $path );
		floor_assert( 0 === preg_match( '/min-(width|height):\s*4[0-3]px/', $css ), 'No control keeps a 40-43px minimum in ' . basename( $path ) . ' (controls aim for 44px).' );
	}
}
$journal = floor_read( 'assets/css/lunara-journal-archive.css' );
$pill = floor_block( $journal, '#primary\.lunara-journal-archive-page \.lunara-journal-filter-pill,\s*#primary\.lunara-journal-archive-page \.lunara-archive-sort-link', 'min-height' );
floor_assert( '' !== $pill && floor_declared( $pill, 'min-height' ) >= 44 && floor_declared( $pill, 'min-width' ) >= 44, 'A Journal filter pill is at least 44px wide and tall ("All" was 40.6px wide).' );
$critical      = floor_read( 'inc/journal-archive-critical.php' );
$critical_pill = floor_block( $critical, '#primary\.lunara-journal-archive-page \.lunara-journal-filter-pill,#primary\.lunara-journal-archive-page \.lunara-archive-sort-link', 'min-height' );
floor_assert( '' !== $critical_pill && floor_declared( $critical_pill, 'min-height' ) >= 44 && floor_declared( $critical_pill, 'min-width' ) >= 44, 'The Journal first paint says the same 44px pill.' );
floor_assert( 1 === preg_match( '/body\.lunara-oscars-portal-page :is\(\.lunara-ceremony-winner-copy, \.lunara-oscars-spotlight-card-copy\) a\.lunara-oscars-text-link \{\s*padding-block: 4px;\s*\}/', $portal ), 'The stacked links in Oscars cards carry 4px of vertical padding, so each is at least 24px to tap.' );

echo "Type floor and targets: {$checks} checks passed.\n";
