<?php
/**
 * Behavior contract for the Debrief index cache (3.2.144, inc/debrief-method.php).
 *
 * A rebuild of the Debrief page's pairing index took 7 to 9 s of a visitor's
 * request on the live site. The checks:
 *
 * - the cached index serves while it is there;
 * - with no cache and no usable snapshot (the first build, a new index
 *   version), the page builds at once;
 * - with no cache but a snapshot, a visitor gets the snapshot, one background
 *   rebuild is scheduled, and the event rebuilds both;
 * - someone who can edit posts gets a fresh build at once;
 * - a snapshot older than a day is rebuilt at once for anyone;
 * - a review or film change drops the cache, keeps the snapshot and asks for
 *   a background rebuild.
 *
 * Run: php tests/debrief-index-cache-runtime.php
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$checks = 0;
function lunara_test_assert( $condition, string $message ): void {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

class WP_Post {
	public $ID;
	public $post_type;
	public function __construct( int $id, string $type ) { $this->ID = $id; $this->post_type = $type; }
}

$GLOBALS['t'] = array( 'actions' => array(), 'transients' => array(), 'options' => array(), 'events' => array(), 'builds' => 0, 'editor' => false );

function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['t']['actions'][ $hook ][] = $callback; }
function get_transient( $key ) { return $GLOBALS['t']['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['t']['transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['t']['transients'][ $key ] ); return true; }
function get_option( $key, $default = false ) { return $GLOBALS['t']['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['t']['options'][ $key ] = $value; $GLOBALS['t']['autoload'][ $key ] = $autoload; return true; }
function current_user_can( $capability ) { return 'edit_posts' === $capability && $GLOBALS['t']['editor']; }
function wp_next_scheduled( $hook ) { return $GLOBALS['t']['events'][ $hook ] ?? false; }
function wp_schedule_single_event( $time, $hook ) { $GLOBALS['t']['events'][ $hook ] = $time; return true; }
function get_post_type( $id ) { return 'review'; }

// The build itself is covered by tests/debrief-method-runtime.php; here it
// only counts, and returns an index of the current shape.
function lunara_debrief_method_build_index() {
	++$GLOBALS['t']['builds'];
	return array( 'version' => 3, 'films' => array(), 'recent' => array(), 'reviews_debrief' => $GLOBALS['t']['builds'], 'pairings_total' => 0, 'unique_films' => 0 );
}

require dirname( __DIR__ ) . '/inc/debrief-method.php';

/** The page reads the index; return what it got and how many builds ran. */
function lunara_test_read(): array {
	$before = $GLOBALS['t']['builds'];
	$index  = lunara_debrief_method_index();
	return array( $index['reviews_debrief'], $GLOBALS['t']['builds'] - $before );
}
function lunara_test_run_events(): void {
	if ( ! empty( $GLOBALS['t']['events'][ LUNARA_DEBRIEF_INDEX_REBUILD_HOOK ] ) ) {
		unset( $GLOBALS['t']['events'][ LUNARA_DEBRIEF_INDEX_REBUILD_HOOK ] );
		foreach ( $GLOBALS['t']['actions'][ LUNARA_DEBRIEF_INDEX_REBUILD_HOOK ] ?? array() as $callback ) {
			call_user_func( $callback );
		}
	}
}

// The first build ever: nothing to serve, so the page builds at once.
list( $served, $built ) = lunara_test_read();
lunara_test_assert( 1 === $built && 1 === $served, 'with no cache and no snapshot, the page builds at once' );
$snapshot = get_option( LUNARA_DEBRIEF_INDEX_SNAPSHOT_OPTION );
lunara_test_assert( is_array( $snapshot ) && 1 === $snapshot['index']['reviews_debrief'] && is_int( $snapshot['built_at'] ) && false === $GLOBALS['t']['autoload'][ LUNARA_DEBRIEF_INDEX_SNAPSHOT_OPTION ], 'a build keeps a snapshot with its build time, not autoloaded' );

// The cache serves.
list( $served, $built ) = lunara_test_read();
lunara_test_assert( 0 === $built && 1 === $served, 'the cached index serves without a build' );

// A review changes: the cache drops, the snapshot stays, a rebuild is asked for.
lunara_debrief_method_flush_index( 110, new WP_Post( 110, 'review' ) );
lunara_test_assert( false === get_transient( LUNARA_DEBRIEF_INDEX_CACHE_KEY ) && is_array( get_option( LUNARA_DEBRIEF_INDEX_SNAPSHOT_OPTION ) ), 'a review change drops the cache and keeps the snapshot' );
lunara_test_assert( isset( $GLOBALS['t']['events'][ LUNARA_DEBRIEF_INDEX_REBUILD_HOOK ] ), 'a review change asks for a background rebuild' );

// A visitor meanwhile: the snapshot, no build in the request, one event.
unset( $GLOBALS['t']['events'][ LUNARA_DEBRIEF_INDEX_REBUILD_HOOK ] );
list( $served, $built ) = lunara_test_read();
lunara_test_assert( 0 === $built && 1 === $served, 'a visitor gets the last build, with no build in the request' );
$due = $GLOBALS['t']['events'][ LUNARA_DEBRIEF_INDEX_REBUILD_HOOK ] ?? 0;
list( , $built ) = lunara_test_read();
lunara_test_assert( 0 === $built && $due === ( $GLOBALS['t']['events'][ LUNARA_DEBRIEF_INDEX_REBUILD_HOOK ] ?? -1 ) && $due > 0, 'a second visitor does not schedule a second rebuild' );

// The background event rebuilds the cache and the snapshot.
lunara_test_run_events();
lunara_test_assert( 2 === $GLOBALS['t']['builds'] && 2 === get_transient( LUNARA_DEBRIEF_INDEX_CACHE_KEY )['reviews_debrief'] && 2 === get_option( LUNARA_DEBRIEF_INDEX_SNAPSHOT_OPTION )['index']['reviews_debrief'], 'the background event rebuilds the cache and the snapshot' );
list( $served, $built ) = lunara_test_read();
lunara_test_assert( 0 === $built && 2 === $served, 'after the event, the new index serves from the cache' );

// An editor sees a fresh build at once.
delete_transient( LUNARA_DEBRIEF_INDEX_CACHE_KEY );
$GLOBALS['t']['editor'] = true;
list( $served, $built ) = lunara_test_read();
lunara_test_assert( 1 === $built && 3 === $served, 'someone who can edit posts gets a fresh build at once' );
$GLOBALS['t']['editor'] = false;

// A snapshot older than a day is not served.
delete_transient( LUNARA_DEBRIEF_INDEX_CACHE_KEY );
$old = get_option( LUNARA_DEBRIEF_INDEX_SNAPSHOT_OPTION );
$old['built_at'] -= DAY_IN_SECONDS + 1;
update_option( LUNARA_DEBRIEF_INDEX_SNAPSHOT_OPTION, $old, false );
list( $served, $built ) = lunara_test_read();
lunara_test_assert( 1 === $built && 4 === $served, 'a snapshot older than a day is rebuilt at once' );

// A snapshot of an older index version is not served.
delete_transient( LUNARA_DEBRIEF_INDEX_CACHE_KEY );
$stale = get_option( LUNARA_DEBRIEF_INDEX_SNAPSHOT_OPTION );
$stale['index']['version'] = 2;
update_option( LUNARA_DEBRIEF_INDEX_SNAPSHOT_OPTION, $stale, false );
list( $served, $built ) = lunara_test_read();
lunara_test_assert( 1 === $built && 5 === $served, 'a snapshot of an older index version is rebuilt at once' );

fwrite( STDOUT, "Debrief index cache runtime passed: {$checks} checks.\n" );
