<?php
/** Old saved shape choices must not change current public presentation. */
define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function __( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_attr( $text ); }
function esc_attr__( $text, $domain = '' ) { return esc_attr( $text ); }
function esc_attr_e( $text, $domain = '' ) { echo esc_attr( $text ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#[a-f0-9]{3,6}$/i', $value ) ? $value : null; }
function get_theme_mod( $key, $default = false ) { return $GLOBALS['saved_mods'][$key] ?? $default; }
function get_post_mime_type( $id ) { return 'image/jpeg'; }
class WP_Post { public $post_type = 'attachment'; }
function get_post( $id ) { return new WP_Post(); }
function wp_get_attachment_image_url( $id, $size ) { return "https://fixture.test/image-$id.jpg"; }
function wp_get_attachment_image( $id, ...$args ) { return '<img class="lunara-media-image" src="' . wp_get_attachment_image_url( $id, 'large' ) . '" alt="Film still">'; }
function wp_get_attachment_caption( $id ) { return "Image credit $id"; }
function get_post_meta( $id, $key, $single = false ) { return 'Film still'; }
// Unrelated typography and section registries are fixed at the harness boundary.
function lunara_sanitize_font_stack( $value ) { return $value; }
function lunara_hex_to_rgba( $value, $alpha ) { return 'rgba(201,169,97,.24)'; }
function lunara_get_home_section_order_map() { return array(); }
function lunara_get_home_section_slugs() { return array(); }
function lunara_get_registry_slugs( $registry ) { return array(); }
function lunara_get_reviews_archive_section_registry() { return array(); }
function lunara_get_news_archive_live_section_registry() { return array(); }
function lunara_get_news_archive_empty_section_registry() { return array(); }
function lunara_get_oscars_portal_section_registry() { return array(); }
require dirname( __DIR__ ) . '/inc/customizer.php';
require dirname( __DIR__ ) . '/inc/curated-media.php';

function geometry_check( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}
$baseline = null;
foreach ( array( 0, 28, 48, 999 ) as $radius ) {
    $GLOBALS['saved_mods'] = array( 'lunara_surface_radius' => $radius, 'lunara_body_font_size' => 18 );
    $saved = $GLOBALS['saved_mods'];
    ob_start(); lunara_output_runtime_customizer_css(); $css = ob_get_clean();
    $baseline = $baseline ?? $css;
    geometry_check( $baseline === $css, 'Retired radius cannot change runtime output.' );
    geometry_check( $saved === $GLOBALS['saved_mods'], 'Rendering preserves stored settings.' );
}
$GLOBALS['saved_mods']['lunara_body_font_size'] = 20;
ob_start(); lunara_output_runtime_customizer_css(); $changed = ob_get_clean();
geometry_check( $changed !== $baseline, 'Supported typography choices still affect output.' );
foreach ( array( 'gallery', 'slider', 'carousel' ) as $display ) {
    $attrs = array( 'ids' => array( 12, 24 ), 'display' => $display, 'showCaptions' => true, 'linkTo' => 'media' );
    $default = lunara_render_media_showcase( $attrs );
    foreach ( array( true, false ) as $rounded ) {
        $saved = $attrs + array( 'rounded' => $rounded );
        geometry_check( $default === lunara_render_media_showcase( $saved ), 'Legacy shape choice leaves ' . $display . ' output unchanged.' );
    }
    $dom = new DOMDocument();
    @$dom->loadHTML( $default );
    $xpath = new DOMXPath( $dom );
    geometry_check( $xpath->query( '//*[contains(concat(" ",normalize-space(@class)," ")," is-rounded ")]' )->length === 0, 'No retired rounded modifier is rendered.' );
    if ( 'carousel' !== $display ) {
        geometry_check( $dom->getElementsByTagName( 'img' )->length === 2, 'Every selected image remains.' );
    } else {
        geometry_check( $xpath->query( '//*[contains(concat(" ",normalize-space(@class)," ")," lunara-carousel-slide ")]' )->length === 2, 'Every selected carousel slide remains.' );
    }
    geometry_check( $xpath->query( '//a[starts-with(@href,"https://fixture.test/image-")]' )->length === 2, 'Every selected destination remains.' );
}
echo "Public geometry saved-setting runtime passed.\n";
