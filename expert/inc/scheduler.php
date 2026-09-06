<?php
/** Native cron orchestration, safe site restoration and bounded telemetry. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_network_tick() {
	if ( ! expert_active() ) {
		return;
	}
	if ( ! wp_next_scheduled( 'expert_network_tick' ) ) {
		wp_schedule_single_event( time() + 60, 'expert_network_tick' );
	}
	if ( function_exists( 'expert_browser_ai_enabled' ) && expert_browser_ai_enabled() ) {
		return;
	}
	// Every active Expert site can wake the network; a root-site lease serialises dispatch.
	$network_root = get_main_site_id();
	switch_to_blog( $network_root );
	try {
		$token = expert_lock( 'network_dispatch', 300 );
		if ( ! $token ) {
			return;
		}
		try {
			$cursor     = (int) get_site_option( 'expert_scheduler_cursor', 0 );
			$sites      = expert_agent_sites( false, $cursor, 20 );
			$dispatched = 0;
			$visited    = 0;
			foreach ( $sites as $site ) {
				++$visited;
				switch_to_blog( $site->blog_id );
				try {
					$state = expert_state();
					if ( expert_is_agent() && ! $state['paused'] && $state['next_loop'] <= time() ) {
						if ( ! wp_next_scheduled( 'expert_manual_loop' ) ) {
							wp_schedule_single_event( time(), 'expert_manual_loop' );
						}
						// Trigger the site's native cron without holding the dispatch request open.
						spawn_cron();
						++$dispatched;
					}
				} finally {
					restore_current_blog();
				}
				if ( $dispatched >= 3 ) {
					break;
				}
			}
			update_site_option( 'expert_scheduler_cursor', $visited === count( $sites ) && count( $sites ) < 20 ? 0 : $cursor + $visited );
		} finally {
			expert_unlock( 'network_dispatch', $token );
		}
	} finally {
		restore_current_blog();
	}
}
add_action( 'expert_network_tick', 'expert_network_tick' );
function expert_run_loop() {
	if ( ! expert_is_agent() || expert_state()['paused'] ) {
		return 'inactive';
	}
	$token = expert_lock( 'loop', 300 );
	if ( ! $token ) {
		return 'locked';
	}
	global $wpdb;
	$started                     = microtime( true );
	$state                       = expert_state();
	$old_mode                    = $state['mode'];
	$status                      = 'failed';
	$error_code                  = '';
	$GLOBALS['expert_budget']    = array( 'deadline' => $started + expert_limits()['seconds'] );
	$GLOBALS['expert_loop_uuid'] = wp_generate_uuid4();
	$GLOBALS['expert_topic']     = '';
	$GLOBALS['expert_counts']    = array();
	try {
		$health = expert_runtime( '/health' );
		if ( is_wp_error( $health ) || true !== ( $health['ok'] ?? false ) ) {
			throw new RuntimeException( 'runtime_unavailable' );
		}
		$status            = expert_research_cycle();
		$state['failures'] = 'completed' === $status ? 0 : $state['failures'] + 1;
	} catch ( Throwable $error ) {
		$error_code = sanitize_key( $error->getMessage() );
		++$state['failures'];
	} finally {
		try {
			$metrics = expert_engagement_metrics( $state, expert_loop_history() );
			$count   = expert_knowledge_count();
			$state   = expert_cadence( $count, $metrics, $state, expert_config() );
			if ( $state['failures'] >= 3 ) {
				$state['mode']     = 'Error';
				$state['interval'] = min( 86400, 10800 * pow( 2, min( 3, $state['failures'] - 3 ) ) );
			}
			$override = (int) ( get_option( 'expert_agent' )['cadence'] ?? 0 );
			if ( $override && 'Error' !== $state['mode'] ) {
				$state['interval'] = max( 600, min( 86400, $override ) );
			}
			// Re-read pause state: a human can pause while an inference request is outstanding.
			if ( expert_state()['paused'] ) {
				$state['paused'] = true;
				$state['mode']   = 'Paused';
			}
			$state['last_loop'] = time();
			$state['next_loop'] = time() + $state['interval'];
			update_option( 'expert_state', $state, false );
			$budget = $GLOBALS['expert_counts'];
			$wpdb->insert(
				$wpdb->base_prefix . 'expert_loops',
				array(
					'blog_id'                    => get_current_blog_id(),
					'loop_uuid'                  => $GLOBALS['expert_loop_uuid'],
					'mode'                       => $state['mode'],
					'started_at'                 => gmdate( 'Y-m-d H:i:s', (int) $started ),
					'completed_at'               => gmdate( 'Y-m-d H:i:s' ),
					'duration_seconds'           => (int) ( microtime( true ) - $started ),
					'scheduled_interval_seconds' => $state['interval'],
					'engagement_score'           => $metrics['score'],
					'engagement_per_hour'        => $metrics['per_hour'],
					'knowledge_piece_count'      => $count,
					'research_topic'             => $GLOBALS['expert_topic'],
					'sources_added'              => $budget['sources'] ?? 0,
					'posts_added'                => $budget['posts'] ?? 0,
					'comments_added'             => $budget['comments'] ?? 0,
					'faqs_added'                 => $budget['faqs'] ?? 0,
					'backlog_added'              => $budget['backlog'] ?? 0,
					'status'                     => $status,
					'error_code'                 => mb_substr( $error_code, 0, 64 ),
				)
			);
			if ( $old_mode !== $state['mode'] ) {
				expert_event( 'cadence_changed', 0, $old_mode . ' → ' . $state['mode'] . '; ' . $state['interval'] . ' seconds.', 'System' );
			}
			expert_event( 'loop_' . $status, 0, $error_code ? expert_failure_description( $error_code ) : __( 'Bounded research cycle finished.', 'expert' ), 'System' );
			$cutoff = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->base_prefix}expert_loops WHERE blog_id=%d ORDER BY id DESC LIMIT 1 OFFSET 199", get_current_blog_id() ) );
			if ( $cutoff ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->base_prefix}expert_loops WHERE blog_id=%d AND id<%d", get_current_blog_id(), $cutoff ) );
			}
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->base_prefix}expert_engagement WHERE blog_id=%d AND hour<%d", get_current_blog_id(), (int) floor( time() / 3600 ) - 720 ) );
		} finally {
			unset( $GLOBALS['expert_budget'], $GLOBALS['expert_loop_uuid'], $GLOBALS['expert_topic'], $GLOBALS['expert_counts'] );
			expert_unlock( 'loop', $token );
		}
	}
		return $status;
}
add_action( 'expert_manual_loop', 'expert_run_loop' );
