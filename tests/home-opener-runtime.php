<?php
/**
 * Behavior contract for the Home opener (3.2.120).
 *
 * Home leads with the program masthead unless the lunara_home_opener_style
 * mod says 'cinematic'; the lunara_home_cinematic_front_door_enabled filter
 * has the final say either way. And front-page.php, rendered here against
 * stubbed WordPress, keeps the legacy Customizer hero out of the section loop
 * whenever any front door renders, so the lead story never appears twice,
 * while the three Direction D slots render through their own modules.
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['lunara_test'] = array(
	'mods'       => array(),
	'filters'    => array(),
	'front_door' => '',
	'enabled'    => array(),
);

function lunara_test_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function add_action( ...$args ): void {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
	$GLOBALS['lunara_test']['filters'][ $hook ][] = $callback;
}
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['lunara_test']['filters'][ $hook ] ?? array() as $callback ) {
		$value = call_user_func( $callback, $value, ...$args );
	}
	return $value;
}
function get_theme_mod( $key, $default = false ) {
	return $GLOBALS['lunara_test']['mods'][ $key ] ?? $default;
}
function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
}
function lunara_test_true() { return true; }
function lunara_test_false() { return false; }

require dirname( __DIR__ ) . '/inc/cinematic-home.php';

// Default: the program masthead leads, the carousel wrapper stays off.
lunara_test_assert( 'program' === lunara_home_opener_style(), 'the opener defaults to the program masthead' );
lunara_test_assert( false === lunara_home_cinematic_front_door_is_enabled(), 'the cinematic wrapper is off by default' );

// The legacy Hero Command checkbox no longer decides: no screen can reach it
// once the hero carousel is adopted in Site Studio.
$GLOBALS['lunara_test']['mods']['lunara_home_cinematic_front_door_enabled'] = 1;
lunara_test_assert( false === lunara_home_cinematic_front_door_is_enabled(), 'the orphaned Hero Command mod does not bring the carousel back' );

// One mod brings the carousel back; junk values fall back to the program.
$GLOBALS['lunara_test']['mods']['lunara_home_opener_style'] = 'cinematic';
lunara_test_assert( true === lunara_home_cinematic_front_door_is_enabled(), 'lunara_home_opener_style = cinematic enables the carousel wrapper' );
$GLOBALS['lunara_test']['mods']['lunara_home_opener_style'] = 'Cinematic!';
lunara_test_assert( 'cinematic' === lunara_home_opener_style(), 'the mod value is normalized before it is read' );
$GLOBALS['lunara_test']['mods']['lunara_home_opener_style'] = 'carousel';
lunara_test_assert( 'program' === lunara_home_opener_style() && false === lunara_home_cinematic_front_door_is_enabled(), 'an unknown opener value falls back to the program masthead' );

// The filter has the final say in both directions.
add_filter( 'lunara_home_cinematic_front_door_enabled', 'lunara_test_true' );
lunara_test_assert( true === lunara_home_cinematic_front_door_is_enabled(), 'a filter can force the carousel on' );
$GLOBALS['lunara_test']['filters'] = array();
$GLOBALS['lunara_test']['mods']['lunara_home_opener_style'] = 'cinematic';
add_filter( 'lunara_home_cinematic_front_door_enabled', 'lunara_test_false' );
lunara_test_assert( false === lunara_home_cinematic_front_door_is_enabled(), 'a filter can force the carousel off' );

/*
 * front-page.php against stubbed WordPress. The section renderers are stubs
 * that leave a marker each, so the output shows which slots rendered, and in
 * which order.
 */
function get_header() { echo '<!--header-->'; }
function get_footer() { echo '<!--footer-->'; }
function get_bloginfo( $what ) { return 'Lunara Film'; }
function __( $text, $domain = null ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function wp_reset_postdata() {}
function lunara_home_uses_block_composition() { return ! empty( $GLOBALS['lunara_test']['block_mode'] ); }
function lunara_render_home_block_composition( $excluded = array() ) {
	$GLOBALS['lunara_test']['excluded'] = array_values( (array) $excluded );
	$blocks = array( 'lunara/cinematic-hero' => '<section class="lunara-home-hero lunara-home-slot-hero">hero block</section>', 'lunara/pairing-desk' => '<section class="lunara-home-slot-pairing-desk">stills block</section>', 'lunara/running-order' => '<section class="lunara-home-slot-featured">running order block</section>' );
	foreach ( $GLOBALS['lunara_test']['excluded'] as $name ) { unset( $blocks[ $name ] ); }
	return implode( '', $blocks );
}
function lunara_render_home_front_door() { return (string) $GLOBALS['lunara_test']['front_door']; }
function lunara_get_home_section_order_map() {
	return array( 'hero' => 1, 'featured' => 2, 'pairing-desk' => 3, 'ledger' => 4, 'dispatch' => 5, 'latest-reviews' => 6, 'oscar-picks' => 7, 'oscar-facts' => 8 );
}
function lunara_home_section_is_enabled( $slug ) { return $GLOBALS['lunara_test']['enabled'][ $slug ] ?? true; }
function lunara_render_cinematic_hero_carousel( $args = array() ) { return '<section class="lunara-home-slot-hero" data-lunara-home-hero-source="legacy">hero</section>'; }
function lunara_render_homepage_latest_reviews() { return '<section class="lunara-home-slot-latest-reviews">latest</section>'; }
function lunara_render_homepage_journal_lane() { return '<section class="lunara-home-slot-dispatch">journal</section>'; }
function lunara_render_oscar_picks_carousel() { return '<section class="lunara-home-slot-oscar-picks">picks</section>'; }
function lunara_render_oscar_facts_carousel() { return '<section class="lunara-home-slot-oscar-facts">facts</section>'; }
function lunara_render_home_pairing_desk() { return '<section class="lunara-home-slot-pairing-desk">pair-it-with</section>'; }
// No published reviews here: The Running Order (its own contract is
// tests/home-running-order-runtime.php) has nothing to show and adds nothing.
class WP_Query { public $posts = array(); public function __construct( array $args = array() ) {} }

// The Direction D slot modules load the way functions-loader.php loads them.
require dirname( __DIR__ ) . '/inc/home-running-order.php';
require dirname( __DIR__ ) . '/inc/home-stills-that-rhyme.php';
require dirname( __DIR__ ) . '/inc/home-ledger-band.php';

function lunara_test_render_front_page(): string {
	ob_start();
	include dirname( __DIR__ ) . '/front-page.php';
	return (string) ob_get_clean();
}

function lunara_test_slot_order( string $html ): array {
	preg_match_all( '/lunara-home-slot-([a-z-]+)/', $html, $m );
	return $m[1];
}

// The program masthead leads: it has no carousel marker, and the legacy hero
// must still stay out, or the lead story shows twice.
$GLOBALS['lunara_test']['front_door'] = '<section class="lunara-home-masthead lunara-home-program">masthead</section>';
$html = lunara_test_render_front_page();
lunara_test_assert( 1 === substr_count( $html, 'lunara-home-program' ), 'the program masthead renders once' );
lunara_test_assert( ! in_array( 'hero', lunara_test_slot_order( $html ), true ), 'the legacy hero slot stays out beneath the program masthead' );

// The cinematic wrapper leads: same rule, as before.
$GLOBALS['lunara_test']['front_door'] = '<section data-lunara-home-hero-source="site-studio">carousel</section>';
$html = lunara_test_render_front_page();
lunara_test_assert( ! in_array( 'hero', lunara_test_slot_order( $html ), true ), 'the legacy hero slot stays out beneath the cinematic wrapper' );

// No front door at all: the legacy hero is the fallback and renders first.
$GLOBALS['lunara_test']['front_door'] = '';
$html = lunara_test_render_front_page();
lunara_test_assert( 'hero' === ( lunara_test_slot_order( $html )[0] ?? '' ), 'with no front door the legacy hero slot renders first' );
lunara_test_assert( false !== strpos( $html, '<h1 class="screen-reader-text lunara-screen-reader-text">Lunara Film</h1>' ), 'with no front door Home still carries a semantic H1' );

// Direction D slots: pairing-desk hands back the Pair It With desk until the
// stills renderer lands; featured has no reviews to show here and ledger
// renders nothing until its renderer does, and their toggles stay honored.
$GLOBALS['lunara_test']['front_door'] = '<section class="lunara-home-masthead lunara-home-program">masthead</section>';
$html = lunara_test_render_front_page();
lunara_test_assert( false !== strpos( $html, '>pair-it-with<' ), 'the pairing-desk slot still renders the Pair It With desk through the Stills that Rhyme module' );
lunara_test_assert( array( 'pairing-desk', 'dispatch', 'latest-reviews', 'oscar-picks', 'oscar-facts' ) === lunara_test_slot_order( $html ), 'the saved section order is kept and empty Direction D renderers add nothing' );
$GLOBALS['lunara_test']['enabled']['pairing-desk'] = false;
$html = lunara_test_render_front_page();
lunara_test_assert( false === strpos( $html, 'pair-it-with' ), 'an off toggle hides the pairing-desk slot' );

// Block mode (the live Home since 3.1.50): the blocks are the page, and the
// hero block stays out under any front door, not only under the carousel.
$GLOBALS['lunara_test']['block_mode'] = true;
$GLOBALS['lunara_test']['enabled']    = array();
$GLOBALS['lunara_test']['front_door'] = '<section class="lunara-home-masthead lunara-home-program">masthead</section>';
$html = lunara_test_render_front_page();
lunara_test_assert( array( 'lunara/cinematic-hero' ) === $GLOBALS['lunara_test']['excluded'], 'in block mode the program masthead keeps the hero block out of the composition' );
lunara_test_assert( false === strpos( $html, 'hero block' ) && false !== strpos( $html, 'stills block' ) && false !== strpos( $html, 'running order block' ) && 1 === substr_count( $html, 'lunara-home-program' ), 'the masthead renders once and the section blocks follow it' );
lunara_test_assert( false === strpos( $html, 'pair-it-with' ), 'the registry loop does not run beneath the block composition' );
$GLOBALS['lunara_test']['front_door'] = '<section data-lunara-home-hero-source="site-studio">carousel</section>';
lunara_test_render_front_page();
lunara_test_assert( array( 'lunara/cinematic-hero' ) === $GLOBALS['lunara_test']['excluded'], 'in block mode the carousel wrapper keeps the hero block out too' );
$GLOBALS['lunara_test']['front_door'] = '';
$html = lunara_test_render_front_page();
lunara_test_assert( array() === $GLOBALS['lunara_test']['excluded'] && false !== strpos( $html, 'hero block' ), 'with no front door the hero block is the opener' );

fwrite( STDOUT, "Home opener: program masthead leads, hero never repeats, Direction D slots wired (registry and block mode).\n" );
