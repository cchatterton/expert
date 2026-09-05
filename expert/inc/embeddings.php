<?php
/** Bounded semantic index. Every SQL operation is scoped to the current blog. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_index( $id, $type = 'post' ) {
	global $wpdb;
	$table = $wpdb->base_prefix . 'expert_embeddings';
	$blog  = get_current_blog_id();
	if ( 'comment' === $type ) {
		$comment = get_comment( $id );
		$post    = $comment ? get_post( $comment->comment_post_ID ) : null;
		$public  = $comment && '1' === $comment->comment_approved && $post && 'publish' === $post->post_status && ! $post->post_password;
		$text    = $public ? $comment->comment_content : '';
	} else {
		$post   = get_post( $id );
		$public = $post && 'publish' === $post->post_status && ! $post->post_password && in_array( $post->post_type, array( 'post', 'expert_source', 'expert_faq', 'expert_research', 'expert_subject' ), true );
		$text   = $public ? $post->post_title . "\n" . $post->post_content : '';
		$type   = $post ? $post->post_type : $type;
	}
	if ( ! $public ) {
		$wpdb->delete(
			$table,
			array(
				'blog_id'     => $blog,
				'object_id'   => $id,
				'object_type' => $type,
			)
		);
		return true;
	}
	$text   = wp_strip_all_tags( $text );
	$hash   = hash( 'sha256', $text );
	$model  = expert_config()['embedding_model'];
	$stored = $wpdb->get_row( $wpdb->prepare( "SELECT content_hash,embedding_model FROM $table WHERE blog_id=%d AND object_id=%d AND object_type=%s LIMIT 1", $blog, $id, $type ) );
	if ( $stored && $stored->content_hash === $hash && $stored->embedding_model === $model ) {
		return true;
	}
	$rows = array();
	foreach ( array_slice( mb_str_split( $text, 1400 ), 0, expert_limits()['chunks'] ) as $chunk => $chunk_text ) {
		$vector = expert_vector( $chunk_text );
		if ( is_wp_error( $vector ) ) {
			return $vector;
		}
		$rows[] = array(
			'blog_id'         => $blog,
			'object_id'       => $id,
			'object_type'     => $type,
			'chunk_index'     => $chunk,
			'chunk_text'      => $chunk_text,
			'content_hash'    => $hash,
			'embedding_json'  => wp_json_encode( $vector ),
			'embedding_model' => $model,
			'updated_at'      => current_time( 'mysql', true ),
		);
	}
	$wpdb->query( 'START TRANSACTION' );
	$wpdb->delete(
		$table,
		array(
			'blog_id'     => $blog,
			'object_id'   => $id,
			'object_type' => $type,
		)
	);
	foreach ( $rows as $row ) {
		if ( false === $wpdb->insert( $table, $row ) ) {
			$wpdb->query( 'ROLLBACK' );
			return expert_error( 'index_write' );
		}
	}
	$wpdb->query( 'COMMIT' );
	return true;
}
function expert_retrieve( $query, $types = array( 'post', 'expert_source', 'expert_faq', 'comment', 'expert_subject' ), $limit = 6, $minimum = 0.3 ) {
	global $wpdb;
	$vector = expert_vector( $query );
	if ( is_wp_error( $vector ) ) {
		return $vector;
	}
	$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}expert_embeddings WHERE blog_id=%d AND embedding_model=%s ORDER BY updated_at DESC,id DESC LIMIT %d", get_current_blog_id(), expert_config()['embedding_model'], expert_limits()['vectors'] ), ARRAY_A );
	$results = array();
	foreach ( $rows as $row ) {
		if ( ! in_array( $row['object_type'], $types, true ) ) {
			continue;
		}
		$comment = 'comment' === $row['object_type'] ? get_comment( $row['object_id'] ) : null;
		$post    = get_post( $comment ? $comment->comment_post_ID : $row['object_id'] );
		if ( ! $post || 'publish' !== $post->post_status || $post->post_password || ( 'comment' === $row['object_type'] && ( ! $comment || '1' !== $comment->comment_approved ) ) ) {
			continue;
		}
		$embedding = json_decode( $row['embedding_json'], true );
		$score     = is_array( $embedding ) ? expert_cosine( $vector, $embedding ) : 0;
		if ( $score < $minimum ) {
			continue;
		}
		$key = $row['object_type'] . ':' . $row['object_id'];
		if ( isset( $results[ $key ] ) && $results[ $key ]['score'] >= $score ) {
			continue;
		}
		// Fresh content, not cached chunk text, prevents stale-index disclosure after edits.
		$results[ $key ] = array(
			'id'       => (int) $row['object_id'],
			'type'     => $row['object_type'],
			'title'    => get_the_title( $post ),
			'text'     => mb_substr( wp_strip_all_tags( $comment ? $comment->comment_content : $post->post_content ), 0, 2400 ),
			'url'      => 'expert_subject' === $post->post_type ? home_url( '/' ) : ( $comment ? get_comment_link( $comment ) : get_permalink( $post ) ),
			'taxonomy' => wp_get_post_terms( $post->ID, array( 'category', 'post_tag' ), array( 'fields' => 'names' ) ),
			'score'    => $score,
		);
	}
	usort( $results, static fn( $a, $b ) => $b['score'] <=> $a['score'] );
	return array_slice( $results, 0, min( 10, $limit ) );
}
function expert_queue_index( $id ) {
	if ( ! expert_is_agent() || wp_is_post_revision( $id ) || ! in_array( get_post_type( $id ), array( 'post', 'expert_source', 'expert_faq', 'expert_research', 'expert_subject' ), true ) ) {
		return;
	}
	if ( ! wp_next_scheduled( 'expert_index_post', array( $id ) ) ) {
		wp_schedule_single_event( time() + 10, 'expert_index_post', array( $id ) );
	}
}
add_action( 'save_post', 'expert_queue_index' );
function expert_index_job( $id ) {
	if ( ! expert_is_agent() ) {
		return;
	}
	$result = expert_index( $id );
	if ( is_wp_error( $result ) ) {
		update_option( 'expert_index_error', $result->get_error_code(), false );
	}
}
add_action( 'expert_index_post', 'expert_index_job' );

function expert_comment_index_change( $id ) {
	if ( ! expert_is_agent() ) {
		return;
	}
	// Index approved human discussion as well as autonomous reflections.
	if ( ! wp_next_scheduled( 'expert_index_comment', array( (int) $id ) ) ) {
		wp_schedule_single_event( time() + 10, 'expert_index_comment', array( (int) $id ) );
	}
}
function expert_index_comment_job( $id ) {
	if ( expert_is_agent() ) {
		expert_index( $id, 'comment' );
	} }
add_action( 'comment_post', 'expert_comment_index_change' );
add_action( 'edit_comment', 'expert_comment_index_change' );
add_action( 'wp_set_comment_status', 'expert_comment_index_change' );
add_action( 'expert_index_comment', 'expert_index_comment_job' );

/** Rebuild model-specific vectors in small native cron batches. */
function expert_check_index_model() {
	if ( ! expert_is_agent() ) {
		return;
	}
	$model = expert_config()['embedding_model'];
	if ( $model && get_option( 'expert_index_model' ) !== $model ) {
		update_option( 'expert_index_model', $model, false );
		update_option( 'expert_index_page', 1, false );
		if ( ! wp_next_scheduled( 'expert_reindex_batch' ) ) {
			wp_schedule_single_event( time() + 30, 'expert_reindex_batch' );
		}
	}
}
add_action( 'init', 'expert_check_index_model', 30 );
function expert_reindex_batch() {
	if ( ! expert_is_agent() ) {
		return;
	}
	$token = expert_lock( 'index_batch', 180 );
	if ( ! $token ) {
		return;
	}
	$page  = max( 1, (int) get_option( 'expert_index_page', 1 ) );
	$retry = false;
	try {
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'expert_source', 'expert_faq', 'expert_research', 'expert_subject' ),
				'post_status'    => 'publish',
				'posts_per_page' => 3,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		foreach ( $posts as $post ) {
			$result = expert_index( $post->ID );
			if ( is_wp_error( $result ) ) {
				$retry = true;
				update_option( 'expert_index_error', $result->get_error_code(), false );
				break;
			}
		}
		if ( ! $retry ) {
			update_option( 'expert_index_page', $page + 1, false );
			delete_option( 'expert_index_error' );
		}
		if ( $retry || count( $posts ) === 3 ) {
			wp_schedule_single_event( time() + ( $retry ? 600 : 60 ), 'expert_reindex_batch' );
		}
	} finally {
		expert_unlock( 'index_batch', $token );
	}
}
add_action( 'expert_reindex_batch', 'expert_reindex_batch' );
