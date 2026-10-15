<?php
/** Exercise asset decisions against the production enqueue functions. */
$checks = 0;
$state = array();
function verify_style_rule( $value, $message ) { global $checks; ++$checks; if ( ! $value ) { throw new RuntimeException( $message ); } }
function is_front_page() { return $GLOBALS['state']['front']; }
function is_preview() { return $GLOBALS['state']['preview']; }
function is_customize_preview() { return $GLOBALS['state']['customize']; }
function lunara_home_uses_block_composition() { return $GLOBALS['state']['composition']; }
function lunara_home_front_page_id() { return 1; }
function lunara_home_front_page_content( $id ) { return 'fixture-content'; }
function parse_blocks( $content ) { return $GLOBALS['state']['blocks']; }
function lunara_home_section_block_map() { return array( 'hero' => 'lunara/cinematic-hero', 'journal' => 'lunara/journal-lane' ); }
function wp_dequeue_style( $handle ) { $GLOBALS['state']['removed'][] = $handle; }
function remove_action( $hook, $callback, $priority = 10 ) { $GLOBALS['state']['actions'][] = array( $hook, $callback, $priority ); }

// Token extraction executes real functions without booting unrelated WordPress hooks.
$wanted = array( 'lunara_home_can_trim_block_styles', 'lunara_disable_unused_home_global_styles', 'lunara_trim_unused_home_styles' );
$tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/inc/frontend.php' ) );
foreach ( $tokens as $i => $token ) {
    if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) { continue; }
    $j = $i + 1;
    while ( isset( $tokens[$j] ) && ( ! is_array( $tokens[$j] ) || T_STRING !== $tokens[$j][0] ) ) { ++$j; }
    if ( ! in_array( $tokens[$j][1] ?? '', $wanted, true ) ) { continue; }
    $code = ''; $depth = 0; $opened = false;
    for ( $k = $i; $k < count( $tokens ); ++$k ) {
        $part = is_array( $tokens[$k] ) ? $tokens[$k][1] : $tokens[$k]; $code .= $part;
        if ( '{' === $part ) { ++$depth; $opened = true; }
        elseif ( '}' === $part && 0 === --$depth && $opened ) { eval( $code ); break; }
    }
}
function block_fixture( $name, $children = array(), $html = '' ) { return array( 'blockName' => $name, 'innerBlocks' => $children, 'innerHTML' => $html ); }
$owned = block_fixture( 'lunara/journal-lane' );
$cases = array(
    array( 'legacy sections', array(), true ),
    array( 'owned block composition', array( 'composition' => true, 'blocks' => array( $owned, block_fixture( null, array(), "\n " ) ) ), true ),
    array( 'core block', array( 'composition' => true, 'blocks' => array( $owned, block_fixture( 'core/columns' ) ) ), false ),
    array( 'synced block', array( 'composition' => true, 'blocks' => array( $owned, block_fixture( 'core/block' ) ) ), false ),
    array( 'third-party block', array( 'composition' => true, 'blocks' => array( $owned, block_fixture( 'stackable/card' ) ) ), false ),
    array( 'nested output', array( 'composition' => true, 'blocks' => array( block_fixture( 'lunara/journal-lane', array( block_fixture( 'core/image' ) ) ) ) ), false ),
    array( 'authored HTML', array( 'composition' => true, 'blocks' => array( $owned, block_fixture( null, array(), '<p>Authored content</p>' ) ) ), false ),
    array( 'other route', array( 'front' => false ), false ),
    array( 'post preview', array( 'preview' => true ), false ),
    array( 'Customizer', array( 'customize' => true ), false ),
    array( 'Studio preview', array( 'studio' => true ), false ),
);
foreach ( $cases as list( $name, $changes, $trim ) ) {
    $state = array_merge( array( 'front' => true, 'preview' => false, 'customize' => false, 'composition' => false, 'blocks' => array(), 'removed' => array(), 'actions' => array() ), $changes );
    $GLOBALS['lunara_site_studio_preview_context'] = ! empty( $state['studio'] ) ? array( 'surface' => 'home' ) : array();
    lunara_disable_unused_home_global_styles();
    lunara_trim_unused_home_styles();
    verify_style_rule( ( ! empty( $state['removed'] ) ) === $trim, $name . ': CSS delivery decision' );
    verify_style_rule( ( 2 === count( $state['actions'] ) ) === $trim, $name . ': global CSS callback decision' );
    if ( $trim ) verify_style_rule( in_array( 'wp-block-library', $state['removed'], true ) && ! in_array( 'lunara-style', $state['removed'], true ), $name . ': preserve the publication stylesheet' );
}
echo "Home stylesheet eligibility: {$checks} checks passed.\n";
