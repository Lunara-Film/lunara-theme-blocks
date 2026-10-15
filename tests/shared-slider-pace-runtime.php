<?php
/** Exercise shared pace through the real Studio storage, validation and restore owner. */
ob_start(); require __DIR__ . '/site-studio-pilot-runtime.php'; ob_end_clean();
$before = $lunara_pilot_options;
$adapter = lunara_site_studio_global_design_adapter();
unset( $lunara_pilot_options['lunara_design_tokens'] );
lunara_pilot_assert( 12000 === lunara_slider_interval() && 0 === lunara_slider_interval( 0 ), 'Default is twelve seconds; manual stays manual.' );
$state = $adapter->read_state(); $state['slider_seconds'] = 18;
$saved = $adapter->save_state( $state );
lunara_pilot_assert( ! is_wp_error( $saved ) && 18 === $adapter->read_state()['slider_seconds'] && 18000 === lunara_slider_interval( 5000 ), 'Apply persists one shared pace that overrides older positive intervals.' );
foreach ( array( 7, 31, 12.5, '18', null, array() ) as $invalid ) {
    $candidate = $state; $candidate['slider_seconds'] = $invalid;
    lunara_pilot_assert( is_wp_error( $adapter->validate_state( $candidate ) ), 'Unsafe, fractional or out-of-range pace is rejected.' );
}
$classic = lunara_design_tokens_prepare_save( array(), lunara_get_design_tokens() );
lunara_pilot_assert( 18 === $classic['slider_seconds'], 'Classic palette edits preserve the shared pace.' );
$restored = $adapter->restore_revision( $saved['revision_id'] );
lunara_pilot_assert( ! is_wp_error( $restored ) && 12000 === lunara_slider_interval(), 'History restores the prior shared pace.' );
echo "Shared slider pace: validation, save, read, Classic preservation and restore passed.\n";
