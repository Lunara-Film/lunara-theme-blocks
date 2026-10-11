<?php
/** Audited public navigation delivery; ordinary WordPress navigation is the fallback. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function lunara_navigation_is_public() {
    return ! is_admin() && ! is_feed() && ! is_preview()
        && ! is_user_logged_in()
        && ! ( function_exists( 'is_customize_preview' ) && is_customize_preview() );
}

/** Native navigation is the production default until third-party owners opt in. */
function lunara_navigation_enabled() {
    return lunara_navigation_is_public() && (bool) apply_filters( 'lunara_navigation_enabled', false );
}

/** Assets may opt in only after their mount/disposal contract is audited. */
function lunara_navigation_asset_contract() {
    return apply_filters( 'lunara_navigation_asset_contract', array(
        'lunara-page-lifecycle' => 'persistent',
        'lunara-navigation' => 'persistent',
        'lunara-header' => 'persistent',
        'lunara-live-search' => 'persistent',
        'lunara-public-runtime' => 'page',
        'lunara-home-runtime' => 'page',
        'lunara-scroll-carousel' => 'page',
        'lunara-home-carousels' => 'page',
        'lunara-hero-carousel' => 'page',
        'lunara-home-splide-pilot' => 'page',
        'lunara-cinematic-home' => 'page',
        'lunara-dynamic-rails' => 'page',
        'lunara-immersive-read' => 'page',
        'lunara-debrief-orbit' => 'page',
        'lunara-pair-thread' => 'page',
        'lunara-carousel' => 'page',
        'lunara-media-showcase' => 'page',
        'lunara-splide' => 'library',
        'aat-ledger-explorer' => 'page',
        'aat-ledger-motion' => 'page',
        'aat-ceremony-dossier' => 'page',
        'jquery' => 'library',
        'jquery-core' => 'library',
        'jquery-migrate' => 'library',
        'wp-hooks' => 'library',
        'wp-i18n' => 'library',
        'wp-dom-ready' => 'library',
        'wp-a11y' => 'library',
    ) );
}

function lunara_navigation_components() {
    return apply_filters( 'lunara_navigation_components', array(
        'lunara-public-runtime' => 'public-runtime',
        'lunara-home-runtime' => 'home-runtime',
        'lunara-scroll-carousel' => 'scroll-carousel',
        'lunara-home-carousels' => 'home-carousels',
        'lunara-hero-carousel' => 'hero-carousels',
        'lunara-home-splide-pilot' => 'splide-pilot',
        'lunara-cinematic-home' => 'cinematic-home',
        'lunara-dynamic-rails' => 'dynamic-rails',
        'lunara-immersive-read' => 'immersive-read',
        'lunara-debrief-orbit' => 'debrief-orbit',
        'lunara-pair-thread' => 'pair-thread',
        'lunara-carousel' => 'carousel',
        'lunara-media-showcase' => 'media-showcase',
        'aat-ledger-explorer' => 'ledger-explorer',
        'aat-ledger-motion' => 'ledger-motion',
        'aat-ceremony-dossier' => 'ceremony-dossier',
    ) );
}

function lunara_navigation_enqueue() {
    if ( ! lunara_navigation_is_public() ) { return; }
    $lifecycle = lunara_resolve_theme_asset( 'assets/js/lunara-page-lifecycle.js' );
    if ( empty( $lifecycle['path'] ) ) { return; }
    // The registry exists before classic/deferred component scripts execute.
    wp_enqueue_script( 'lunara-page-lifecycle', $lifecycle['uri'], array(), lunara_theme_asset_version( $lifecycle['path'] ), false );
    if ( ! lunara_navigation_enabled() ) { return; }
    $navigation = lunara_resolve_theme_asset( 'assets/js/lunara-navigation.bundle.js' );
    if ( empty( $navigation['path'] ) ) { return; }
    wp_enqueue_script( 'lunara-navigation', $navigation['uri'], array( 'lunara-page-lifecycle' ), lunara_theme_asset_version( $navigation['path'] ), true );
    wp_script_add_data( 'lunara-navigation', 'strategy', 'defer' );
}
add_action( 'wp_enqueue_scripts', 'lunara_navigation_enqueue', 5 );

function lunara_navigation_script_tag( $tag, $handle ) {
    $contract = lunara_navigation_asset_contract();
    if ( ! isset( $contract[ $handle ] ) || ! lunara_navigation_is_public() || ( 'lunara-page-lifecycle' !== $handle && ! lunara_navigation_enabled() ) ) { return $tag; }
    return preg_replace( '/<script\b/', '<script data-jetpack-boost="ignore" data-lunara-asset="' . esc_attr( $handle ) . '" data-lunara-scope="' . esc_attr( $contract[ $handle ] ) . '"', $tag, 1 );
}
add_filter( 'script_loader_tag', 'lunara_navigation_script_tag', 20, 2 );

/** Preserve handle provenance and the registry's head ordering in optimizers. */
function lunara_navigation_keep_assets_separate( $concat, $handle ) {
    $contract = lunara_navigation_asset_contract();
    return lunara_navigation_is_public() && isset( $contract[ $handle ] ) && ( 'lunara-page-lifecycle' === $handle || lunara_navigation_enabled() ) ? false : $concat;
}
add_filter( 'js_do_concat', 'lunara_navigation_keep_assets_separate', 10, 2 );

/** Read only known JSON localization values; never evaluate incoming JavaScript. */
function lunara_navigation_page_config() {
    $config = array();
    $scripts = wp_scripts();
    $source = isset( $scripts->registered['lunara-live-search']->extra['data'] ) ? $scripts->registered['lunara-live-search']->extra['data'] : '';
    if ( is_string( $source ) && preg_match( '/\bvar\s+LUNARA_LIVE_SEARCH\s*=\s*(\{[^\r\n]*\})\s*;/', $source, $matches ) ) {
        $decoded = json_decode( $matches[1], true );
        if ( is_array( $decoded ) ) { $config['LUNARA_LIVE_SEARCH'] = $decoded; }
    }
    return $config;
}

function lunara_navigation_manifest() {
    if ( ! lunara_navigation_enabled() ) { return; }
    $manifest = array(
        'version' => 1,
        'enabled' => true,
        'home' => home_url( '/' ),
        'assets' => lunara_navigation_asset_contract(),
        'components' => lunara_navigation_components(),
        'config' => lunara_navigation_page_config(),
    );
    echo '<script type="application/json" id="lunara-navigation-manifest">' . wp_json_encode( $manifest, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with HTML delimiters escaped.
}
add_action( 'wp_footer', 'lunara_navigation_manifest', 99 );

function lunara_navigation_rocket_exclusions( $excluded ) {
    $excluded = is_array( $excluded ) ? $excluded : array();
    return array_values( array_unique( array_merge( $excluded, array( 'lunara-page-lifecycle.js', 'lunara-navigation.bundle.js' ) ) ) );
}
add_filter( 'rocket_delay_js_exclusions', 'lunara_navigation_rocket_exclusions' );
add_filter( 'rocket_exclude_defer_js', 'lunara_navigation_rocket_exclusions' );
