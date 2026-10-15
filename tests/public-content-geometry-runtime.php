<?php
/** Real geometry adapters; database/settings are immutable fixture inputs. */
define( 'ABSPATH', __DIR__ );
define( 'WP_DEBUG', true );
function add_filter( ...$args ) { $GLOBALS['geometry_hooks'][$args[0]] = $args; }
function add_action( ...$args ) { $GLOBALS['geometry_hooks'][$args[0]] = $args; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ); }
function wp_kses_uri_attributes() { return array( 'src', 'href' ); }
function _doing_it_wrong( $fn, $message, $version ) { throw new RuntimeException( $message ); }
function __( $text ) { return $text; }
$native = getenv( 'LUNARA_NATIVE_HTML_API' );
if ( $native ) {
    foreach ( array( 'class-wp-token-map.php', 'html5-named-character-references.php', 'class-wp-html-attribute-token.php', 'class-wp-html-span.php', 'class-wp-html-doctype-info.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-tag-processor.php' ) as $file ) { require $native . '/' . $file; }
} else {
    // API seam only: PHP DOM supplies tag/attribute operations in portable CI.
    class WP_HTML_Tag_Processor {
        private $dom; private $nodes; private $index = -1;
        function __construct( $html ) { $this->dom = new DOMDocument(); @$this->dom->loadHTML( '<!doctype html><html><body>' . $html . '</body></html>' ); $this->nodes = $this->dom->getElementsByTagName( '*' ); }
        function next_tag() { return ++$this->index < $this->nodes->length; }
        function get_attribute( $name ) { $el = $this->nodes->item( $this->index ); return $el->hasAttribute( $name ) ? $el->getAttribute( $name ) : null; }
        function set_attribute( $name, $value ) { $this->nodes->item( $this->index )->setAttribute( $name, $value ); }
        function remove_class( $class ) { $value = $this->get_attribute( 'class' ); if ( null !== $value ) { $this->set_attribute( 'class', implode( ' ', array_diff( preg_split( '/\s+/', $value ), array( $class ) ) ) ); } }
        function get_updated_html() { $html = ''; foreach ( $this->dom->getElementsByTagName( 'body' )->item( 0 )->childNodes as $node ) { $html .= $this->dom->saveHTML( $node ); } return $html; }
    }
}
require dirname( __DIR__ ) . '/inc/public-geometry.php';
function content_geometry_check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
$cases = array(
    'border-radius:24px;color:red' => 'border-radius:0;color:red',
    'border-top-left-radius:20px !important; padding: 8px' => 'border-top-left-radius:0 !important; padding: 8px',
    'border-start-end-radius:var(--old);border-end-start-radius:50%' => 'border-start-end-radius:0;border-end-start-radius:0',
    'background:url("data:image/svg+xml;a;border-radius:20px"); border-radius: 9px;' => 'background:url("data:image/svg+xml;a;border-radius:20px"); border-radius:0;',
    '--label:"border-radius:10px;";color:red' => '--label:"border-radius:10px;";color:red',
    '/* old; corner */border-radius:10px;object-position:30% 70%' => '/* old; corner */border-radius:0;object-position:30% 70%',
    'color:red;--label:"incomplete radius' => 'color:red;--label:"incomplete radius',
);
foreach ( $cases as $input => $expected ) { content_geometry_check( lunara_square_inline_geometry( $input ) === $expected, 'Inline property adaptation: ' . $input ); }
$source = '<div class="wp-block-group" style="border-radius:28px;background:#123456"><figure class="wp-block-image is-style-rounded"><img src="poster.jpg" alt="A film" style="border-radius:50%;object-position:30% 70%"></figure><a class="wp-block-button__link" href="/journal/" style="border-radius:999px;padding:12px">Read the whole story</a><span class="lunara-pair-thread-node" style="border-radius:50%">Meaningful node</span></div>';
$before = $source;
$rendered = lunara_render_square_content( $source );
content_geometry_check( $source === $before, 'Stored source is unchanged.' );
$dom = new DOMDocument(); @$dom->loadHTML( $rendered ); $xpath = new DOMXPath( $dom );
content_geometry_check( $xpath->query( '//figure[contains(@class,"is-style-rounded")]' )->length === 0, 'Retired image shape is not rendered.' );
content_geometry_check( $xpath->query( '//img[@src="poster.jpg" and @alt="A film" and contains(@style,"object-position:30% 70%")]' )->length === 1, 'Artwork identity and focal point survive.' );
content_geometry_check( $xpath->query( '//a[@href="/journal/" and contains(@style,"border-radius:0") and text()="Read the whole story"]' )->length === 1, 'Link, text, and square geometry survive.' );
content_geometry_check( $xpath->query( '//span[contains(@style,"border-radius:50%")]' )->length === 1, 'Meaningful diagram node remains circular.' );
content_geometry_check( lunara_render_square_content( $rendered ) === $rendered, 'Repeated block/content filtering is idempotent.' );
$saved = array( 'version' => 2, 'settings' => array( 'blocks' => array( 'core/group' => array( 'border' => array( 'radius' => true ) ) ) ), 'styles' => array( 'blocks' => array( 'core/group' => array( 'border' => array( 'radius' => array( 'topLeft' => '24px' ), 'color' => '#123456' ), 'spacing' => array( 'padding' => '20px' ) ) ) ) );
$data = new class( $saved ) { public $data; function __construct( $data ) { $this->data = $data; } function get_data() { return $this->data; } function update_with( $update ) { $this->data = array_replace_recursive( $this->data, $update ); return $this; } };
$result = lunara_square_global_styles( $data )->get_data();
content_geometry_check( '0' === $result['styles']['blocks']['core/group']['border']['radius'] && false === $result['settings']['blocks']['core/group']['border']['radius'], 'Saved global style and per-block control cannot restore rounding.' );
content_geometry_check( '#123456' === $result['styles']['blocks']['core/group']['border']['color'] && '20px' === $result['styles']['blocks']['core/group']['spacing']['padding'], 'Other styling survives.' );
$registration = lunara_square_block_registration( array( 'styles' => array( array( 'name' => 'default' ), array( 'name' => 'rounded' ) ), 'supports' => array( '__experimentalBorder' => array( 'radius' => true, 'color' => true ) ) ), 'core/image' );
content_geometry_check( count( $registration['styles'] ) === 1 && false === $registration['supports']['__experimentalBorder']['radius'] && true === $registration['supports']['__experimentalBorder']['color'], 'Only the obsolete shape choice retires.' );
content_geometry_check( isset( $GLOBALS['geometry_hooks']['render_block'], $GLOBALS['geometry_hooks']['the_content'], $GLOBALS['geometry_hooks']['wp_theme_json_data_user'] ), 'All content entry points are registered.' );
if ( in_array( '--fixture', $argv ?? array(), true ) ) { echo $rendered; } else { echo 'Public content geometry passed (' . ( $native ? 'native WordPress HTML API' : 'portable DOM API seam' ) . ").\n"; }
