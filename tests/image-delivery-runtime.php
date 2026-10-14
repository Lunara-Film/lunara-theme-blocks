<?php
/**
 * Image delivery and first-paint geometry (Theme 3.2.90): TMDB and
 * WordPress.com width candidates, the multi-width locked review srcset, the
 * external-hero fallback, and the synchronous hero and dossier seeds.
 * Real functions are extracted from the theme; WordPress is stubbed.
 *
 * Run: php tests/image-delivery-runtime.php
 */
define( 'ABSPATH', __DIR__ . '/' );

$checks = 0;
function img_assert( $condition, $message ) { global $checks; ++$checks; if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }

function esc_url( $url ) { return str_replace( '&', '&#038;', (string) $url ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
function esc_html( $text ) { return esc_attr( $text ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path = '' ) { return 'https://lunarafilm.com/' . ltrim( (string) $path, '/' ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function sanitize_html_class( $value ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value ); }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function get_the_title( $id ) { return 'Black Bag'; }
function remove_query_arg( $keys, $url ) {
	$parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $query );
	foreach ( (array) $keys as $key ) { unset( $query[ $key ] ); }
	return strtok( $url, '?' ) . ( $query ? '?' . http_build_query( $query ) : '' );
}
function add_query_arg( $args, $value = null, $url = null ) {
	if ( ! is_array( $args ) ) { $args = array( $args => $value ); } else { $url = $value; }
	$parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $query );
	return strtok( $url, '?' ) . '?' . http_build_query( array_merge( $query, $args ) );
}
function is_admin() { return false; }
function is_feed() { return false; }
function is_front_page() { return ! empty( $GLOBALS['img_route']['front'] ); }
function is_singular( $types = '' ) { return in_array( $GLOBALS['img_route']['type'] ?? '', (array) $types, true ); }
function add_action() {}
function add_filter() {}

function img_extract( $file, $name ) {
	$tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/' . $file ) );
	foreach ( $tokens as $i => $token ) {
		if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) { continue; }
		$j = $i + 1; while ( isset( $tokens[ $j ] ) && ( ! is_array( $tokens[ $j ] ) || T_STRING !== $tokens[ $j ][0] ) ) { ++$j; }
		if ( ( $tokens[ $j ][1] ?? '' ) !== $name ) { continue; }
		$code = ''; $depth = 0; $opened = false;
		for ( $k = $i; $k < count( $tokens ); ++$k ) {
			$part = is_array( $tokens[ $k ] ) ? $tokens[ $k ][1] : $tokens[ $k ]; $code .= $part;
			if ( '{' === $part || ( is_array( $tokens[ $k ] ) && in_array( $tokens[ $k ][0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) { ++$depth; $opened = true; }
			elseif ( '}' === $part && 0 === --$depth && $opened ) { eval( $code ); return; }
		}
	}
	throw new RuntimeException( 'Missing function ' . $name );
}
foreach ( array( 'lunara_tmdb_image_srcset', 'lunara_image_url_width_srcset', 'lunara_resize_tmdb_image_url', 'lunara_resize_wpcom_image_url' ) as $name ) { img_extract( 'inc/setup.php', $name ); }
foreach ( array( 'lunara_get_review_image_profile', 'lunara_review_image_can_use_wpcom_resize', 'lunara_lock_review_image_url', 'lunara_replace_img_attribute', 'lunara_remove_img_attribute', 'lunara_lock_review_image_markup', 'lunara_render_review_visual_slot' ) as $name ) { img_extract( 'inc/review-rendering.php', $name ); }
foreach ( array( 'lunara_output_home_hero_geometry_css', 'lunara_output_entity_geometry_css', 'lunara_rocket_preserve_front_door_css' ) as $name ) { img_extract( 'inc/frontend.php', $name ); }

// ---- TMDB candidates --------------------------------------------------------
$backdrop = lunara_tmdb_image_srcset( 'https://image.tmdb.org/t/p/original/gxO51FVgADhYGGnnRPIlutVqb30.jpg' );
img_assert( 'https://image.tmdb.org/t/p/w780/gxO51FVgADhYGGnnRPIlutVqb30.jpg 780w, https://image.tmdb.org/t/p/w1280/gxO51FVgADhYGGnnRPIlutVqb30.jpg 1280w, https://image.tmdb.org/t/p/original/gxO51FVgADhYGGnnRPIlutVqb30.jpg 1920w' === $backdrop, 'A TMDB still offers w780, w1280 and the original.' );
$poster = lunara_tmdb_image_srcset( 'https://image.tmdb.org/t/p/w500/abc.jpg', false );
img_assert( false === strpos( $poster, 'w1280' ) && false !== strpos( $poster, '/w780/abc.jpg 780w' ) && false !== strpos( $poster, '/original/abc.jpg 2000w' ), 'A TMDB poster never requests the backdrop-only w1280 size.' );
img_assert( '' === lunara_tmdb_image_srcset( 'https://lunarafilm.com/wp-content/uploads/x.jpg' ) && '' === lunara_tmdb_image_srcset( 'https://image.tmdb.org/t/p/original/a.jpg?x=1' ) && '' === lunara_tmdb_image_srcset( 'https://evil.test/image.tmdb.org/t/p/original/a.jpg' ), 'Only exact TMDB image URLs get TMDB candidates.' );

// ---- URL-only width candidates ----------------------------------------------------
$local = lunara_image_url_width_srcset( 'https://lunarafilm.com/wp-content/uploads/2026/07/still.jpg?resize=2000%2C1200' );
img_assert( 'https://lunarafilm.com/wp-content/uploads/2026/07/still.jpg?w=480 480w, https://lunarafilm.com/wp-content/uploads/2026/07/still.jpg?w=768 768w, https://lunarafilm.com/wp-content/uploads/2026/07/still.jpg?w=1200 1200w' === $local, 'Local uploads resize by width, dropping any crop.' );
$cdn = lunara_image_url_width_srcset( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/07/still.jpg?fit=2048%2C1152&amp;ssl=1' );
img_assert( false !== strpos( $cdn, 'still.jpg?ssl=1&w=480 480w' ) && false === strpos( $cdn, 'fit=' ), 'CDN uploads keep their ssl flag and resize by width.' );
img_assert( '' === lunara_image_url_width_srcset( 'https://example.org/wp-content/uploads/x.jpg' ) && '' === lunara_image_url_width_srcset( 'https://lunarafilm.com/about/' ) && '' === lunara_image_url_width_srcset( '' ), 'Foreign hosts and non-upload URLs are left alone.' );
img_assert( false === strpos( lunara_image_url_width_srcset( 'https://image.tmdb.org/t/p/original/p.jpg', false ), 'w1280' ), 'URL-only TMDB posters get poster-safe candidates.' );

// ---- Locked review artwork: quarter, half, locked and retina widths ---------------------
$profile = lunara_get_review_image_profile( 'lunara-review-single-debrief-poster' );
$locked  = lunara_lock_review_image_markup( '<img src="https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/07/poster.jpg?quality=86&ssl=1" class="lunara-review-single-debrief-poster" alt="A" loading="lazy">', '', $profile );
preg_match( '/\ssrcset="([^"]+)"/', $locked, $srcset );
$widths = array_map( static function ( $candidate ) { return (int) substr( strrchr( trim( $candidate ), ' ' ), 1 ); }, explode( ',', $srcset[1] ?? '' ) );
img_assert( array( 500, 1000, 2000, 4000 ) === $widths, 'A 320px debrief poster can now choose 500w or 1000w instead of only 2000w/4000w.' );
img_assert( false !== strpos( $locked, 'width="2000"' ) && false !== strpos( $locked, 'height="3000"' ) && false !== strpos( $locked, 'resize=500%2C750' ), 'Locked dimensions stay put; smaller candidates keep the 2:3 crop.' );
$external = lunara_lock_review_image_markup( '<img src="https://example.org/poster.jpg" alt="A">', '', $profile );
img_assert( false === strpos( $external, 'srcset=' ), 'Hosts that cannot resize get no invented candidates.' );

// ---- External TMDB hero fallback ------------------------------------------------------------
function lunara_get_review_visual_slot_data( $post_id, $slot ) { return array( 'url' => $GLOBALS['img_slot_url'], 'caption' => '', 'alt' => 'Black Bag Hero Banner', 'label' => 'Hero Banner', 'slot' => $slot ); }
$GLOBALS['img_slot_url'] = 'https://image.tmdb.org/t/p/original/gxO51FVgADhYGGnnRPIlutVqb30.jpg';
$hero = lunara_render_review_visual_slot( 7, 'hero_banner', array( 'context' => 'hero', 'loading' => 'eager' ) );
img_assert( false !== strpos( $hero, 'src="https://image.tmdb.org/t/p/w1280/gxO51FVgADhYGGnnRPIlutVqb30.jpg"' ) && false !== strpos( $hero, ' 1280w' ) && false !== strpos( $hero, 'fetchpriority="high"' ), 'A TMDB hero starts from w1280 and offers its widths to the browser.' );
$GLOBALS['img_slot_url'] = 'https://example.org/still-one-sheet.jpg';
$other = lunara_render_review_visual_slot( 7, 'hero_banner', array( 'context' => 'hero', 'loading' => 'eager' ) );
img_assert( false === strpos( $other, 'srcset=' ) && false !== strpos( $other, 'src="https://example.org/still-one-sheet.jpg"' ), 'Non-TMDB external heroes keep their exact URL.' );

// ---- First-paint seeds ---------------------------------------------------------------------------
$GLOBALS['img_route'] = array( 'front' => true );
ob_start(); lunara_output_home_hero_geometry_css(); $seed = ob_get_clean();
$seed_document = new DOMDocument();
$seed_document->loadHTML( $seed, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
$seed_style = $seed_document->getElementById( 'lunara-home-hero-geometry-css' );
img_assert( $seed_style instanceof DOMElement, 'The homepage emits the synchronous hero style through its public head callback.' );
$seed_css = $seed_style->textContent;
img_assert( 1 === preg_match( '/@media\s*\(min-width:\s*901px\)\s*\{(.*)\}\s*$/s', $seed_css, $desktop_seed ), 'Control positioning is scoped to the desktop media query.' );
$seed_rules = array();
preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $desktop_seed[1], $matches, PREG_SET_ORDER );
foreach ( $matches as $match ) {
	$properties = array();
	foreach ( explode( ';', $match[2] ) as $declaration ) {
		$pair = explode( ':', $declaration, 2 );
		if ( count( $pair ) === 2 ) { $properties[ trim( $pair[0] ) ] = trim( $pair[1] ); }
	}
	$seed_rules[ trim( $match[1] ) ] = $properties;
}
foreach ( array( '.lunara-cinematic-hero-carousel .lunara-hero-arrows', '.lunara-cinematic-hero-carousel .lunara-hero-pagination', '.lunara-home-curated-hero .lunara-home-carousel-toggle' ) as $selector ) {
	img_assert( 'absolute' === ( $seed_rules[ $selector ]['position'] ?? '' ), 'Desktop control stays out of document flow: ' . $selector );
}
$toggle_seed = $seed_rules['.lunara-home-curated-hero .lunara-home-carousel-toggle'];
img_assert( '50%' === $toggle_seed['left'] && 'auto' === $toggle_seed['right'] && '64px' === $toggle_seed['bottom'] && 'translateX(-50%)' === $toggle_seed['transform'], 'The emitted desktop toggle is centered without a stretching opposite inset.' );
img_assert( false === strpos( preg_replace( '/@media.*$/s', '', $seed_css ), 'lunara-home-carousel-toggle' ), 'The synchronous seed leaves phone toggle positioning to its in-flow layout.' );
img_assert( false !== strpos( file_get_contents( dirname( __DIR__ ) . '/style.css' ), ".lunara-cinematic-hero {\n    /* Single tunable hero height — contained, never full-screen. */\n    --lunara-hero-height: clamp(360px, 54vh, 600px);\n    position: relative;" ), 'The seed restates the hero root position the final stylesheet already sets.' );
ob_start(); lunara_output_entity_geometry_css(); img_assert( '' === ob_get_clean(), 'The dossier seed stays off the homepage.' );
foreach ( array( 'movie', 'person' ) as $type ) {
	$GLOBALS['img_route'] = array( 'type' => $type );
	ob_start(); lunara_output_entity_geometry_css(); $entity = ob_get_clean();
	img_assert( false !== strpos( $entity, '<style id="lunara-entity-geometry-css">' ) && false !== strpos( $entity, '.lunara-entity-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,158px),1fr))' ), 'The dossier seed prints on ' . $type . ' pages.' );
}
// Seed/final geometry is compared in the browser, not pinned to CSS text.
$GLOBALS['img_route'] = array( 'type' => 'journal' );
ob_start(); lunara_output_entity_geometry_css(); img_assert( '' === ob_get_clean(), 'The dossier seed stays off other singular routes.' );
img_assert( in_array( 'lunara-entity-geometry-css', lunara_rocket_preserve_front_door_css( array() ), true ) && in_array( 'lunara-home-hero-geometry-css', lunara_rocket_preserve_front_door_css( array() ), true ), 'Both seeds are excluded from used-CSS rewriting.' );

if ( in_array( '--entity-seed', $argv ?? array(), true ) ) { echo $entity; exit; }

echo "Image delivery runtime passed: {$checks} checks.\n";
