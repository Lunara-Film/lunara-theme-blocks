<?php
/**
 * The name is always "Lunara Film", never "Lunara" alone (Dalton, 2026-10-23;
 * DESIGN.md, Identity). This is a lint over the theme's source, not a copy pin:
 * it reads every string a person can be shown and fails on a bare "Lunara" or
 * "LUNARA" that is not followed by "Film"/"FILM".
 *
 * Read:
 *  - PHP (root templates, inc/, functions*.php): every string literal and all
 *    inline template text, including attribute values and inline <script>
 *    strings, plus the Template Name header. Comments and HTML comments are
 *    skipped (block delimiters such as <!-- wp:... {...} --> are content).
 *  - JavaScript in assets/js (not minified or bundled): every string and
 *    template literal. Comments and regular expression literals are skipped.
 *  - CSS (style.css, assets/css): string values, plus the style.css header.
 *  - theme.json string values.
 * Identifiers never match: lunara_*, LunaraApp, LUNARA_*, lunara-film, URLs.
 *
 * Run: php tests/brand-name-runtime.php
 */
$root = dirname( __DIR__ );

// The explicit exceptions. Each one is code, not copy, and says why.
// file: glob; text: regex the whole string must match; line: regex its source
// line must match. An entry that matches nothing fails, so the list cannot rot.
$allow = array(
	array(
		'file' => 'assets/css/*.css',
		'text' => '/^Lunara (Journal|Oscars|Review) Tiempos Text$/',
		'line' => '/font-family\s*:/',
		'why'  => 'font-family names that must match their @font-face declarations; never rendered as text',
	),
	array(
		'file' => '{inc/site-studio*.php,assets/js/lunara-site-studio.js}',
		'text' => '/^Lunara$/',
		'line' => '/[\'"]?\bq[\'"]?\s*(=>|:)\s*[\'"]Lunara[\'"]|\.q\s*!==\s*[\'"]Lunara[\'"]/',
		'why'  => 'the Site Studio Search preview\'s sample query, validated by the preview protocol in PHP and JS',
	),
	array(
		'file' => '{archive-journal.php,inc/journal-archive-studio.php}',
		'text' => '/^The Lunara Journal$/',
		'line' => '/in_array\(\s*\$kicker|===\s*\$legacy_kicker/',
		'why'  => 'a stored pre-Studio kicker, matched at the read boundary so it is never printed',
	),
	array(
		'file' => 'functions.php',
		'text' => '/^News, Reactions, and the Lunara Journal$/',
		'line' => '/if \( \'News, Reactions, and the Lunara Journal\' ===/',
		'why'  => 'a stored legacy heading, matched so it is replaced before it is printed',
	),
	array(
		'file' => '{inc/review-rendering.php,functions.php}',
		'text' => '/LUNARA\\\\s\+DEBRIEF/',
		'line' => '/\$marker_pattern\s*=/',
		'why'  => 'a pattern that finds the legacy "LUNARA DEBRIEF" marker in stored review content',
	),
	array(
		'file' => 'inc/setup.php',
		'text' => '/^Lunara (Festival QR|Legacy News)$/',
		'line' => '/wp_safe_redirect\(/',
		'why'  => 'X-Redirect-By response header values that name the redirecting code',
	),
);

// "Lunara" not followed by "Film" (whitespace, a hyphen, &nbsp;, a regex \s+
// or markup between them still counts). Identifier fragments are not words.
$sep  = '(?:\s|-|\\\\s[+*?]?|&nbsp;|&#160;|<[^>]*>)+';
$bare = '/(?<![\w\-.\/@#\\\\$])(?:Lunara\b(?!' . $sep . 'Film\b)|LUNARA\b(?!' . $sep . 'FILM\b))/';

$violations = array();
$used       = array_fill( 0, count( $allow ), 0 );
$checked    = 0;

function brand_nl( $text, $length ) {
	return substr_count( substr( $text, 0, $length ), "\n" );
}

function brand_glob_match( $glob, $rel ) {
	if ( preg_match( '/^\{(.+)\}$/', $glob, $m ) ) {
		foreach ( explode( ',', $m[1] ) as $g ) {
			if ( fnmatch( $g, $rel ) ) {
				return true;
			}
		}
		return false;
	}
	return fnmatch( $glob, $rel );
}

/**
 * Record a string found at $line of $rel. $lines are the file's source lines.
 */
function brand_check( $rel, $line, $text, $lines ) {
	global $bare, $allow, $used, $violations, $checked;
	$checked++;
	if ( ! preg_match_all( $bare, $text, $m, PREG_OFFSET_CAPTURE ) ) {
		return;
	}
	foreach ( $m[0] as $hit ) {
		$at     = $line + brand_nl( $text, $hit[1] );
		$source = isset( $lines[ $at - 1 ] ) ? $lines[ $at - 1 ] : '';
		foreach ( $allow as $i => $entry ) {
			if ( brand_glob_match( $entry['file'], $rel ) && preg_match( $entry['text'], $text ) && preg_match( $entry['line'], $source ) ) {
				$used[ $i ]++;
				continue 2;
			}
		}
		$excerpt      = substr( $text, max( 0, $hit[1] - 60 ), 140 );
		$excerpt      = preg_replace( '/^[\x80-\xBF]+|[\xC0-\xFF][\x80-\xBF]*$/', '', $excerpt ); // no split UTF-8 at the cut
		$excerpt      = trim( preg_replace( '/\s+/', ' ', $excerpt ) );
		$violations[] = "$rel:$at: …{$excerpt}…";
	}
}

/**
 * Strings in JavaScript source: [ line, body ] for each '…', "…" and `…`.
 * Comments and regular expression literals are skipped.
 */
function brand_js_strings( $src, $line = 1 ) {
	$out    = array();
	$n      = strlen( $src );
	$i      = 0;
	$last   = '';
	$word   = '';
	$before = '(,=:[!&|?{};+-*%<>~^}';
	$kw     = array( 'return', 'typeof', 'instanceof', 'in', 'of', 'new', 'delete', 'void', 'throw', 'case', 'do', 'else', 'yield', 'await' );
	while ( $i < $n ) {
		$c    = $src[ $i ];
		$next = $i + 1 < $n ? $src[ $i + 1 ] : '';
		if ( "\n" === $c ) {
			$line++;
			$i++;
			continue;
		}
		if ( ' ' === $c || "\t" === $c || "\r" === $c ) {
			$i++;
			continue;
		}
		if ( '/' === $c && '/' === $next ) {
			$j = strpos( $src, "\n", $i );
			$i = false === $j ? $n : $j;
			continue;
		}
		if ( '/' === $c && '*' === $next ) {
			$j     = strpos( $src, '*/', $i + 2 );
			$j     = false === $j ? $n : $j + 2;
			$line += brand_nl( substr( $src, $i ), $j - $i );
			$i     = $j;
			continue;
		}
		if ( "'" === $c || '"' === $c || '`' === $c ) {
			$start = $line;
			$buf   = '';
			$j     = $i + 1;
			while ( $j < $n ) {
				$d = $src[ $j ];
				if ( '\\' === $d ) {
					$buf  .= substr( $src, $j, 2 );
					$line += ( $j + 1 < $n && "\n" === $src[ $j + 1 ] ) ? 1 : 0;
					$j    += 2;
					continue;
				}
				if ( '`' === $c && '$' === $d && $j + 1 < $n && '{' === $src[ $j + 1 ] ) {
					$depth = 1;
					$k     = $j + 2;
					while ( $k < $n && $depth ) {
						$depth += '{' === $src[ $k ] ? 1 : ( '}' === $src[ $k ] ? -1 : 0 );
						$line  += "\n" === $src[ $k ] ? 1 : 0;
						$k++;
					}
					$buf .= ' ';
					$j    = $k;
					continue;
				}
				if ( $d === $c || ( "\n" === $d && '`' !== $c ) ) {
					break;
				}
				$line += "\n" === $d ? 1 : 0;
				$buf  .= $d;
				$j++;
			}
			$out[] = array( $start, $buf );
			$i     = ( $j < $n && $src[ $j ] === $c ) ? $j + 1 : $j;
			$last  = 'a';
			$word  = '';
			continue;
		}
		if ( '/' === $c && ( '' === $last || false !== strpos( $before, $last ) || in_array( $word, $kw, true ) ) ) {
			$j     = $i + 1;
			$class = false;
			while ( $j < $n ) {
				$d = $src[ $j ];
				if ( '\\' === $d ) {
					$j += 2;
					continue;
				}
				if ( "\n" === $d || ( '/' === $d && ! $class ) ) {
					break;
				}
				$class = '[' === $d ? true : ( ']' === $d ? false : $class );
				$j++;
			}
			$j++;
			while ( $j < $n && ctype_alpha( $src[ $j ] ) ) {
				$j++;
			}
			$i    = $j;
			$last = 'a';
			$word = '';
			continue;
		}
		if ( preg_match( '/\G[A-Za-z_$][\w$]*/', $src, $w, 0, $i ) ) {
			$word = $w[0];
			$last = 'a';
			$i   += strlen( $w[0] );
			continue;
		}
		$last = $c;
		$word = '';
		$i++;
	}
	return $out;
}

/**
 * Strings in CSS source (comments blanked first): [ line, body ].
 */
function brand_css_strings( $src, $line = 1 ) {
	$src = preg_replace_callback( '~/\*.*?\*/~s', function ( $c ) { return preg_replace( '/[^\n]/', ' ', $c[0] ); }, $src );
	$out = array();
	if ( preg_match_all( '/"((?:[^"\\\\\n]|\\\\.)*)"|\'((?:[^\'\\\\\n]|\\\\.)*)\'/', $src, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
		foreach ( $m as $s ) {
			$body  = isset( $s[2] ) ? $s[2][0] : $s[1][0];
			$out[] = array( $line + brand_nl( $src, $s[0][1] ), $body );
		}
	}
	return $out;
}

/**
 * Inline template text (T_INLINE_HTML): markup and attributes as text, inline
 * <script> through the JS reader, <style> through the CSS reader. $mode
 * carries an open <script>/<style> across PHP tags.
 */
function brand_check_inline_html( $rel, $line, $html, $lines, &$mode ) {
	$offset = 0;
	$len    = strlen( $html );
	while ( $offset < $len ) {
		$at = $line + brand_nl( $html, $offset );
		if ( 'script' === $mode || 'style' === $mode ) {
			$end   = stripos( $html, '</' . $mode, $offset );
			$chunk = false === $end ? substr( $html, $offset ) : substr( $html, $offset, $end - $offset );
			foreach ( 'script' === $mode ? brand_js_strings( $chunk, $at ) : brand_css_strings( $chunk, $at ) as $s ) {
				brand_check( $rel, $s[0], $s[1], $lines );
			}
			if ( false === $end ) {
				return;
			}
			$mode   = 'html';
			$offset = $end;
			continue;
		}
		if ( ! preg_match( '/<(script|style)\b[^>]*>|<!--(?!\s*\/?wp:).*?(?:-->|$)/is', $html, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
			brand_check( $rel, $at, substr( $html, $offset ), $lines );
			return;
		}
		brand_check( $rel, $at, substr( $html, $offset, $m[0][1] - $offset ), $lines );
		if ( isset( $m[1] ) && '' !== $m[1][0] ) {
			brand_check( $rel, $line + brand_nl( $html, $m[0][1] ), $m[0][0], $lines ); // the tag's own attributes
			$mode = strtolower( $m[1][0] );
		}
		$offset = $m[0][1] + strlen( $m[0][0] );
	}
}

$php = array_merge( glob( $root . '/*.php' ), glob( $root . '/inc/*.php' ), glob( $root . '/inc/*/*.php' ) ?: array(), glob( $root . '/template-parts/*.php' ) ?: array() );
$js  = array_filter( array_merge( glob( $root . '/assets/js/*.js' ), glob( $root . '/assets/js/*/*.js' ) ?: array() ), function ( $f ) { return ! preg_match( '/\.(min|bundle)\.js$/', $f ); } );
$css = array_filter( array_merge( array( $root . '/style.css' ), glob( $root . '/assets/css/*.css' ) ), function ( $f ) { return ! preg_match( '/\.min\.css$/', $f ); } );

foreach ( $php as $file ) {
	$rel   = substr( $file, strlen( $root ) + 1 );
	$src   = file_get_contents( $file );
	$lines = explode( "\n", $src );
	$mode  = 'html';
	foreach ( token_get_all( $src ) as $t ) {
		if ( ! is_array( $t ) ) {
			continue;
		}
		list( $type, $text, $line ) = $t;
		if ( T_CONSTANT_ENCAPSED_STRING === $type ) {
			brand_check( $rel, $line, substr( $text, 1, -1 ), $lines );
		} elseif ( T_ENCAPSED_AND_WHITESPACE === $type ) {
			brand_check( $rel, $line, $text, $lines );
		} elseif ( T_INLINE_HTML === $type ) {
			brand_check_inline_html( $rel, $line, $text, $lines, $mode );
		} elseif ( ( T_DOC_COMMENT === $type || T_COMMENT === $type ) && preg_match_all( '/^[\s*]*Template Name:\s*(.+)$/m', $text, $h, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $h[1] as $name ) {
				brand_check( $rel, $line + brand_nl( $text, $name[1] ), $name[0], $lines );
			}
		}
	}
}

foreach ( $js as $file ) {
	$rel   = substr( $file, strlen( $root ) + 1 );
	$src   = file_get_contents( $file );
	$lines = explode( "\n", $src );
	foreach ( brand_js_strings( $src ) as $s ) {
		brand_check( $rel, $s[0], $s[1], $lines );
	}
}

foreach ( $css as $file ) {
	$rel   = substr( $file, strlen( $root ) + 1 );
	$src   = file_get_contents( $file );
	$lines = explode( "\n", $src );
	if ( 'style.css' === $rel && preg_match( '~^/\*(.*?)\*/~s', $src, $head ) && preg_match_all( '/^(?:Theme Name|Description|Author):\s*(.+)$/m', $head[1], $h, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $h[1] as $field ) {
			brand_check( $rel, 1 + brand_nl( $head[1], $field[1] ), $field[0], $lines );
		}
	}
	foreach ( brand_css_strings( $src ) as $s ) {
		brand_check( $rel, $s[0], $s[1], $lines );
	}
}

$theme_json = $root . '/theme.json';
$lines      = explode( "\n", file_get_contents( $theme_json ) );
foreach ( $lines as $n => $l ) {
	if ( preg_match_all( '/"((?:[^"\\\\]|\\\\.)*)"/', $l, $m ) ) {
		foreach ( $m[1] as $s ) {
			brand_check( 'theme.json', $n + 1, $s, $lines );
		}
	}
}

foreach ( $used as $i => $count ) {
	if ( 0 === $count ) {
		$violations[] = 'tests/brand-name-runtime.php: allowlist entry ' . ( $i + 1 ) . ' (' . $allow[ $i ]['why'] . ') matches nothing; remove it.';
	}
}

if ( $violations ) {
	fwrite( STDERR, "Bare \"Lunara\" in visible copy; the name is always \"Lunara Film\" (DESIGN.md, Identity):\n" . implode( "\n", $violations ) . "\n" );
	exit( 1 );
}
printf( "Brand name guard OK (%d files, %d strings, %d allowlisted).\n", count( $php ) + count( $js ) + count( $css ) + 1, $checked, array_sum( $used ) );
