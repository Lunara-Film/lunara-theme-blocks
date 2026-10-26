<?php
/**
 * Runtime regression for safe Homepage Structure write-through.
 *
 * @package Lunara_Film
 */

define( 'ABSPATH', __DIR__ . '/' );

$lunara_home_test_content = '<!-- wp:core/heading -->Before<!-- /wp:core/heading -->' . "\n\n"
	. '<!-- wp:lunara/cinematic-hero {"overrideTitle":"Stored hero"} /-->' . "\n\n"
	. '<!-- wp:core/paragraph -->Keep C:\\\\Cinema between lanes.<!-- /wp:core/paragraph -->' . "\n\n"
	. '<!-- wp:lunara/latest-reviews {"source":"curated","count":8} /-->' . "\n\n"
	. '<!-- wp:core/paragraph -->Keep me after lanes.<!-- /wp:core/paragraph -->';
$lunara_home_test_update = null;
$lunara_home_test_update_error = false;
$lunara_home_test_wp_error_flag = null;
$lunara_home_test_has_blocks = false;

function lunara_home_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$lunara_home_test_options = array();
$lunara_home_test_can_edit = false;

function get_option( $key, $default = false ) {
	global $lunara_home_test_options;
	if ( 'show_on_front' === $key ) {
		return 'page';
	}
	if ( 'page_on_front' === $key ) {
		return 4055;
	}
	return array_key_exists( $key, $lunara_home_test_options ) ? $lunara_home_test_options[ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	global $lunara_home_test_options;
	$lunara_home_test_options[ $key ] = $value;
	return true;
}

function current_user_can( $cap ) {
	global $lunara_home_test_can_edit;
	return $lunara_home_test_can_edit && 'edit_theme_options' === $cap;
}

function add_action( ...$args ) {}

function get_post_field( $field, $post_id ) {
	global $lunara_home_test_content;
	return 'post_content' === $field && 4055 === $post_id ? $lunara_home_test_content : '';
}

function apply_filters( $hook, $value ) {
	return $value;
}

function parse_blocks( $content ) {
	return array(
		array( 'blockName' => 'core/heading', 'serialized' => '<!-- wp:core/heading -->Before<!-- /wp:core/heading -->' ),
		array( 'blockName' => 'lunara/cinematic-hero', 'serialized' => '<!-- wp:lunara/cinematic-hero {"overrideTitle":"Stored hero"} /-->' ),
		array( 'blockName' => 'core/paragraph', 'serialized' => '<!-- wp:core/paragraph -->Keep C:\\\\Cinema between lanes.<!-- /wp:core/paragraph -->' ),
		array( 'blockName' => 'lunara/latest-reviews', 'serialized' => '<!-- wp:lunara/latest-reviews {"source":"curated","count":8} /-->' ),
		array( 'blockName' => 'core/paragraph', 'serialized' => '<!-- wp:core/paragraph -->Keep me after lanes.<!-- /wp:core/paragraph -->' ),
	);
}

function serialize_block( $block ) {
	return $block['serialized'];
}

class WP_Error {
	private $code;
	public function __construct( $code ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }

function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : addslashes( (string) $value ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value ); }

function wp_update_post( $post, $wp_error = false ) {
	global $lunara_home_test_update, $lunara_home_test_update_error, $lunara_home_test_wp_error_flag, $lunara_home_test_content;
	$lunara_home_test_wp_error_flag = $wp_error;
	if ( $lunara_home_test_update_error ) {
		return $wp_error ? new WP_Error( 'injected_home_update_failure' ) : 0;
	}
	$lunara_home_test_update = wp_unslash( $post );
	$lunara_home_test_content = $lunara_home_test_update['post_content'];
	return $post['ID'];
}

function sanitize_text_field( $value ) {
	return (string) $value;
}

function has_block( $name, $content ) {
	global $lunara_home_test_has_blocks;
	return $lunara_home_test_has_blocks && false !== strpos( (string) $name, 'lunara/' );
}

function do_blocks( $content ) {
	return $content;
}

function render_block( $block ) {
	return '<render>' . $block['serialized'] . '</render>';
}

require dirname( __DIR__ ) . '/inc/home-blocks.php';

$composed = lunara_compose_home_section_blocks( $lunara_home_test_content, array( 'latest-reviews', 'hero' ) );
lunara_home_test_assert( is_string( $composed ), 'The pure Homepage composer must return candidate content without writing.' );
lunara_home_test_assert( null === $lunara_home_test_update, 'The pure Homepage composer must perform no post write.' );

$changed = lunara_write_home_section_blocks( array( 'latest-reviews', 'hero' ) );
lunara_home_test_assert( true === $changed, 'A requested lane reorder must update the front page.' );
lunara_home_test_assert( is_array( $lunara_home_test_update ), 'Homepage Structure must issue one explicit update on save.' );
lunara_home_test_assert( true === $lunara_home_test_wp_error_flag, 'Homepage Structure must request WP_Error propagation from wp_update_post.' );

$expected = '<!-- wp:core/heading -->Before<!-- /wp:core/heading -->' . "\n\n"
	. '<!-- wp:lunara/latest-reviews {"source":"curated","count":8} /-->' . "\n\n"
	. '<!-- wp:core/paragraph -->Keep C:\\\\Cinema between lanes.<!-- /wp:core/paragraph -->' . "\n\n"
	. '<!-- wp:lunara/cinematic-hero {"overrideTitle":"Stored hero"} /-->' . "\n\n"
	. '<!-- wp:core/paragraph -->Keep me after lanes.<!-- /wp:core/paragraph -->';

lunara_home_test_assert( $expected === $lunara_home_test_update['post_content'], 'Reordering canonical lanes must preserve every unknown block in its original slot and retain canonical attributes.' );

$lunara_home_test_content = "\n" . $expected . "\n";
$lunara_home_test_update = null;
$byte_change = lunara_write_home_section_blocks( array( 'latest-reviews', 'hero' ) );
lunara_home_test_assert( true === $byte_change && $expected === $lunara_home_test_update['post_content'], 'Homepage writer no-op detection must use byte equality, not trim-equivalence that hides leading/trailing-byte changes.' );

$lunara_home_test_update_error = true;
$failure = lunara_write_home_section_blocks( array( 'hero', 'latest-reviews' ) );
lunara_home_test_assert( is_wp_error( $failure ) && 'injected_home_update_failure' === $failure->get_error_code(), 'Homepage Structure must propagate the exact wp_update_post failure.' );

$lunara_home_test_has_blocks = true;
$sync_failure = lunara_sync_home_section_blocks_from_settings();
lunara_home_test_assert( is_wp_error( $sync_failure ) && 'injected_home_update_failure' === $sync_failure->get_error_code(), 'Legacy Homepage synchronization must propagate the exact writer error to its Control Desk callers.' );

/*
 * The block map carries the Direction D slots, and the one-time recomposition
 * (3.2.122) writes their blocks into a block-mode Home page exactly once.
 */
$map = lunara_home_section_block_map();
lunara_home_test_assert( 'lunara/running-order' === ( $map['featured'] ?? '' ) && 'lunara/ledger-band' === ( $map['ledger'] ?? '' ) && 'lunara/pairing-desk' === ( $map['pairing-desk'] ?? '' ), 'The block map maps featured, ledger and pairing-desk to their Direction D blocks.' );

$lunara_home_test_update_error = false;
$lunara_home_test_update       = null;
$lunara_home_test_has_blocks   = true;

// Not a theme editor: nothing happens, nothing is recorded.
$lunara_home_test_can_edit = false;
lunara_home_test_assert( false === lunara_home_migrate_block_composition() && null === $lunara_home_test_update && ! isset( $lunara_home_test_options['lunara_home_composition_revision'] ), 'The recomposition waits for a theme editor and records nothing before one arrives.' );

// A registry-mode Home is left alone, and the revision is recorded so it is not retried.
$lunara_home_test_can_edit   = true;
$lunara_home_test_has_blocks = false;
lunara_home_test_assert( false === lunara_home_migrate_block_composition() && null === $lunara_home_test_update && lunara_home_composition_revision() === $lunara_home_test_options['lunara_home_composition_revision'], 'A registry-mode Home is not converted; the revision is recorded.' );

// A block-mode Home gets the new blocks written in, once.
$lunara_home_test_options    = array();
$lunara_home_test_has_blocks = true;
$lunara_home_test_content    = $expected;
$migrated = lunara_home_migrate_block_composition();
lunara_home_test_assert( true === $migrated && is_array( $lunara_home_test_update ), 'A block-mode Home is recomposed from the saved order and visibility.' );
$written = (string) $lunara_home_test_update['post_content'];
lunara_home_test_assert( false !== strpos( $written, '<!-- wp:lunara/running-order /-->' ) && false !== strpos( $written, '<!-- wp:lunara/ledger-band /-->' ) && false !== strpos( $written, '<!-- wp:lunara/pairing-desk /-->' ), 'The recomposition writes the Running Order, Ledger band and pairing-desk blocks into the page.' );
lunara_home_test_assert( false !== strpos( $written, 'Keep me after lanes.' ) && false !== strpos( $written, 'Keep C:\\\\Cinema between lanes.' ) && false !== strpos( $written, '{"overrideTitle":"Stored hero"}' ), 'Editorial content and stored block attributes survive the recomposition.' );
lunara_home_test_assert( lunara_home_composition_revision() === $lunara_home_test_options['lunara_home_composition_revision'], 'The composition revision is recorded after the write.' );

$lunara_home_test_update = null;
lunara_home_test_assert( false === lunara_home_migrate_block_composition() && null === $lunara_home_test_update, 'The recomposition does not run twice for the same revision.' );

// A write failure is surfaced and the revision is not recorded, so the next admin load retries.
$lunara_home_test_options      = array();
$lunara_home_test_content      = $expected;
$lunara_home_test_update_error = true;
$failed = lunara_home_migrate_block_composition();
lunara_home_test_assert( is_wp_error( $failed ) && ! isset( $lunara_home_test_options['lunara_home_composition_revision'] ), 'A failed recomposition is reported and stays due.' );

/*
 * Block mode renders the page's blocks one at a time (3.2.137), so
 * front-page.php can mark Home's alternating panels on them: every top-level
 * block, in page order, rendered by render_block() as do_blocks() renders it,
 * a Lunara section block with its registry slug and any other block with ''.
 */
$lunara_home_test_content = $expected;
$sections = lunara_home_block_composition_sections( array( 'lunara/cinematic-hero' ) );
lunara_home_test_assert( array( '', '', 'latest-reviews', '' ) === array_column( $sections, 'slug' ), 'Block sections leave the excluded hero out and name only Lunara section blocks by slug.' );
lunara_home_test_assert( '<render><!-- wp:core/heading -->Before<!-- /wp:core/heading --></render>' === $sections[0]['html'] && '<render><!-- wp:lunara/latest-reviews {"source":"curated","count":8} /--></render>' === $sections[2]['html'], 'Each block section is that block rendered on its own, in page order.' );
$all = lunara_home_block_composition_sections();
lunara_home_test_assert( 5 === count( $all ) && 'hero' === $all[1]['slug'] && 'Keep me after lanes.' === trim( strip_tags( str_replace( array( '<!-- wp:core/paragraph -->', '<!-- /wp:core/paragraph -->' ), '', $all[4]['html'] ) ) ), 'Without exclusions every block renders, the hero block named hero.' );
$lunara_home_test_content = "  \n";
lunara_home_test_assert( array() === lunara_home_block_composition_sections(), 'An empty Home page has no block sections.' );

/*
 * The saved Section Order decides the printed order in block mode (3.2.138):
 * section blocks by their place in the map, an unmapped slug after them,
 * any other block first, and equal places in page order.
 */
$order_map = array( 'hero' => 1, 'pairing-desk' => 2, 'dispatch' => 3, 'ledger' => 4, 'featured' => 5 );
$stored    = array(
	array( 'slug' => 'pairing-desk', 'html' => 'P' ),
	array( 'slug' => 'featured', 'html' => 'F' ),
	array( 'slug' => '', 'html' => 'note' ),
	array( 'slug' => 'ledger', 'html' => 'L' ),
	array( 'slug' => 'oscar-facts', 'html' => 'X' ),
	array( 'slug' => 'dispatch', 'html' => 'J' ),
	array( 'slug' => '', 'html' => 'space' ),
);
lunara_home_test_assert( array( 'note', 'space', 'P', 'J', 'L', 'F', 'X' ) === array_column( lunara_home_sort_block_sections( $stored, $order_map ), 'html' ), 'Blocks print in the saved order: other blocks first, then the map, then unmapped slugs.' );
lunara_home_test_assert( array( 'P', 'F' ) === array_column( lunara_home_sort_block_sections( array( array( 'slug' => 'pairing-desk', 'html' => 'P' ), array( 'slug' => 'featured', 'html' => 'F' ) ), array() ), 'html' ), 'Without a map, the stored order holds.' );

echo "home-block composition runtime: all assertions passed.\n";
