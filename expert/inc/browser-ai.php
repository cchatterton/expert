<?php
/** Browser-local inference jobs with WordPress-owned, durable memory. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function expert_browser_ai_enabled() {
	return true;
}

function expert_browser_evidence( $query = '', $limit = 6 ) {
	$args = array(
		'post_type'      => array( 'post', 'expert_source', 'expert_faq', 'expert_subject' ),
		'post_status'    => 'publish',
		'posts_per_page' => min( 12, max( 1, (int) $limit ) ),
		'orderby'        => 'modified',
		'order'          => 'DESC',
	);
	if ( trim( $query ) ) {
		$args['s'] = mb_substr( sanitize_text_field( $query ), 0, 300 );
	}
	$posts = get_posts( $args );
	if ( ! $posts && trim( $query ) ) {
		unset( $args['s'] );
		$posts = get_posts( $args );
	}
	return array_map(
		static function ( $post ) {
			$url = 'expert_source' === $post->post_type ? get_post_meta( $post->ID, '_expert_url', true ) : get_permalink( $post );
			return array(
				'id'    => (int) $post->ID,
				'type'  => $post->post_type,
				'title' => get_the_title( $post ),
				'text'  => mb_substr( wp_strip_all_tags( $post->post_content ), 0, 900 ),
				'url'   => esc_url_raw( $url ?: get_permalink( $post ) ),
			);
		},
		$posts
	);
}

function expert_browser_token( $purpose, $payload ) {
	$token = wp_generate_password( 32, false, false );
	set_transient( 'expert_browser_' . sanitize_key( $purpose ) . '_' . get_current_user_id() . '_' . hash( 'sha256', $token ), $payload, 15 * MINUTE_IN_SECONDS );
	return $token;
}

function expert_browser_claim_token( $purpose, $token ) {
	$key     = 'expert_browser_' . sanitize_key( $purpose ) . '_' . get_current_user_id() . '_' . hash( 'sha256', (string) $token );
	$payload = get_transient( $key );
	delete_transient( $key );
	return is_array( $payload ) ? $payload : false;
}

function expert_browser_heartbeat_request() {
	update_option( 'expert_browser_station', array( 'user' => get_current_user_id(), 'seen' => time() ), false );
	return array( 'ok' => true, 'server_time' => time() );
}

function expert_browser_chat_context_request( $request ) {
	if ( ! expert_rate_limit() ) {
		return new WP_Error( 'rate_limit', __( 'Please wait a minute before trying again.', 'expert' ), array( 'status' => 429 ) );
	}
	$question = mb_substr( sanitize_textarea_field( $request['message'] ), 0, 1000 );
	$agent    = get_option( 'expert_agent' );
	$evidence = expert_browser_evidence( $question );
	$token    = expert_browser_token( 'chat', array( 'question' => $question, 'evidence' => $evidence ) );
	expert_engage( 'chat' );
	return array(
		'token'    => $token,
		'area'     => $agent['area'],
		'question' => $question,
		'evidence' => $evidence,
	);
}

function expert_browser_chat_commit_request( $request ) {
	$issued = expert_browser_claim_token( 'chat', $request['token'] );
	if ( ! $issued ) {
		return new WP_Error( 'expired_job', __( 'This local-AI request expired. Please ask again.', 'expert' ), array( 'status' => 409 ) );
	}
	$result    = is_array( $request['result'] ) ? $request['result'] : array();
	$citations = array();
	foreach ( array_slice( (array) ( $result['citations'] ?? array() ), 0, 6 ) as $index ) {
		$index = absint( $index );
		if ( isset( $issued['evidence'][ $index ] ) ) {
			$citations[] = $issued['evidence'][ $index ];
		}
	}
	$answer   = mb_substr( sanitize_textarea_field( $result['answer'] ?? '' ), 0, 7000 );
	$grounded = ! empty( $result['grounded'] ) && $answer && $citations;
	if ( ! $grounded ) {
		if ( ! empty( $result['useful'] ) ) {
			expert_browser_backlog( $issued['question'] );
		}
		return array( 'answer' => __( 'I do not yet have enough supported knowledge to answer that. I added this useful question to my backlog.', 'expert' ), 'sources' => array() );
	}
	if ( ! empty( $result['reusable'] ) ) {
		expert_browser_save_faq( $issued['question'], $answer, wp_list_pluck( $citations, 'id' ) );
	}
	return array( 'answer' => $answer, 'sources' => $citations );
}

function expert_browser_backlog( $question ) {
	$existing = get_posts( array( 'post_type' => 'expert_research', 'post_status' => 'publish', 'title' => $question, 'numberposts' => 1 ) );
	if ( $existing ) {
		$demand = 1 + (int) get_post_meta( $existing[0]->ID, '_expert_demand', true );
		update_post_meta( $existing[0]->ID, '_expert_demand', $demand );
		update_post_meta( $existing[0]->ID, '_expert_priority', min( 100, $demand * 5 ) );
		return $existing[0]->ID;
	}
	$agent = get_option( 'expert_agent' );
	$id    = wp_insert_post( array( 'post_type' => 'expert_research', 'post_status' => 'publish', 'post_title' => $question, 'post_content' => __( 'An open research question, not established knowledge.', 'expert' ), 'post_author' => $agent['user'] ), true );
	if ( ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_expert_status', 'Candidate' );
		update_post_meta( $id, '_expert_demand', 1 );
		update_post_meta( $id, '_expert_priority', 5 );
		expert_event( 'backlog_created', $id, __( 'A useful unanswered question was added to the backlog.', 'expert' ) );
	}
	return $id;
}

function expert_browser_save_faq( $question, $answer, $sources ) {
	$existing = get_posts( array( 'post_type' => 'expert_faq', 'post_status' => 'publish', 'title' => $question, 'numberposts' => 1 ) );
	if ( $existing && get_post_meta( $existing[0]->ID, '_expert_human', true ) ) {
		return $existing[0]->ID;
	}
	$agent = get_option( 'expert_agent' );
	$args  = array( 'post_type' => 'expert_faq', 'post_status' => 'publish', 'post_title' => $question, 'post_content' => $answer, 'post_author' => $agent['user'], 'meta_input' => array( '_expert_authored' => 1, '_expert_sources' => array_map( 'absint', $sources ) ) );
	if ( $existing ) {
		$args['ID'] = $existing[0]->ID;
		wp_save_post_revision( $args['ID'] );
	}
	$id = wp_insert_post( $args, true );
	if ( ! is_wp_error( $id ) ) {
		expert_event( $existing ? 'knowledge_updated' : 'knowledge_created', $id, __( 'A locally generated, evidence-grounded FAQ was saved.', 'expert' ), 'Agent', $sources );
	}
	return $id;
}

function expert_browser_learning_context() {
	$agent    = get_option( 'expert_agent' );
	$backlog  = get_posts( array( 'post_type' => 'expert_research', 'post_status' => 'publish', 'numberposts' => 3, 'meta_key' => '_expert_priority', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) );
	$evidence = expert_browser_evidence( '', 10 );
	return array(
		'area'       => $agent['area'],
		'subject'    => wp_strip_all_tags( get_post_field( 'post_content', $agent['subject'] ) ),
		'backlog'    => array_map( static fn( $post ) => array( 'id' => (int) $post->ID, 'question' => $post->post_title ), $backlog ),
		'evidence'   => $evidence,
		'agent_name' => $agent['name'],
	);
}

function expert_browser_learn_claim_request() {
	$state = expert_state();
	if ( $state['paused'] || $state['next_loop'] > time() ) {
		return array( 'job' => false, 'next' => $state['next_loop'] );
	}
	$lock = expert_lock( 'browser_learning', 15 * MINUTE_IN_SECONDS );
	if ( ! $lock ) {
		return array( 'job' => false, 'busy' => true );
	}
	$context = expert_browser_learning_context();
	if ( count( $context['evidence'] ) < 2 ) {
		expert_unlock( 'browser_learning', $lock );
		return array( 'job' => false, 'needs_sources' => true );
	}
	$token = expert_browser_token( 'learn', array( 'lock' => $lock, 'context' => $context, 'started' => time() ) );
	return array( 'job' => true, 'token' => $token, 'context' => $context );
}

function expert_browser_learn_commit_request( $request ) {
	$issued = expert_browser_claim_token( 'learn', $request['token'] );
	if ( ! $issued ) {
		return new WP_Error( 'expired_job', __( 'This learning job expired.', 'expert' ), array( 'status' => 409 ) );
	}
	try {
		$result   = is_array( $request['result'] ) ? $request['result'] : array();
		$context  = $issued['context'];
		$allowed  = array_map( 'intval', wp_list_pluck( $context['evidence'], 'id' ) );
		$sources  = array_values( array_intersect( $allowed, array_map( 'absint', (array) ( $result['source_ids'] ?? array() ) ) ) );
		$title    = mb_substr( sanitize_text_field( $result['title'] ?? '' ), 0, 200 );
		$content  = mb_substr( sanitize_textarea_field( $result['post'] ?? '' ), 0, 12000 );
		$grounded = ! empty( $result['grounded'] ) && count( $sources ) >= 2 && $title && $content;
		$status   = 'incomplete';
		$error    = 'insufficient_evidence';
		if ( $grounded ) {
			$GLOBALS['expert_loop_uuid'] = wp_generate_uuid4();
			$id = expert_save_knowledge( 'post', $title, $content, $sources );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$subject = mb_substr( sanitize_textarea_field( $result['subject'] ?? '' ), 0, 6000 );
			if ( $subject && $subject !== $context['subject'] ) {
				expert_save_knowledge( 'expert_subject', $context['agent_name'], $subject, $sources, get_option( 'expert_agent' )['subject'] );
			}
			$backlog_id = absint( $result['backlog_id'] ?? 0 );
			if ( in_array( $backlog_id, array_map( 'intval', wp_list_pluck( $context['backlog'], 'id' ) ), true ) ) {
				update_post_meta( $backlog_id, '_expert_status', 'Answered' );
				update_post_meta( $backlog_id, '_expert_related', array( $id ) );
			}
			$status = 'completed';
			$error  = '';
		}
		expert_browser_finish_loop( $issued['started'], $status, $error, sanitize_text_field( $result['topic'] ?? '' ) );
		return array( 'saved' => 'completed' === $status, 'status' => $status );
	} finally {
		expert_unlock( 'browser_learning', $issued['lock'] );
		unset( $GLOBALS['expert_loop_uuid'] );
	}
}

function expert_browser_finish_loop( $started, $status, $error, $topic ) {
	global $wpdb;
	$state = expert_state();
	$state['failures'] = 'completed' === $status ? 0 : $state['failures'] + 1;
	$state['last_loop'] = time();
	$state['next_loop'] = time() + max( 600, (int) $state['interval'] );
	update_option( 'expert_state', $state, false );
	$wpdb->insert( $wpdb->base_prefix . 'expert_loops', array( 'blog_id' => get_current_blog_id(), 'loop_uuid' => $GLOBALS['expert_loop_uuid'] ?? wp_generate_uuid4(), 'mode' => $state['mode'], 'started_at' => gmdate( 'Y-m-d H:i:s', $started ), 'completed_at' => gmdate( 'Y-m-d H:i:s' ), 'duration_seconds' => time() - $started, 'scheduled_interval_seconds' => $state['interval'], 'engagement_score' => $state['average'], 'engagement_per_hour' => $state['per_hour'], 'knowledge_piece_count' => expert_knowledge_count(), 'research_topic' => mb_substr( $topic, 0, 500 ), 'sources_added' => 0, 'posts_added' => 'completed' === $status ? 1 : 0, 'comments_added' => 0, 'faqs_added' => 0, 'backlog_added' => 0, 'status' => $status, 'error_code' => $error ) );
	expert_event( 'loop_' . $status, 0, 'completed' === $status ? __( 'A browser-local learning cycle added a supported perspective.', 'expert' ) : __( 'The local model found insufficient stored evidence. Add sources or backlog questions before the next cycle.', 'expert' ), 'System' );
}
