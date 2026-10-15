<?php
/** Behavioral delivery/config contract; --manifest feeds the browser fixture. */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$GLOBALS['nav_test'] = array( 'admin' => false, 'feed' => false, 'preview' => false, 'logged' => false, 'customize' => false, 'enqueued' => array(), 'enabled' => true );
function add_action() {}
function add_filter() {}
function apply_filters( $name, $value ) { return 'lunara_navigation_enabled' === $name && null !== $GLOBALS['nav_test']['enabled'] ? $GLOBALS['nav_test']['enabled'] : $value; }
function is_admin() { return $GLOBALS['nav_test']['admin']; }
function is_feed() { return $GLOBALS['nav_test']['feed']; }
function is_preview() { return $GLOBALS['nav_test']['preview']; }
function is_user_logged_in() { return $GLOBALS['nav_test']['logged']; }
function is_customize_preview() { return $GLOBALS['nav_test']['customize']; }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function home_url( $suffix ) { return 'https://navigation.test' . $suffix; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_scripts() { return (object) array( 'registered' => array( 'lunara-live-search' => (object) array( 'extra' => array( 'data' => 'var LUNARA_LIVE_SEARCH = {"endpoint":"/wp-json/search","label":"</script>"};' ) ) ) ); }
function lunara_resolve_theme_asset( $file ) { return array( 'path' => ABSPATH . $file, 'uri' => '/' . $file ); }
function lunara_theme_asset_version( $file ) { return filemtime( $file ); }
function wp_enqueue_script( $handle, $uri, $deps, $version, $footer ) { $GLOBALS['nav_test']['enqueued'][ $handle ] = compact( 'uri', 'deps', 'version', 'footer' ); }
function wp_script_add_data( $handle, $name, $value ) { $GLOBALS['nav_test']['enqueued'][ $handle ][ $name ] = $value; }
require ABSPATH . 'inc/navigation-runtime.php';
ob_start(); lunara_navigation_manifest(); $html = ob_get_clean();
preg_match( '~<script[^>]*>(.*)</script>~s', $html, $match );
$manifest = json_decode( $match[1], true, 512, JSON_THROW_ON_ERROR );
if ( in_array( '--manifest', $argv, true ) ) { echo json_encode( $manifest ); exit; }
$checks = 0;
function verify( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { throw new RuntimeException( $message ); } }
verify( $manifest['config']['LUNARA_LIVE_SEARCH']['label'] === '</script>', 'Typed config preserves text without executing JS.' );
verify( substr_count( $html, '</script>' ) === 1, 'Typed config escapes HTML delimiters.' );
verify( count( $manifest['components'] ) > 10, 'All audited public page components are described.' );
foreach ( $manifest['assets'] as $handle => $scope ) {
    verify( in_array( $scope, array( 'persistent', 'library', 'page' ), true ), 'Asset scopes are explicit.' );
    if ( 'page' === $scope ) { verify( ! empty( $manifest['components'][ $handle ] ), 'Page assets name their registration.' ); }
}
$unknown = '<script src="/other.js"></script>';
verify( lunara_navigation_script_tag( $unknown, 'other' ) === $unknown, 'Unaudited plugins never opt in automatically.' );
verify( strpos( lunara_navigation_script_tag( $unknown, 'lunara-public-runtime' ), 'data-lunara-asset="lunara-public-runtime"' ) !== false, 'Audited handle gets explicit provenance.' );
lunara_navigation_enqueue();
verify( false === $GLOBALS['nav_test']['enqueued']['lunara-page-lifecycle']['footer'], 'Lifecycle arrives before page component scripts.' );
verify( array( 'lunara-page-lifecycle' ) === $GLOBALS['nav_test']['enqueued']['lunara-navigation']['deps'], 'Navigation requires the lifecycle registry.' );
verify( 'defer' === $GLOBALS['nav_test']['enqueued']['lunara-navigation']['strategy'], 'Navigation does not block HTML parsing.' );
$GLOBALS['nav_test']['enabled'] = null;
$GLOBALS['nav_test']['enqueued'] = array(); lunara_navigation_enqueue();
ob_start(); lunara_navigation_manifest(); $default_manifest = ob_get_clean();
verify( array( 'lunara-page-lifecycle' ) === array_keys( $GLOBALS['nav_test']['enqueued'] ), 'Native default loads only the lightweight lifecycle, with zero Swup download.' );
verify( '' === $default_manifest && ! lunara_navigation_enabled(), 'Native default emits no navigation manifest.' );
verify( lunara_navigation_keep_assets_separate( true, 'lunara-public-runtime' ), 'Native default preserves existing component optimizer behavior.' );
verify( ! lunara_navigation_keep_assets_separate( true, 'lunara-page-lifecycle' ), 'The early registry always preserves its execution order.' );
$GLOBALS['nav_test']['enabled'] = true;
foreach ( array( 'admin', 'feed', 'preview', 'logged', 'customize' ) as $flag ) {
    $GLOBALS['nav_test'][ $flag ] = true;
    $GLOBALS['nav_test']['enqueued'] = array(); lunara_navigation_enqueue();
    ob_start(); lunara_navigation_manifest(); $disabled = ob_get_clean();
    verify( empty( $GLOBALS['nav_test']['enqueued'] ) && '' === $disabled, $flag . ' keeps ordinary navigation.' );
    $GLOBALS['nav_test'][ $flag ] = false;
}
echo json_encode( array( 'test' => 'navigation-runtime', 'checks' => $checks ) ) . PHP_EOL;
