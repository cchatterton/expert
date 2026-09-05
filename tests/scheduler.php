<?php
/** Regression: due Agent cron must be spawned without a front-end visit. */
wp_set_current_user( 1 );
$GLOBALS['expert_spawned_urls'] = array();
function expert_capture_spawn( $pre, $args, $url ) {
 if ( str_contains( $url, 'wp-cron.php' ) ) {
  $GLOBALS['expert_spawned_urls'][] = $url;
  return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '', 'cookies' => array() );
 }
 return $pre;
}
add_filter( 'pre_http_request', 'expert_capture_spawn', 10, 3 );
foreach ( get_sites( array( 'number' => 20 ) ) as $site ) {
 switch_to_blog( $site->blog_id );
 $state = expert_state(); $state['paused'] = (int) $site->blog_id !== 2; $state['next_loop'] = time() - 60; update_option( 'expert_state', $state );
 wp_unschedule_hook( 'expert_manual_loop' ); delete_transient( 'doing_cron' );
 restore_current_blog();
}
update_site_option( 'expert_scheduler_cursor', 0 );
expert_network_tick();
switch_to_blog( 2 );
if ( ! wp_next_scheduled( 'expert_manual_loop' ) || wp_next_scheduled( 'expert_manual_loop' ) > time() ) { throw new RuntimeException( 'Job must already be due at spawn' ); }
if ( ! $GLOBALS['expert_spawned_urls'] || ! str_contains( $GLOBALS['expert_spawned_urls'][0], '/systems-expert/wp-cron.php' ) ) { throw new RuntimeException( 'Agent cron was not spawned' ); }
delete_transient( 'doing_cron' ); restore_current_blog();
$first = count( $GLOBALS['expert_spawned_urls'] ); expert_network_tick();
if ( count( $GLOBALS['expert_spawned_urls'] ) <= $first ) { throw new RuntimeException( 'Pending due cron did not retry' ); }
echo "PASS: Due cron is spawned immediately for an unvisited Agent\nPASS: Pending due cron is retried on the next dispatch\nPASS: Site context restored: " . get_current_blog_id() . "\n";
