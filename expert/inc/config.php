<?php
/** Central configuration and hard ceilings. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_defaults() {
	return array(
		'runtime_url'      => 'http://127.0.0.1:8765',
		'text_model'       => '',
		'embedding_model'  => '',
		'discovery_url'    => '',
		'growth_threshold' => 100,
		'growth_interval'  => 600,
		'steady_interval'  => 10800,
		'moderate'         => 10,
		'high'             => 30,
		'very_high'        => 80,
		'publishing'       => 'draft',
		'max_ai'           => 24,
		'max_seconds'      => 180,
		'max_fetches'      => 6,
		'max_bytes'        => 262144,
	);
}
function expert_config() {
	return wp_parse_args( get_site_option( 'expert_settings', array() ), expert_defaults() );
}
function expert_limits() {
	$settings = expert_config();
	return array(
		'ai'         => min( 40, max( 4, (int) $settings['max_ai'] ) ),
		'seconds'    => min( 240, max( 30, (int) $settings['max_seconds'] ) ),
		'fetches'    => min( 8, max( 2, (int) $settings['max_fetches'] ) ),
		'bytes'      => min( 524288, max( 16384, (int) $settings['max_bytes'] ) ),
		'candidates' => 6,
		'searches'   => 1,
		'sources'    => 2,
		'posts'      => 1,
		'comments'   => 2,
		'terms'      => 4,
		'faqs'       => 2,
		'backlog'    => 3,
		'vectors'    => 2000,
		'chunks'     => 4,
	);
}
function expert_active() {
	return is_multisite() && 'expert' === get_option( 'template' );
}
function expert_is_agent() {
	return expert_active() && ! is_main_site() && (bool) get_option( 'expert_agent' );
}
function expert_state() {
	return wp_parse_args(
		get_option( 'expert_state', array() ),
		array(
			'mode'           => 'Growth',
			'interval'       => 600,
			'last_loop'      => 0,
			'next_loop'      => 0,
			'failures'       => 0,
			'paused'         => false,
			'band'           => 0,
			'candidate_band' => 0,
			'band_streak'    => 0,
			'average'        => 0,
			'per_hour'       => 0,
			'trend'          => 0,
		)
	);
}
function expert_error( $code, $message = '' ) {
	return new WP_Error( $code, $message ?: __( 'This action could not be completed. Please try again later.', 'expert' ) );
}
function expert_budget( $kind ) {
	if ( empty( $GLOBALS['expert_budget'] ) ) {
		return;
	}
	$budget =& $GLOBALS['expert_budget'];
	if ( isset( $GLOBALS['expert_loop_uuid'] ) && ( ! expert_is_agent() || expert_state()['paused'] ) ) {
		throw new RuntimeException( 'agent_paused' ); }
	if ( microtime( true ) >= $budget['deadline'] ) {
		throw new RuntimeException( 'loop_timeout' );
	}
	$budget[ $kind ] = ( $budget[ $kind ] ?? 0 ) + 1;
	if ( $budget[ $kind ] > ( expert_limits()[ $kind ] ?? 0 ) ) {
		throw new RuntimeException( 'limit_' . $kind );
	}
}

function expert_failure_description( $code ) {
	$messages = array(
		'runtime_unavailable'      => __( 'The local reasoning service is unavailable. Learning will retry later.', 'expert' ),
		'discovery_not_configured' => __( 'Network search setup is needed before research can begin.', 'expert' ),
		'discovery_unavailable'    => __( 'The search service is unavailable. Existing knowledge is unchanged.', 'expert' ),
		'loop_timeout'             => __( 'The research time limit was reached. Further work is deferred.', 'expert' ),
	);
	return $messages[ $code ] ?? __( 'Research could not finish safely. Further work is deferred; administrators can inspect the loop details.', 'expert' );
}
