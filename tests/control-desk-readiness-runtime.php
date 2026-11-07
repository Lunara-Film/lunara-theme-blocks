<?php
/**
 * The Control Desk's readiness row for the Oscars Ledger plugin (3.2.158).
 *
 * Run: php tests/control-desk-readiness-runtime.php
 *
 * Runs the production lunara_control_desk_oscars_plugin_state() against the seam the theme declares:
 * no plugin reads "needs", anything below the seam "weak", the seam and everything newer "ready".
 * Versions are derived from the seam, never written down, so no release number is pinned here.
 * Then reads inc/control-desk.php for the retired repository paths the screen used to name.
 */
define( 'ABSPATH', __DIR__ . '/' );
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/inc/control-desk.php' );
$checks = 0;
function readiness_assert( $condition, $message ) {
    ++$GLOBALS['checks'];
    if ( ! $condition ) {
        fwrite( STDERR, "Control Desk readiness: $message\n" );
        exit( 1 );
    }
}
readiness_assert( is_string( $source ) && '' !== $source, 'inc/control-desk.php is readable' );

// ---- The production function and its seam, extracted ------------------------------------------------------
readiness_assert( 1 === preg_match( "/define\( 'LUNARA_OSCARS_LEDGER_SEAM', '([0-9.]+)' \)/", $source, $seam_match ), 'the seam constant is declared once with a version' );
define( 'LUNARA_OSCARS_LEDGER_SEAM', $seam_match[1] );
$start = strpos( $source, 'function lunara_control_desk_oscars_plugin_state(' );
readiness_assert( false !== $start, 'the state function exists' );
$function = ''; $depth = 0; $opened = false;
foreach ( token_get_all( '<?php ' . substr( $source, $start ) ) as $token ) {
    if ( is_array( $token ) ) { if ( T_OPEN_TAG !== $token[0] ) { $function .= $token[1]; } continue; }
    $function .= $token;
    if ( '{' === $token ) { ++$depth; $opened = true; }
    if ( '}' === $token && 0 === --$depth && $opened ) { break; }
}
eval( $function );

// ---- Behaviour at, above and below the seam ----------------------------------------------------------------
$seam  = LUNARA_OSCARS_LEDGER_SEAM;
$parts = array_map( 'intval', explode( '.', $seam ) );
$below = implode( '.', array( $parts[0], $parts[1], max( 0, $parts[2] - 1 ) ) );
if ( $below === $seam ) { $below = implode( '.', array( $parts[0], max( 0, $parts[1] - 1 ), 99 ) ); }
$patch_above = implode( '.', array( $parts[0], $parts[1], $parts[2] + 28 ) );
$minor_above = implode( '.', array( $parts[0], $parts[1] + 1, 0 ) );
$major_above = implode( '.', array( $parts[0] + 1, 0, 0 ) );
$old_pin     = '2.7.89';
readiness_assert( 'needs' === lunara_control_desk_oscars_plugin_state( '' ), 'no plugin reads needs' );
readiness_assert( 'needs' === lunara_control_desk_oscars_plugin_state( null ), 'a null version reads needs' );
readiness_assert( 'weak' === lunara_control_desk_oscars_plugin_state( $below ), "a build below the seam ($below) reads weak" );
readiness_assert( 'ready' === lunara_control_desk_oscars_plugin_state( $seam ), "the seam itself ($seam) reads ready" );
readiness_assert( 'ready' === lunara_control_desk_oscars_plugin_state( $patch_above ), "a patch release above the seam ($patch_above) reads ready" );
readiness_assert( 'ready' === lunara_control_desk_oscars_plugin_state( $minor_above ), "a minor release above the seam ($minor_above) reads ready" );
readiness_assert( 'ready' === lunara_control_desk_oscars_plugin_state( $major_above ), "a major release above the seam ($major_above) reads ready" );
readiness_assert( 'ready' === lunara_control_desk_oscars_plugin_state( " $seam " ), 'whitespace around the version is ignored' );
readiness_assert( version_compare( $old_pin, $seam, '<' ) ? 'weak' === lunara_control_desk_oscars_plugin_state( $old_pin ) : true, 'the version the row used to pin is judged by the seam like any other' );

// ---- The row uses the function and the seam ----------------------------------------------------------------
readiness_assert( false !== strpos( $source, "'state' => lunara_control_desk_oscars_plugin_state( \$aat_version )" ), 'the Academy Awards row takes its state from the function' );
readiness_assert( 1 !== preg_match( "/'[0-9]+\.[0-9]+\.[0-9]+' === \\\$aat_version/", $source ), 'no row compares the plugin version with a pinned release' );
readiness_assert( false !== strpos( $source, 'LUNARA_OSCARS_LEDGER_SEAM' ), 'the row note names the seam' );

// ---- The screen names the repositories that exist ---------------------------------------------------------
readiness_assert( false === strpos( $source, 'TheAntagonist2020/' ), 'no retired TheAntagonist2020/ repository path remains' );
foreach ( array( 'lunara-theme-blocks', 'lunara-plugin-ai-assistant-classic', 'lunara-plugin-core', 'lunara-plugin-oscars-ledger', 'lunara-plugin-dispatch', 'lunara-plugin-imdb-guard' ) as $repo ) {
    readiness_assert( false !== strpos( $source, 'Lunara-Film/' . $repo ), "the screen names Lunara-Film/$repo" );
}
echo "Control Desk readiness: $checks checks passed.\n";
