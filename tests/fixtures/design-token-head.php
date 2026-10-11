<?php
/** CLI adapter for the real shared font/palette wp_head output. */
define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
function __( $text, $domain = '' ) { return $text; }
function add_action() {}
function add_filter() {}
function is_admin() { return false; }
function get_option( $name, $default = false ) {
    global $argv;
    if ( 'lunara_design_tokens' !== $name || empty( $argv[1] ) ) return $default;
    $saved = json_decode( base64_decode( $argv[1] ), true );
    return is_array( $saved ) ? $saved : $default;
}
function get_theme_mod( $name, $default = false ) { return $default; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $value ) ? $value : null; }
require dirname( __DIR__, 2 ) . '/inc/design-tokens.php';
lunara_design_tokens_output_css();
