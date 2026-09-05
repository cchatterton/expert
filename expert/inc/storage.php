<?php
/** Network tables, atomic expiring leases and append-only audit records. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_install_storage() {
	if ( get_site_option( 'expert_schema' ) === EXPERT_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$collate = $wpdb->get_charset_collate();
	$prefix  = $wpdb->base_prefix;
	dbDelta(
		"CREATE TABLE {$prefix}expert_embeddings (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 blog_id bigint unsigned NOT NULL,
 object_id bigint unsigned NOT NULL,
 object_type varchar(32) NOT NULL,
 chunk_index int NOT NULL,
 chunk_text text NOT NULL,
 content_hash char(64) NOT NULL,
 embedding_json longtext NOT NULL,
 embedding_model varchar(191) NOT NULL,
 updated_at datetime NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY object_chunk (blog_id,object_id,object_type,chunk_index),
 KEY site_model (blog_id,embedding_model)
 ) $collate;"
	);
	dbDelta(
		"CREATE TABLE {$prefix}expert_loops (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 blog_id bigint unsigned NOT NULL,
 loop_uuid char(36) NOT NULL,
 mode varchar(20) NOT NULL,
 started_at datetime NOT NULL,
 completed_at datetime NOT NULL,
 duration_seconds int NOT NULL DEFAULT 0,
 scheduled_interval_seconds int NOT NULL DEFAULT 600,
 engagement_score double NOT NULL DEFAULT 0,
 engagement_per_hour double NOT NULL DEFAULT 0,
 knowledge_piece_count int NOT NULL DEFAULT 0,
 research_topic text NOT NULL,
 sources_added int NOT NULL DEFAULT 0,
 posts_added int NOT NULL DEFAULT 0,
 comments_added int NOT NULL DEFAULT 0,
 faqs_added int NOT NULL DEFAULT 0,
 backlog_added int NOT NULL DEFAULT 0,
 status varchar(32) NOT NULL,
 error_code varchar(64) NOT NULL,
 PRIMARY KEY  (id),
 KEY site_time (blog_id,id)
 ) $collate;"
	);
	dbDelta(
		"CREATE TABLE {$prefix}expert_timeline (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 blog_id bigint unsigned NOT NULL,
 created_at datetime NOT NULL,
 actor_type varchar(16) NOT NULL,
 actor bigint unsigned NOT NULL DEFAULT 0,
 action_type varchar(64) NOT NULL,
 object_type varchar(32) NOT NULL,
 object_id bigint unsigned NOT NULL DEFAULT 0,
 description text NOT NULL,
 sources text NOT NULL,
 loop_uuid varchar(36) NOT NULL,
 PRIMARY KEY  (id),
 KEY site_time (blog_id,id)
 ) $collate;"
	);
	dbDelta(
		"CREATE TABLE {$prefix}expert_engagement (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 blog_id bigint unsigned NOT NULL,
 hour bigint unsigned NOT NULL,
 object_id bigint unsigned NOT NULL DEFAULT 0,
 score double NOT NULL DEFAULT 0,
 events int NOT NULL DEFAULT 0,
 PRIMARY KEY  (id),
 UNIQUE KEY site_hour_object (blog_id,hour,object_id)
 ) $collate;"
	);
	if ( ! $wpdb->last_error ) {
		update_site_option( 'expert_schema', EXPERT_VERSION );
	}
}
function expert_lock( $name, $ttl = 300 ) {
	global $wpdb;
	$key   = 'expert_lock_' . sanitize_key( $name );
	$token = ( time() + $ttl ) . ':' . wp_generate_uuid4();
	if ( add_option( $key, $token, '', false ) ) {
		return $token;
	}
	$old = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $key ) );
	if ( $old && (int) $old < time() ) {
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND option_value=%s", $token, $key, $old ) );
		wp_cache_delete( $key, 'options' );
		if ( $changed ) {
			return $token;
		}
	}
	return false;
}
function expert_unlock( $name, $token ) {
	global $wpdb;
	$key = 'expert_lock_' . sanitize_key( $name );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", $key, $token ) );
	wp_cache_delete( $key, 'options' );
}
function expert_event( $action, $id, $description, $actor = 'Agent', $sources = array(), $type = '' ) {
	global $wpdb;
	$agent = get_option( 'expert_agent', array() );
	$ok    = $wpdb->insert(
		$wpdb->base_prefix . 'expert_timeline',
		array(
			'blog_id'     => get_current_blog_id(),
			'created_at'  => current_time( 'mysql', true ),
			'actor_type'  => $actor,
			'actor'       => 'Human' === $actor ? get_current_user_id() : (int) ( $agent['user'] ?? 0 ),
			'action_type' => sanitize_key( $action ),
			'object_type' => $type ?: ( get_post_type( $id ) ?: 'agent' ),
			'object_id'   => $id,
			'description' => sanitize_textarea_field( $description ),
			'sources'     => wp_json_encode( array_map( 'absint', $sources ) ),
			'loop_uuid'   => $GLOBALS['expert_loop_uuid'] ?? '',
		)
	);
	if ( ! $ok ) {
		throw new RuntimeException( 'timeline_write_failed' );
	}
}
function expert_timeline( $page = 1 ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}expert_timeline WHERE blog_id=%d ORDER BY id DESC LIMIT 30 OFFSET %d", get_current_blog_id(), ( max( 1, $page ) - 1 ) * 30 ), ARRAY_A );
}
function expert_loop_history( $count = 6 ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}expert_loops WHERE blog_id=%d ORDER BY id DESC LIMIT %d", get_current_blog_id(), min( 50, $count ) ), ARRAY_A );
}
