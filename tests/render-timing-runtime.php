<?php
/**
 * Behavior contract for the admin render timing (3.2.141, inc/render-timing.php).
 *
 * The speed pass reads where a live page spends its time. These checks hold
 * the promises that make that safe on production:
 *
 * - without ?lunara-timing nothing is hooked and nothing is printed;
 * - a timed request runs every callback with the same arguments and values,
 *   and leaves a callback that takes an argument by reference alone;
 * - each callback's time goes to the plugin, theme or core file that owns
 *   it, and nested work is not counted twice in the owner totals;
 * - top-level blocks and named spans are measured;
 * - only an admin on an HTML page gets the report, as one HTML comment.
 *
 * The hook stub iterates the way WordPress core's WP_Hook does: each
 * priority's callbacks are read when that priority is reached.
 *
 * Run: php tests/render-timing-runtime.php
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$checks = 0;
function lunara_test_assert( $condition, string $message ): void {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

/** A hook as core runs it (wp-includes/class-wp-hook.php, apply_filters). */
class WP_Hook {
	public $callbacks = array();
	public $doing_action = false;

	public function add_filter( $callback, int $priority, int $accepted_args ): void {
		$id = is_string( $callback ) ? $callback : spl_object_hash( (object) $callback );
		if ( is_array( $callback ) ) {
			$id = ( is_object( $callback[0] ) ? spl_object_hash( $callback[0] ) : $callback[0] ) . '::' . $callback[1];
		} elseif ( $callback instanceof Closure ) {
			$id = spl_object_hash( $callback );
		}
		$this->callbacks[ $priority ][ $id ] = array( 'function' => $callback, 'accepted_args' => $accepted_args );
		ksort( $this->callbacks, SORT_NUMERIC );
	}

	public function apply_filters( $value, array $args ) {
		$priorities = array_keys( $this->callbacks );
		$num_args   = count( $args );
		foreach ( $priorities as $priority ) {
			foreach ( $this->callbacks[ $priority ] as $the_ ) {
				if ( ! $this->doing_action ) {
					$args[0] = $value;
				}
				if ( 0 === $the_['accepted_args'] ) {
					$value = call_user_func( $the_['function'] );
				} elseif ( $the_['accepted_args'] >= $num_args ) {
					$value = call_user_func_array( $the_['function'], $args );
				} else {
					$value = call_user_func_array( $the_['function'], array_slice( $args, 0, $the_['accepted_args'] ) );
				}
			}
		}
		return $value;
	}
}

$GLOBALS['wp_filter']         = array();
$GLOBALS['wp_current_filter'] = array();
$GLOBALS['lunara_test_did']   = array();
$GLOBALS['lunara_test_admin'] = false;

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	if ( ! isset( $GLOBALS['wp_filter'][ $hook ] ) ) {
		$GLOBALS['wp_filter'][ $hook ] = new WP_Hook();
	}
	$GLOBALS['wp_filter'][ $hook ]->add_filter( $callback, (int) $priority, (int) $accepted_args );
	return true;
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $hook, $callback, $priority, $accepted_args );
}
function apply_filters( $hook, $value, ...$args ) {
	if ( ! isset( $GLOBALS['wp_filter'][ $hook ] ) ) {
		return $value;
	}
	$GLOBALS['wp_current_filter'][] = $hook;
	$GLOBALS['wp_filter'][ $hook ]->doing_action = false;
	$value = $GLOBALS['wp_filter'][ $hook ]->apply_filters( $value, array_merge( array( $value ), $args ) );
	array_pop( $GLOBALS['wp_current_filter'] );
	return $value;
}
function do_action( $hook, ...$args ) {
	$GLOBALS['lunara_test_did'][ $hook ] = ( $GLOBALS['lunara_test_did'][ $hook ] ?? 0 ) + 1;
	if ( ! isset( $GLOBALS['wp_filter'][ $hook ] ) ) {
		return;
	}
	$GLOBALS['wp_current_filter'][] = $hook;
	$GLOBALS['wp_filter'][ $hook ]->doing_action = true;
	$GLOBALS['wp_filter'][ $hook ]->apply_filters( '', $args ? $args : array( '' ) );
	$GLOBALS['wp_filter'][ $hook ]->doing_action = false;
	array_pop( $GLOBALS['wp_current_filter'] );
}
function do_action_ref_array( $hook, array $args ) {
	$GLOBALS['lunara_test_did'][ $hook ] = ( $GLOBALS['lunara_test_did'][ $hook ] ?? 0 ) + 1;
	if ( ! isset( $GLOBALS['wp_filter'][ $hook ] ) ) {
		return;
	}
	$GLOBALS['wp_current_filter'][] = $hook;
	$GLOBALS['wp_filter'][ $hook ]->doing_action = true;
	$GLOBALS['wp_filter'][ $hook ]->apply_filters( '', $args );
	$GLOBALS['wp_filter'][ $hook ]->doing_action = false;
	array_pop( $GLOBALS['wp_current_filter'] );
}
function current_filter() { return end( $GLOBALS['wp_current_filter'] ); }
function did_action( $hook ) { return $GLOBALS['lunara_test_did'][ $hook ] ?? 0; }
function current_user_can( $capability ) { return 'manage_options' === $capability && $GLOBALS['lunara_test_admin']; }
function wp_doing_ajax() { return false; }
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_using_ext_object_cache() { return true; }

/** Every registered callback of a hook that the recorder owns. */
function lunara_test_timing_callbacks(): array {
	$found = array();
	foreach ( $GLOBALS['wp_filter'] as $hook => $wp_hook ) {
		foreach ( $wp_hook->callbacks as $callbacks ) {
			foreach ( $callbacks as $entry ) {
				if ( is_string( $entry['function'] ) && 0 === strpos( $entry['function'], 'lunara_render_timing_' ) ) {
					$found[] = $hook . ' ' . $entry['function'];
				}
			}
		}
	}
	return $found;
}

function lunara_test_report(): array {
	ob_start();
	lunara_render_timing_print();
	$out = (string) ob_get_clean();
	if ( ! preg_match( '/^\n<!-- lunara-timing ([A-Za-z0-9+\/=]+) -->\n$/', $out, $m ) ) {
		return array( 'raw' => $out );
	}
	return (array) json_decode( (string) base64_decode( $m[1] ), true );
}

/*
 * Real files in plugin, theme and core places, so the owners come from the
 * same paths WordPress loads them from.
 */
$root = sys_get_temp_dir() . '/lunara-render-timing-' . getmypid();
$files = array(
	'/wp-content/plugins/slowplug/slowplug.php' => '<?php
function slowplug_init() { usleep( 30000 ); }
function slowplug_inner() { usleep( 30000 ); }
function slowplug_append( $value ) { return $value . "+slow"; }
function slowplug_two( $value, $extra ) { return $value . "+" . $extra; }
function slowplug_by_ref( &$query ) { $query = "changed"; }
function slowplug_query() {
	$GLOBALS["wpdb"]->num_queries += 3;
	$card = "require(\'wp-blog-header.php\'), lunara_render_home_running_order, lunara_get_review_card_image_data, attachment_url_to_postid";
	$GLOBALS["wpdb"]->queries[] = array( "SELECT post_id, meta_value FROM wp_postmeta WHERE meta_key = \'_wp_attached_file\' AND meta_value = \'2026/07/a.jpg\'", 0.012, $card );
	$GLOBALS["wpdb"]->queries[] = array( "SELECT   post_id,\n meta_value FROM wp_postmeta WHERE meta_key = \'_wp_attached_file\' AND meta_value = \'2026/07/b.jpg\'", 0.020, $card );
	$GLOBALS["wpdb"]->queries[] = array( "SELECT option_value FROM wp_options WHERE option_name = \'x\'", 0.001, "require(\'wp-blog-header.php\'), get_option" );
}',
	'/wp-content/themes/lunara-theme/inc/hub.php' => '<?php
function lunara_theme_hub() { do_action( "lunara_inner" ); }',
	'/wordpress/core/7.1.3/wp-includes/core.php' => '<?php
function core_head() {}',
);
foreach ( $files as $path => $source ) {
	@mkdir( dirname( $root . $path ), 0777, true );
	file_put_contents( $root . $path, $source );
	require $root . $path;
}
// One query ran before the theme loaded; the report counts only later ones.
$GLOBALS['wpdb'] = (object) array( 'num_queries' => 0, 'queries' => array( array( 'SELECT 1', 0.5, 'wp_load_alloptions' ) ) );

$_GET = array();
require dirname( __DIR__ ) . '/inc/render-timing.php';

// Owners come from path segments, which also covers managed WordPress.com paths.
foreach ( array(
	'/wordpress/plugins/jetpack/16.4-a.7/class.jetpack.php'   => 'jetpack',
	'/srv/htdocs/wp-content/plugins/lunara-core/lunara-core.php' => 'lunara-core',
	'/srv/htdocs/wp-content/plugins/hello.php'                => 'hello',
	'/srv/htdocs/wp-content/mu-plugins/wpcomsh/loader.php'    => 'mu:wpcomsh',
	'/srv/htdocs/wp-content/themes/blocksy/inc/init.php'      => 'theme:blocksy',
	'/wordpress/core/7.1.3/wp-includes/plugin.php'            => 'core',
	'/wordpress/core/7.1.3/wp-admin/includes/file.php'        => 'core',
	''                                                        => 'php',
	'/srv/htdocs/index.php'                                   => 'other',
) as $path => $owner ) {
	lunara_test_assert( $owner === lunara_render_timing_owner( $path ), "the owner of '{$path}' is {$owner}" );
}

/*
 * Untimed (the normal request): the module hooks nothing, a span records
 * nothing, and even an admin gets no comment.
 */
lunara_test_assert( array() === lunara_test_timing_callbacks(), 'without ?lunara-timing the module hooks nothing' );
lunara_test_assert( false === lunara_render_timing_enabled(), 'without ?lunara-timing the recorder is off' );
add_filter( 'the_content', 'slowplug_append' );
lunara_test_assert( 'text+slow' === apply_filters( 'the_content', 'text' ) && 'slowplug_append' === $GLOBALS['wp_filter']['the_content']->callbacks[10]['slowplug_append']['function'], 'untimed, a callback runs as itself' );
lunara_render_timing_span( 'front-door', 'begin' );
lunara_render_timing_span( 'front-door', 'end' );
lunara_render_timing_mark( 'theme_loaded' );
do_action( 'wp_footer' );
$GLOBALS['lunara_test_admin'] = true;
lunara_test_assert( array( 'raw' => '' ) === lunara_test_report(), 'untimed, an admin gets no comment' );
lunara_test_assert( array() === $GLOBALS['lunara_render_timing']['spans'] && array() === $GLOBALS['lunara_render_timing']['marks'], 'untimed, spans and marks record nothing' );
lunara_test_assert( ! defined( 'SAVEQUERIES' ), 'untimed, query logging stays off' );
lunara_test_assert( 1 === apply_filters( 'attachment_url_to_postid', 1, 'https://lunarafilm.com/wp-content/uploads/a.jpg' ) && 0 === $GLOBALS['lunara_render_timing']['lookups']['count'], 'untimed, a lookup is not counted' );

/*
 * Timed. The hooks start fresh, the way a new request starts.
 */
$GLOBALS['wp_filter']       = array();
$GLOBALS['lunara_test_did'] = array();
$_GET['lunara-timing']      = '1';
lunara_test_assert( true === lunara_render_timing_requested(), 'the query parameter asks for timing' );
lunara_render_timing_boot( lunara_render_timing_requested() );
lunara_test_assert( in_array( 'init lunara_render_timing_wrap_hook', lunara_test_timing_callbacks(), true ) && in_array( 'enqueue_block_assets lunara_render_timing_wrap_hook', lunara_test_timing_callbacks(), true ) && in_array( 'shutdown lunara_render_timing_print', lunara_test_timing_callbacks(), true ), 'timed, the recorder hooks the page hooks, enqueue_block_assets included, and the shutdown print' );
lunara_test_assert( defined( 'SAVEQUERIES' ) && true === SAVEQUERIES && 1 === $GLOBALS['lunara_render_timing']['db_from'], 'timed, queries are logged from the moment the theme loads' );

// Callbacks registered after the recorder (as plugins' and the theme's are
// by the time the hooks run) are measured, and keep their behavior.
add_action( 'init', 'slowplug_init' );
add_action( 'init', 'lunara_theme_hub', 20 );
add_action( 'lunara_inner', 'slowplug_inner' );
add_filter( 'wp_head', 'core_head' );
add_filter( 'the_content', 'slowplug_append' );
add_filter( 'the_content', static function ( $value ) { return $value . '+closure'; }, 20 );
add_filter( 'the_content', 'slowplug_two', 30, 2 );
add_action( 'wp', 'slowplug_by_ref' );
add_action( 'wp', 'slowplug_query', 20 );

do_action( 'init' );
lunara_test_assert( array_key_exists( 'init:start', $GLOBALS['lunara_render_timing']['marks'] ) && array_key_exists( 'init:end', $GLOBALS['lunara_render_timing']['marks'] ), 'init marks its start and end' );

// lunara_inner is not a page hook, so slowplug_inner runs unmeasured inside
// the theme's callback: the theme owns that time. Measure the inner hook too
// and the time moves to the plugin.
$owners = $GLOBALS['lunara_render_timing']['owners'];
lunara_test_assert( isset( $owners['slowplug'], $owners['theme:lunara-theme'] ), 'init callbacks are counted for their owners' );
lunara_test_assert( $owners['slowplug']['self_ms'] >= 25 && $owners['theme:lunara-theme']['self_ms'] >= 25, 'unmeasured nested work belongs to the callback that ran it' );

$GLOBALS['lunara_render_timing']['owners']    = array();
$GLOBALS['lunara_render_timing']['callbacks'] = array();
add_filter( 'lunara_inner', 'lunara_render_timing_wrap_hook', PHP_INT_MIN, 1 );
do_action( 'init' );
$owners = $GLOBALS['lunara_render_timing']['owners'];
lunara_test_assert( $owners['slowplug']['self_ms'] >= 50, 'both plugin callbacks count for the plugin (30 ms each)' );
lunara_test_assert( $owners['theme:lunara-theme']['self_ms'] < 20, 'measured nested work is not counted again for the theme callback around it' );
$hub = $GLOBALS['lunara_render_timing']['callbacks']['init | theme:lunara-theme | lunara_theme_hub'] ?? null;
lunara_test_assert( null !== $hub && $hub['ms'] >= 25 && $hub['self_ms'] < 20 && 1 === $hub['calls'], "a callback's ms includes measured nested work; its self_ms does not" );

// Filters keep their values and their argument counts. A second run does not
// wrap the stand-ins again.
$extra = 'extra';
lunara_test_assert( 'text+slow+closure+extra' === apply_filters( 'the_content', 'text', $extra ), 'timed, every filter callback gets its value and arguments and returns as before' );
lunara_test_assert( 'text+slow+closure+extra' === apply_filters( 'the_content', 'text', $extra ), 'a second run gives the same value' );
$append = $GLOBALS['lunara_render_timing']['callbacks']['the_content | slowplug | slowplug_append'] ?? null;
lunara_test_assert( null !== $append && 2 === $append['calls'], 'a callback that runs twice counts two calls, not nested ones' );
$closure_keys = array_values( array_filter( array_keys( $GLOBALS['lunara_render_timing']['callbacks'] ), static function ( $key ) { return 0 === strpos( $key, 'the_content | other | {closure}@render-timing-runtime.php:' ); } ) );
lunara_test_assert( 1 === count( $closure_keys ), 'a closure is named by its file and line' );

// A callback that takes an argument by reference is not wrapped; queries go
// to the callback that ran them.
$query = 'original';
do_action_ref_array( 'wp', array( &$query ) );
lunara_test_assert( 'slowplug_by_ref' === $GLOBALS['wp_filter']['wp']->callbacks[10]['slowplug_by_ref']['function'] && 'changed' === $query, 'a by-reference callback is left as itself and still changes its argument' );
lunara_test_assert( 3 === ( $GLOBALS['lunara_render_timing']['callbacks']['wp | slowplug | slowplug_query']['queries'] ?? null ) && 3 === $GLOBALS['lunara_render_timing']['owners']['slowplug']['queries'], 'database queries go to the callback that ran them' );

// Spans: begin and end measure; an end without a begin is ignored.
lunara_render_timing_span( 'front-door', 'begin' );
usleep( 5000 );
lunara_render_timing_span( 'front-door', 'end' );
lunara_render_timing_span( 'never-opened', 'end' );
$spans = $GLOBALS['lunara_render_timing']['spans'];
lunara_test_assert( 1 === count( $spans ) && 'front-door' === $spans[0]['label'] && $spans[0]['ms'] >= 4, 'a span measures the time between its begin and end' );

// Blocks: the top-level block is measured with its inner blocks inside it;
// a block another filter already replaced does not render and is not counted.
add_filter( 'pre_render_block', static function ( $pre, $block ) { return 'core/cached' === ( $block['blockName'] ?? '' ) ? '<cached>' : $pre; }, 10, 2 );
$section = array( 'blockName' => 'lunara/pairing-desk' );
lunara_test_assert( null === apply_filters( 'pre_render_block', null, $section, null ), 'pre_render_block passes null through' );
apply_filters( 'pre_render_block', null, array( 'blockName' => 'core/paragraph' ), null );
apply_filters( 'render_block', '<p>x</p>', array( 'blockName' => 'core/paragraph' ) );
usleep( 5000 );
lunara_test_assert( '<section>x</section>' === apply_filters( 'render_block', '<section>x</section>', $section ), 'render_block passes the content through' );
lunara_test_assert( '<cached>' === apply_filters( 'pre_render_block', null, array( 'blockName' => 'core/cached' ), null ), 'a replaced block keeps its replacement' );
apply_filters( 'pre_render_block', null, array( 'blockName' => null ), null );
apply_filters( 'render_block', "\n\n", array( 'blockName' => null ) );
apply_filters( 'render_block', '<orphan>', array( 'blockName' => 'core/orphan' ) );
$blocks = $GLOBALS['lunara_render_timing']['blocks'];
lunara_test_assert( 1 === count( $blocks ) && 'lunara/pairing-desk' === $blocks[0]['block'] && $blocks[0]['ms'] >= 4, 'only the top-level named block is listed, with its inner blocks inside its time' );
lunara_test_assert( 0 === $GLOBALS['lunara_render_timing']['block_depth'], 'every block that started has finished' );

// Attachment lookups: each one that runs its query is timed; one another
// filter already answered is not counted.
$local = 'https://lunarafilm.com/wp-content/uploads/2026/07/a.jpg';
lunara_test_assert( null === apply_filters( 'pre_attachment_url_to_postid', null, $local ), 'pre_attachment_url_to_postid passes null through' );
usleep( 3000 );
lunara_test_assert( 42 === apply_filters( 'attachment_url_to_postid', 42, $local ), 'attachment_url_to_postid passes the ID through' );
apply_filters( 'pre_attachment_url_to_postid', null, 'https://image.tmdb.org/t/p/w342/x.jpg' );
apply_filters( 'attachment_url_to_postid', null, 'https://image.tmdb.org/t/p/w342/x.jpg' );
add_filter( 'pre_attachment_url_to_postid', static function ( $id, $url ) { return false !== strpos( $url, 'cached' ) ? 7 : $id; }, 10, 2 );
lunara_test_assert( 7 === apply_filters( 'pre_attachment_url_to_postid', null, 'https://lunarafilm.com/wp-content/uploads/cached.jpg' ), 'an answer from an earlier filter passes through' );
$lookups = $GLOBALS['lunara_render_timing']['lookups'];
lunara_test_assert( 2 === $lookups['count'] && 1 === $lookups['found'] && $lookups['ms'] >= 2 && array() === $GLOBALS['lunara_render_timing']['lookup_open'], 'two lookups ran their query, one found an attachment, and none is left open' );

// Phase marks and the template.
do_action( 'wp_head' );
$template = '/srv/htdocs/wp-content/themes/lunara-theme/front-page.php';
lunara_test_assert( $template === apply_filters( 'template_include', $template ) && 'front-page.php' === $GLOBALS['lunara_render_timing']['template'], 'the template passes through unchanged and its name is noted' );

// The report: nothing before wp_footer (not an HTML page yet), nothing for a
// visitor, one comment for an admin.
lunara_test_assert( array( 'raw' => '' ) === lunara_test_report(), 'before wp_footer there is no report: REST, feeds and Ajax never run it' );
do_action( 'wp_footer' );
$GLOBALS['lunara_test_admin'] = false;
lunara_test_assert( array( 'raw' => '' ) === lunara_test_report(), 'a visitor never gets the report' );
$GLOBALS['lunara_test_admin'] = true;
$_SERVER['REQUEST_URI'] = '/?lunara-timing=1&audit=7';
$report = lunara_test_report();
lunara_test_assert( 1 === ( $report['v'] ?? null ) && '/' === $report['path'] && 'front-page.php' === $report['template'], 'an admin gets one comment with the report' );
foreach ( array( 'theme', 'init:start', 'init:end', 'wp_head:start', 'wp_head:end', 'template', 'wp_footer:start', 'wp_footer:end' ) as $mark ) {
	lunara_test_assert( isset( $report['marks'][ $mark ]['at_ms'] ), "the report has the {$mark} mark" );
}
lunara_test_assert( $report['marks']['init:start']['at_ms'] <= $report['marks']['init:end']['at_ms'] && $report['marks']['init:end']['at_ms'] <= $report['marks']['wp_footer:end']['at_ms'], 'marks are in page order' );
lunara_test_assert( 'slowplug' === array_key_first( $report['owners'] ), 'owners are sorted by self time, the slowest first' );
lunara_test_assert( isset( $report['callbacks']['init | slowplug | slowplug_init'], $report['callbacks']['wp_head | core | core_head'] ), 'the report lists measured callbacks by hook, owner and name' );
lunara_test_assert( 'front-door' === $report['spans'][0]['label'] && 'lunara/pairing-desk' === $report['blocks'][0]['block'], 'the report carries the spans and the blocks' );
lunara_test_assert( ( $report['files_by_owner']['slowplug'] ?? 0 ) >= 1 && ( $report['files_by_owner']['theme:lunara-theme'] ?? 0 ) >= 1 && $report['files'] >= 3, 'the report counts loaded files by owner' );
lunara_test_assert( $report['total_ms'] > 0 && 3 === $report['queries'] && true === $report['object_cache'], 'the report has the total, the query count and the object cache state' );
lunara_test_assert( 2 === $report['lookups']['count'] && 42 === $report['lookups']['calls'][0]['id'] && $report['lookups']['calls'][0]['ms'] >= $report['lookups']['calls'][1]['ms'], 'the report lists the lookups, the slowest first' );
$db = $report['db'];
lunara_test_assert( true === $db['logged'] && 3 === $db['count'] && abs( $db['ms'] - 33 ) < 0.01, 'the report counts only the queries logged after the theme loaded, with their time' );
lunara_test_assert( abs( $db['slowest'][0]['ms'] - 20 ) < 0.01 && 0 === strpos( $db['slowest'][0]['sql'], 'SELECT post_id, meta_value FROM wp_postmeta' ), 'the slowest query comes first, its whitespace collapsed' );
$card_caller = "lunara_render_home_running_order > lunara_get_review_card_image_data > attachment_url_to_postid";
lunara_test_assert( "require('wp-blog-header.php') > " . $card_caller === array_key_first( $db['by_caller'] ) && 2 === $db['by_caller'][ array_key_first( $db['by_caller'] ) ]['count'] && abs( $db['by_caller'][ array_key_first( $db['by_caller'] ) ]['ms'] - 32 ) < 0.01, 'queries add up by the code that ran them, the costliest first' );

foreach ( array_keys( $files ) as $path ) {
	@unlink( $root . $path );
}

fwrite( STDOUT, "Render timing runtime passed: {$checks} checks.\n" );
