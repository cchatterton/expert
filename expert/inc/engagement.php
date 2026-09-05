<?php
/** Minimal first-party aggregates and sustained cadence bands. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_engage( $kind, $id = 0 ) {
	global $wpdb;
	$weights = array(
		'view'    => 1,
		'source'  => 2,
		'search'  => 3,
		'faq'     => 3,
		'chat'    => 4,
		'comment' => 4,
		'edit'    => 5,
	);
	if ( ! isset( $weights[ $kind ] ) || ! expert_is_agent() ) {
		return;
	}
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->base_prefix}expert_engagement (blog_id,hour,object_id,score,events) VALUES (%d,%d,%d,%f,1) ON DUPLICATE KEY UPDATE score=score+VALUES(score),events=events+1", get_current_blog_id(), (int) floor( time() / 3600 ), absint( $id ), $weights[ $kind ] ) );
}
function expert_engagement_metrics( $state, $history ) {
	global $wpdb;
	$hours    = $wpdb->get_results( $wpdb->prepare( "SELECT hour,SUM(score) AS score FROM {$wpdb->base_prefix}expert_engagement WHERE blog_id=%d AND hour>=%d GROUP BY hour", get_current_blog_id(), (int) floor( time() / 3600 ) - 5 ), ARRAY_A );
	$snapshot = array();
	$score    = 0;
	foreach ( $hours as $hour ) {
		$snapshot[ $hour['hour'] ] = (float) $hour['score'];
		$score                    += max( 0, (float) $hour['score'] - (float) ( $state['engagement_snapshot'][ $hour['hour'] ] ?? 0 ) );
	}
	$per_hour = array_sum( $snapshot ) / 6;
	$averages = array_merge( array( $score ), array_map( 'floatval', array_column( array_slice( $history, 0, 5 ), 'engagement_score' ) ) );
	return array(
		'score'               => $score,
		'engagement_snapshot' => $snapshot,
		'per_hour'            => $per_hour,
		'average'             => array_sum( $averages ) / count( $averages ),
		'trend'               => $per_hour - (float) ( $history[0]['engagement_per_hour'] ?? 0 ),
	);
}
function expert_cadence( $count, $metrics, $state, $settings ) {
	$state = array_merge( $state, array_intersect_key( $metrics, array_flip( array( 'per_hour', 'average', 'trend', 'engagement_snapshot' ) ) ) );
	if ( $state['paused'] ) {
		$state['mode'] = 'Paused';
		return $state;
	}
	if ( $count < $settings['growth_threshold'] ) {
		$state['mode']     = 'Growth';
		$state['interval'] = (int) $settings['growth_interval'];
		return $state;
	}
	$band = $metrics['per_hour'] >= $settings['very_high'] ? 3 : ( $metrics['per_hour'] >= $settings['high'] ? 2 : ( $metrics['per_hour'] >= $settings['moderate'] ? 1 : 0 ) );
	if ( $band > (int) $state['band'] && isset( $metrics['score'] ) && $metrics['score'] <= 0 ) {
		$band = (int) $state['band'];
	}
	if ( $band === (int) $state['candidate_band'] ) {
		++$state['band_streak'];
	} else {
		$state['candidate_band'] = $band;
		$state['band_streak']    = 1;
	}
	if ( $state['band_streak'] >= 2 ) {
		$state['band'] = $band;
	}
	$intervals         = array( (int) $settings['steady_interval'], 3600, 1800, 600 );
	$state['interval'] = $intervals[ $state['band'] ];
	$state['mode']     = $state['band'] ? 'Active' : 'Steady';
	return $state;
}
