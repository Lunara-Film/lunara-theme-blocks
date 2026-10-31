<?php
/**
 * Render timing for admins (Theme 3.2.141).
 *
 * The speed pass needs to know where an uncached page spends its time on the
 * live site, with the live plugins and the live data. A request that carries
 * the `lunara-timing` query parameter records:
 *
 * - when each loading phase starts (the theme, after_setup_theme, init,
 *   wp_loaded, template_redirect, wp_head, wp_footer), with the database
 *   query count at that moment;
 * - each callback on the main page hooks, with the plugin, theme or core file
 *   that owns it;
 * - each top-level block render, and each Home span front-page.php marks.
 *
 * At the end of the page, a user who can manage options gets one HTML comment
 * that holds the report as base64 JSON. Nobody else gets output, and a request
 * without the parameter does no timing work: this file only defines the
 * functions below.
 *
 * Read it in a logged-in browser tab on the site:
 *   const html = await (await fetch('/?lunara-timing=1')).text();
 *   JSON.parse(atob(html.match(/<!-- lunara-timing ([A-Za-z0-9+\/=]+) -->/)[1]));
 *
 * A callback's "ms" includes the measured callbacks it runs inside it; its
 * "self_ms" does not. The owner totals add self time only, so nested work is
 * not counted twice. A callback that takes an argument by reference is left
 * unwrapped and unmeasured, so a timed request behaves as an untimed one.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The hooks whose callbacks a timed request measures one by one. */
function lunara_render_timing_hooks() {
	return array( 'after_setup_theme', 'init', 'wp_loaded', 'parse_request', 'pre_get_posts', 'wp', 'template_redirect', 'wp_enqueue_scripts', 'wp_head', 'wp_body_open', 'the_content', 'render_block', 'wp_footer', 'wp_print_footer_scripts' );
}

/** True when this request asked for timing. */
function lunara_render_timing_requested() {
	return isset( $_GET['lunara-timing'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only flag; the report goes to admins only.
}

/** True while this request records timing. */
function lunara_render_timing_enabled() {
	return ! empty( $GLOBALS['lunara_render_timing']['on'] );
}

/** The database queries this request has run so far. */
function lunara_render_timing_queries() {
	global $wpdb;
	return ( is_object( $wpdb ) && isset( $wpdb->num_queries ) ) ? (int) $wpdb->num_queries : 0;
}

/**
 * Start (or restart) the recorder. With $on false nothing is hooked and every
 * function here returns at once.
 *
 * @param bool $on Record this request.
 */
function lunara_render_timing_boot( $on ) {
	$GLOBALS['lunara_render_timing'] = array(
		'on'          => (bool) $on,
		'start'       => isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true ),
		'marks'       => array(),
		'spans'       => array(),
		'open'        => array(),
		'owners'      => array(),
		'callbacks'   => array(),
		'frames'      => array(),
		'wrappers'    => array(),
		'blocks'      => array(),
		'block_depth' => 0,
		'block_open'  => null,
		'template'    => '',
	);
	if ( ! $on ) {
		return;
	}

	lunara_render_timing_mark( 'theme' );
	foreach ( lunara_render_timing_hooks() as $hook ) {
		add_filter( $hook, 'lunara_render_timing_wrap_hook', PHP_INT_MIN, 1 );
	}
	foreach ( array( 'after_setup_theme', 'init', 'wp_loaded', 'template_redirect', 'wp_head', 'wp_footer' ) as $hook ) {
		add_filter( $hook, 'lunara_render_timing_mark_start', PHP_INT_MIN, 1 );
	}
	foreach ( array( 'init', 'wp_head', 'wp_footer' ) as $hook ) {
		add_filter( $hook, 'lunara_render_timing_mark_end', PHP_INT_MAX, 1 );
	}
	add_filter( 'template_include', 'lunara_render_timing_template', PHP_INT_MAX, 1 );
	add_filter( 'pre_render_block', 'lunara_render_timing_block_start', PHP_INT_MAX, 2 );
	add_filter( 'render_block', 'lunara_render_timing_block_end', PHP_INT_MAX, 2 );
	add_action( 'shutdown', 'lunara_render_timing_print', PHP_INT_MAX );
}

/**
 * Record the first time a label is reached, with the query count then.
 *
 * @param string $label Phase name.
 */
function lunara_render_timing_mark( $label ) {
	if ( ! lunara_render_timing_enabled() ) {
		return;
	}
	$label = (string) $label;
	if ( ! isset( $GLOBALS['lunara_render_timing']['marks'][ $label ] ) ) {
		$GLOBALS['lunara_render_timing']['marks'][ $label ] = array( microtime( true ), lunara_render_timing_queries() );
	}
}

/** Hook callback: the current hook starts. Passes a filter value through. */
function lunara_render_timing_mark_start( $value = null ) {
	lunara_render_timing_mark( current_filter() . ':start' );
	return $value;
}

/** Hook callback: the current hook ends. Passes a filter value through. */
function lunara_render_timing_mark_end( $value = null ) {
	lunara_render_timing_mark( current_filter() . ':end' );
	return $value;
}

/** Filter callback: note which template renders the page. */
function lunara_render_timing_template( $template ) {
	lunara_render_timing_mark( 'template' );
	if ( lunara_render_timing_enabled() && is_string( $template ) ) {
		$GLOBALS['lunara_render_timing']['template'] = basename( $template );
	}
	return $template;
}

/**
 * Open or close a named span, such as one Home section.
 *
 * @param string $label A span name.
 * @param string $edge  'begin' or 'end'. An end without a begin is ignored.
 */
function lunara_render_timing_span( $label, $edge ) {
	if ( ! lunara_render_timing_enabled() ) {
		return;
	}
	$state = &$GLOBALS['lunara_render_timing'];
	$label = (string) $label;
	if ( 'begin' === $edge ) {
		$state['open'][ $label ] = array( hrtime( true ), lunara_render_timing_queries() );
		return;
	}
	if ( ! isset( $state['open'][ $label ] ) ) {
		return;
	}
	list( $started, $queries ) = $state['open'][ $label ];
	unset( $state['open'][ $label ] );
	$state['spans'][] = array(
		'label'   => $label,
		'ms'      => round( ( hrtime( true ) - $started ) / 1e6, 2 ),
		'queries' => lunara_render_timing_queries() - $queries,
	);
}

/**
 * The owner of a PHP file: a plugin folder, 'mu:' and a must-use plugin,
 * 'theme:' and a theme folder, 'core', 'php' for a built-in function, or
 * 'other'. Works for managed paths (WordPress.com keeps core and some plugins
 * outside wp-content), because only the path segments are read.
 *
 * @param string $file Absolute path, or '' for a built-in function.
 * @return string
 */
function lunara_render_timing_owner( $file ) {
	$file = str_replace( '\\', '/', (string) $file );
	if ( '' === $file ) {
		return 'php';
	}
	foreach ( array( '/mu-plugins/' => 'mu:', '/plugins/' => '', '/themes/' => 'theme:' ) as $marker => $prefix ) {
		$at = strpos( $file, $marker );
		if ( false !== $at ) {
			$folder = (string) strtok( substr( $file, $at + strlen( $marker ) ), '/' );
			return $prefix . preg_replace( '/\.php$/', '', $folder );
		}
	}
	if ( false !== strpos( $file, '/wp-includes/' ) || false !== strpos( $file, '/wp-admin/' ) ) {
		return 'core';
	}
	return 'other';
}

/**
 * Describe a hook callback.
 *
 * @param mixed $callback A hook callback.
 * @return array|null array( owner, name ), or null when it must not be wrapped:
 *                    it cannot be reflected, or it takes an argument by reference.
 */
function lunara_render_timing_describe( $callback ) {
	try {
		if ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
			$callback = explode( '::', $callback, 2 );
		}
		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			$reflection = new ReflectionMethod( $callback[0], (string) $callback[1] );
			$name       = ( is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0] ) . '::' . $callback[1];
		} elseif ( $callback instanceof Closure || is_string( $callback ) ) {
			$reflection = new ReflectionFunction( $callback );
			$name       = $callback instanceof Closure ? '{closure}' : $callback;
		} elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
			$reflection = new ReflectionMethod( $callback, '__invoke' );
			$name       = get_class( $callback ) . '::__invoke';
		} else {
			return null;
		}
	} catch ( Throwable $error ) {
		return null;
	}

	foreach ( $reflection->getParameters() as $parameter ) {
		if ( $parameter->isPassedByReference() ) {
			return null;
		}
	}

	$file = $reflection->isInternal() ? '' : (string) $reflection->getFileName();
	if ( $callback instanceof Closure ) {
		$name .= '@' . basename( $file ) . ':' . $reflection->getStartLine();
	}
	return array( lunara_render_timing_owner( $file ), $name );
}

/**
 * A stand-in for one callback that runs it unchanged and records its time.
 *
 * @param string $hook     Hook name.
 * @param mixed  $callback The original callback.
 * @param string $owner    Its owner.
 * @param string $name     Its name.
 * @return Closure
 */
function lunara_render_timing_wrapper( $hook, $callback, $owner, $name ) {
	$key = $hook . ' | ' . $owner . ' | ' . $name;
	return static function ( ...$args ) use ( $callback, $owner, $key ) {
		$state             = &$GLOBALS['lunara_render_timing'];
		$state['frames'][] = array( 0.0, 0 );
		$queries           = lunara_render_timing_queries();
		$started           = hrtime( true );
		try {
			return call_user_func_array( $callback, $args );
		} finally {
			$elapsed  = ( hrtime( true ) - $started ) / 1e6;
			$ran      = lunara_render_timing_queries() - $queries;
			$children = array_pop( $state['frames'] );
			if ( $state['frames'] ) {
				$parent                       = count( $state['frames'] ) - 1;
				$state['frames'][ $parent ][0] += $elapsed;
				$state['frames'][ $parent ][1] += $ran;
			}
			lunara_render_timing_count( $owner, $key, $elapsed, $elapsed - $children[0], $ran, $ran - $children[1] );
		}
	};
}

/** Add one measured call to the owner and callback totals. */
function lunara_render_timing_count( $owner, $key, $elapsed, $self, $queries, $self_queries ) {
	$state = &$GLOBALS['lunara_render_timing'];
	if ( ! isset( $state['owners'][ $owner ] ) ) {
		$state['owners'][ $owner ] = array( 'self_ms' => 0.0, 'calls' => 0, 'queries' => 0 );
	}
	$state['owners'][ $owner ]['self_ms'] += $self;
	$state['owners'][ $owner ]['calls']   += 1;
	$state['owners'][ $owner ]['queries'] += $self_queries;

	if ( ! isset( $state['callbacks'][ $key ] ) ) {
		$state['callbacks'][ $key ] = array( 'ms' => 0.0, 'self_ms' => 0.0, 'calls' => 0, 'queries' => 0 );
	}
	$state['callbacks'][ $key ]['ms']      += $elapsed;
	$state['callbacks'][ $key ]['self_ms'] += $self;
	$state['callbacks'][ $key ]['calls']   += 1;
	$state['callbacks'][ $key ]['queries'] += $queries;
}

/**
 * Hook callback at the first priority: put a measured stand-in in place of
 * every callback of the current hook that is not measured yet. Callbacks at
 * later priorities run through their stand-ins in this same run. Passes a
 * filter value through.
 */
function lunara_render_timing_wrap_hook( $value = null ) {
	global $wp_filter;
	$hook = current_filter();
	if ( ! lunara_render_timing_enabled() || ! is_string( $hook ) || ! isset( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) || ! is_array( $wp_filter[ $hook ]->callbacks ) ) {
		return $value;
	}
	$state = &$GLOBALS['lunara_render_timing'];
	foreach ( array_keys( $wp_filter[ $hook ]->callbacks ) as $priority ) {
		foreach ( array_keys( $wp_filter[ $hook ]->callbacks[ $priority ] ) as $id ) {
			$callback = isset( $wp_filter[ $hook ]->callbacks[ $priority ][ $id ]['function'] ) ? $wp_filter[ $hook ]->callbacks[ $priority ][ $id ]['function'] : null;
			if ( null === $callback || ( is_string( $callback ) && 0 === strpos( $callback, 'lunara_render_timing_' ) ) ) {
				continue;
			}
			if ( $callback instanceof Closure && isset( $state['wrappers'][ spl_object_id( $callback ) ] ) ) {
				continue;
			}
			$described = lunara_render_timing_describe( $callback );
			if ( null === $described ) {
				continue;
			}
			$wrapper                                   = lunara_render_timing_wrapper( $hook, $callback, $described[0], $described[1] );
			$state['wrappers'][ spl_object_id( $wrapper ) ] = true;
			$wp_filter[ $hook ]->callbacks[ $priority ][ $id ]['function'] = $wrapper;
		}
	}
	return $value;
}

/**
 * pre_render_block, last: a block starts to render. A block another filter
 * already replaced does not render, so it is not counted.
 */
function lunara_render_timing_block_start( $pre_render, $parsed_block = array() ) {
	if ( null !== $pre_render || ! lunara_render_timing_enabled() ) {
		return $pre_render;
	}
	$state = &$GLOBALS['lunara_render_timing'];
	++$state['block_depth'];
	if ( 1 === $state['block_depth'] ) {
		$name                = is_array( $parsed_block ) && isset( $parsed_block['blockName'] ) ? (string) $parsed_block['blockName'] : '';
		$state['block_open'] = array( $name, hrtime( true ), lunara_render_timing_queries() );
	}
	return $pre_render;
}

/** render_block, last: a block finished. Top-level named blocks are kept. */
function lunara_render_timing_block_end( $content, $parsed_block = array() ) {
	if ( ! lunara_render_timing_enabled() ) {
		return $content;
	}
	$state = &$GLOBALS['lunara_render_timing'];
	if ( $state['block_depth'] <= 0 ) {
		return $content;
	}
	--$state['block_depth'];
	if ( 0 === $state['block_depth'] && is_array( $state['block_open'] ) ) {
		list( $name, $started, $queries ) = $state['block_open'];
		$state['block_open']               = null;
		if ( '' !== $name ) {
			$state['blocks'][] = array(
				'block'   => $name,
				'ms'      => round( ( hrtime( true ) - $started ) / 1e6, 2 ),
				'queries' => lunara_render_timing_queries() - $queries,
			);
		}
	}
	return $content;
}

/** The report, ready to encode. */
function lunara_render_timing_report() {
	$state = $GLOBALS['lunara_render_timing'];
	$start = $state['start'];

	$marks = array();
	foreach ( $state['marks'] as $label => $mark ) {
		$marks[ $label ] = array( 'at_ms' => round( ( $mark[0] - $start ) * 1000, 1 ), 'queries' => $mark[1] );
	}

	$owners = $state['owners'];
	uasort( $owners, static function ( $a, $b ) { return $b['self_ms'] <=> $a['self_ms']; } );
	foreach ( $owners as $owner => $row ) {
		$owners[ $owner ]['self_ms'] = round( $row['self_ms'], 2 );
	}

	$callbacks = $state['callbacks'];
	uasort( $callbacks, static function ( $a, $b ) { return $b['ms'] <=> $a['ms']; } );
	$callbacks = array_slice( $callbacks, 0, 60, true );
	foreach ( $callbacks as $key => $row ) {
		$callbacks[ $key ]['ms']      = round( $row['ms'], 2 );
		$callbacks[ $key ]['self_ms'] = round( $row['self_ms'], 2 );
	}

	$files = array();
	foreach ( get_included_files() as $file ) {
		$owner           = lunara_render_timing_owner( $file );
		$files[ $owner ] = isset( $files[ $owner ] ) ? $files[ $owner ] + 1 : 1;
	}
	arsort( $files );

	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	return array(
		'v'              => 1,
		'path'           => (string) strtok( $uri, '?' ),
		'template'       => $state['template'],
		'total_ms'       => round( ( microtime( true ) - $start ) * 1000, 1 ),
		'wp_start_ms'    => isset( $GLOBALS['timestart'] ) ? round( ( (float) $GLOBALS['timestart'] - $start ) * 1000, 1 ) : null,
		'queries'        => lunara_render_timing_queries(),
		'memory_mb'      => round( memory_get_peak_usage() / 1048576, 1 ),
		'object_cache'   => function_exists( 'wp_using_ext_object_cache' ) ? (bool) wp_using_ext_object_cache() : null,
		'marks'          => $marks,
		'spans'          => $state['spans'],
		'blocks'         => $state['blocks'],
		'owners'         => $owners,
		'callbacks'      => $callbacks,
		'files'          => count( get_included_files() ),
		'files_by_owner' => $files,
	);
}

/**
 * shutdown, last: print the report for an admin on an HTML page. Feeds, REST,
 * Ajax and admin screens never run wp_footer, so they never get it.
 */
function lunara_render_timing_print() {
	if ( ! lunara_render_timing_enabled() || ! did_action( 'wp_footer' ) ) {
		return;
	}
	if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo "\n<!-- lunara-timing " . base64_encode( (string) wp_json_encode( lunara_render_timing_report() ) ) . " -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64 of our own JSON, admin-only.
}

lunara_render_timing_boot( lunara_render_timing_requested() );
