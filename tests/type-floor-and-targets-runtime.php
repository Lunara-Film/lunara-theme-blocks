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
function floor_block( $css, $selector_pattern ) {
	// The first rule block whose selector matches the pattern, body only.
	return preg_match( '/' . $selector_pattern . '[^{]*\{([^}]*)\}/s', $css, $m ) ? $m[1] : '';
}

// ---- The 13px floor ---------------------------------------------------------------------------------
$header = floor_read( 'inc/header-command.php' );
floor_assert( 1 === preg_match( '/\.lunara-header-nav a, \.lunara-header-nav-link \{[^}]*font-size: \.8125rem;/s', $header ), 'The header navigation links are 13px, not 12.8px.' );

$home_modules = floor_read( 'assets/css/lunara-home-modules.css' );
floor_assert( false !== strpos( $home_modules, '.lunara-home-program.is-cover .lunara-home-program-dateline-items{font-size:.8125rem;' ), 'The Home cover dateline is 13px, not 12px.' );

$review_single = floor_read( 'assets/css/lunara-review-single.css' );
foreach ( array( 'body.single-review .lunara-review-single-debrief .lunara-debrief-list--signature li strong', 'html body.single-review .lunara-review-single-page .lunara-review-single-debrief .lunara-debrief-list--signature li strong' ) as $selector ) {
	$body = floor_block( $review_single, preg_quote( $selector, '/' ) );
	floor_assert( '' !== $body && 1 === preg_match( '/font-size: 0\.8125rem !important;/', $body ), "The Signature list labels (Score, Where to Watch) are 13px in `{$selector}`." );
}
$plate = floor_block( $review_single, preg_quote( '.lunara-review-single-debrief-section .lunara-pair-card-plate-title', '/' ) );
floor_assert( '' !== $plate && false !== strpos( $plate, 'font-size: 0.8125rem !important;' ), 'A pairing card title plate reads at 13px, not 12.8px.' );

$rendering = floor_read( 'inc/review-rendering.php' );
foreach ( array( 'lunara-review-watch-eyebrow', 'lunara-review-watch-meta' ) as $class ) {
	$body = floor_block( $rendering, 'body\.single-review \.' . preg_quote( $class, '/' ) );
	floor_assert( '' !== $body && false !== strpos( $body, 'font-size: .8125rem !important;' ), "The where-to-watch chip's {$class} is 13px." );
}
$affiliate = floor_block( $rendering, 'body\.single-review \.lunara-review-watch-card\.is-affiliate::after' );
floor_assert( '' !== $affiliate && false !== strpos( $affiliate, 'font-size: .8125rem;' ), 'The chip\'s Affiliate mark is 13px.' );
floor_assert( false === strpos( $rendering, 'font-size: .66rem' ) && false === strpos( $rendering, 'font-size: .72rem' ) && false === strpos( $rendering, 'font-size: .64rem' ), 'No 10-11px sizes remain in the review Studio CSS.' );

$portal = floor_read( 'assets/css/lunara-oscars-portal.css' );
$em = floor_block( $portal, preg_quote( 'body.lunara-oscars-portal-page .lunara-oscars-board-call em', '/' ) );
floor_assert( '' !== $em && false !== strpos( $em, 'font-size: max(0.8125rem, 0.78em);' ), 'A film name on the board never drops under 13px.' );

// ---- 44px targets ----------------------------------------------------------------------------------
foreach ( array( 'assets/css/lunara-shell.css', 'assets/css/lunara-shell-non-portal.css', 'style.css' ) as $file ) {
	$css = floor_read( $file );
	$pager = floor_block( $css, preg_quote( '.lunara-archive-pagination .page-numbers', '/' ) );
	floor_assert( '' !== $pager && false !== strpos( $pager, 'min-width: 44px' ) && false !== strpos( $pager, 'min-height: 44px' ), "Archive pagination targets are 44px in {$file}." );
}
foreach ( array( 'assets/css/*.css', 'style.css', 'inc/*.php' ) as $pattern ) {
	foreach ( glob( ABSPATH . $pattern ) as $path ) {
		if ( false !== strpos( $path, 'lunara-control-desk' ) ) { continue; }
		$css = (string) file_get_contents( $path );
		floor_assert( 0 === preg_match( '/min-(width|height):\s*4[0-3]px/', $css ), 'No control keeps a 40-43px minimum in ' . basename( $path ) . ' (controls aim for 44px).' );
	}
}
$journal = floor_read( 'assets/css/lunara-journal-archive.css' );
$pill = floor_block( $journal, '#primary\.lunara-journal-archive-page \.lunara-journal-filter-pill,\s*#primary\.lunara-journal-archive-page \.lunara-archive-sort-link' );
floor_assert( '' !== $pill && false !== strpos( $pill, 'min-height: 44px !important;' ) && false !== strpos( $pill, 'min-width: 44px !important;' ), 'A Journal filter pill is at least 44px wide and tall ("All" was 42px wide).' );
$critical = floor_read( 'inc/journal-archive-critical.php' );
floor_assert( false !== strpos( $critical, '.lunara-journal-filter-pill,#primary.lunara-journal-archive-page .lunara-archive-sort-link{min-height:44px!important;min-width:44px!important}' ), 'The Journal first paint says the same 44px pill.' );
floor_assert( 1 === preg_match( '/body\.lunara-oscars-portal-page :is\(\.lunara-ceremony-winner-copy, \.lunara-oscars-spotlight-card-copy\) a\.lunara-oscars-text-link \{\s*padding-block: 4px;\s*\}/', $portal ), 'The stacked links in Oscars cards carry 4px of vertical padding, so each is at least 24px to tap.' );

echo "Type floor and targets: {$checks} checks passed.\n";
