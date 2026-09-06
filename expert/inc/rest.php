<?php
/** Purpose-specific member APIs, with independent capability checks for edits. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_member_permission( $request ) {
	if ( ! expert_network_member() || ! expert_is_agent() || ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
		return new WP_Error( 'expert_forbidden', __( 'Please sign in again through the core site.', 'expert' ), array( 'status' => 403 ) );
	}
	return true;
}
function expert_editor_permission( $request ) {
	$member = expert_member_permission( $request );
	if ( is_wp_error( $member ) ) {
		return $member;
	}
	$id = absint( $request['id'] );
	return $id && current_user_can( 'edit_post', $id ) ? true : new WP_Error( 'expert_edit_forbidden', __( 'You do not have permission to edit this item.', 'expert' ), array( 'status' => 403 ) );
}
function expert_rate_limit() {
	global $wpdb;
	$key   = 'rate_' . get_current_user_id();
	$token = expert_lock( $key, 5 );
	if ( ! $token ) {
		return false;
	}
	try {
		$rate = get_transient( 'expert_' . $key ) ?: array(
			'start' => time(),
			'count' => 0,
		);
		if ( time() - $rate['start'] > 60 ) {
			$rate = array(
				'start' => time(),
				'count' => 0,
			);
		}
		if ( $rate['count'] >= 6 ) {
			return false;
		}
		++$rate['count'];
		set_transient( 'expert_' . $key, $rate, 60 );
		return true;
	} finally {
		expert_unlock( $key, $token );
	}
}
function expert_routes() {
	foreach ( array(
		'chat'       => 'expert_chat_request',
		'search'     => 'expert_search_request',
		'engagement' => 'expert_engagement_request',
	) as $route => $callback ) {
		register_rest_route(
			'expert/v1',
			'/' . $route,
			array(
				'methods'             => 'POST',
				'callback'            => $callback,
				'permission_callback' => 'expert_member_permission',
				'args'                => array(
					'message' => array(
						'type'              => 'string',
						'required'          => 'engagement' !== $route,
						'minLength'         => 2,
						'maxLength'         => 1000,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
	}
	foreach ( array(
		'local/heartbeat'   => array( 'POST', 'expert_browser_heartbeat_request' ),
		'local/chat'        => array( 'POST', 'expert_browser_chat_context_request' ),
		'local/chat/commit' => array( 'POST', 'expert_browser_chat_commit_request' ),
		'local/learn'       => array( 'POST', 'expert_browser_learn_claim_request' ),
		'local/learn/commit'=> array( 'POST', 'expert_browser_learn_commit_request' ),
	) as $route => $handler ) {
		register_rest_route(
			'expert/v1',
			'/' . $route,
			array(
				'methods'             => $handler[0],
				'callback'            => $handler[1],
				'permission_callback' => 'expert_member_permission',
			)
		);
	}
	register_rest_route(
		'expert/v1',
		'/frontend-edit',
		array(
			'methods'             => 'POST',
			'callback'            => 'expert_edit_request',
			'permission_callback' => 'expert_editor_permission',
			'args'                => array(
				'id'       => array(
					'type'     => 'integer',
					'required' => true,
				),
				'content'  => array(
					'type'      => 'string',
					'required'  => true,
					'maxLength' => 30000,
				),
				'modified' => array(
					'type'     => 'string',
					'required' => true,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'expert_routes' );
function expert_search_request( $request ) {
	if ( ! expert_rate_limit() ) {
		return new WP_Error( 'rate_limit', __( 'Please wait a minute before trying again.', 'expert' ), array( 'status' => 429 ) );
	}
	$results = expert_browser_ai_enabled() ? expert_browser_evidence( $request['message'], 10 ) : expert_retrieve( $request['message'], array( 'post', 'expert_source', 'expert_faq', 'comment' ), 10 );
	if ( is_wp_error( $results ) ) {
		return $results;
	}
	expert_engage( 'search' );
	return array( 'results' => $results );
}
function expert_chat_request( $request ) {
	if ( ! expert_rate_limit() ) {
		return new WP_Error( 'rate_limit', __( 'Please wait a minute before trying again.', 'expert' ), array( 'status' => 429 ) );
	}
	$token = expert_lock( 'conversation', 100 );
	if ( ! $token ) {
		return expert_error( 'busy', __( 'The Agent is answering another question. Please try again shortly.', 'expert' ) );
	}
	$GLOBALS['expert_budget'] = array( 'deadline' => microtime( true ) + 90 );
	try {
		$question = $request['message'];
		$agent    = get_option( 'expert_agent' );
		$evidence = expert_retrieve( $question );
		if ( is_wp_error( $evidence ) ) {
			return $evidence;
		}
		expert_engage( 'chat' );
		$result = expert_generate(
			'grounded_answer',
			array(
				'area'     => $agent['area'],
				'question' => $question,
				'evidence' => $evidence,
			),
			array(
				'grounded'  => 'boolean; true only if supplied evidence supports the answer',
				'answer'    => 'string; no links or invented facts',
				'citations' => 'array of zero-based evidence indices actually supporting the answer',
				'useful'    => 'boolean; useful in-area research question',
				'reusable'  => 'boolean; durable FAQ value',
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$citations = array();
		foreach ( array_slice( (array) ( $result['citations'] ?? array() ), 0, 6 ) as $index ) {
			if ( is_int( $index ) && isset( $evidence[ $index ] ) ) {
					$citations[] = $evidence[ $index ];
			}
		}
		$grounded = true === ( $result['grounded'] ?? false ) && $citations && ! empty( $result['answer'] );
		if ( ! $grounded ) {
			if ( true === ( $result['useful'] ?? false ) ) {
				$saved = expert_backlog( $question );
				if ( is_wp_error( $saved ) ) {
							return $saved;
				}
			}
			return array(
				'answer'  => __( 'I do not yet have enough supported knowledge to answer that. Useful open questions are added to my research backlog.', 'expert' ),
				'sources' => array(),
			);
		}
		$answer = mb_substr( sanitize_textarea_field( $result['answer'] ), 0, 7000 );
		if ( true === ( $result['reusable'] ?? false ) ) {
			$faq = expert_faq( $question, $answer, array_column( array_filter( $citations, static fn( $citation ) => 'comment' !== $citation['type'] ), 'id' ) );
			if ( ! is_wp_error( $faq ) ) {
				update_post_meta(
					$faq,
					'_expert_citations',
					array_map(
						static fn( $citation ) => array(
							'id'   => $citation['id'],
							'type' => $citation['type'],
						),
						$citations
					)
				);
			}
			if ( is_wp_error( $faq ) && 'human_edit_preserved' !== $faq->get_error_code() ) {
				expert_event( 'faq_failed', 0, $faq->get_error_code(), 'System' );
			}
		}
		return array(
			'answer'  => $answer,
			'sources' => $citations,
		);
	} catch ( Throwable $error ) {
		return expert_error( 'answer_incomplete', __( 'The answer could not be completed within this request. Please try again.', 'expert' ) );
	} finally {
		unset( $GLOBALS['expert_budget'] );
		expert_unlock( 'conversation', $token );
	}
}
function expert_edit_request( $request ) {
	$id   = absint( $request['id'] );
	$post = get_post( $id );
	if ( ! $post || ! in_array( $post->post_type, array( 'post', 'expert_source', 'expert_faq', 'expert_subject' ), true ) ) {
		return expert_error( 'unsupported_edit' );
	}
	$token = expert_lock( 'edit_' . $id, 30 );
	if ( ! $token ) {
		return expert_error( 'edit_busy' );
	}
	try {
		if ( $request['modified'] !== $post->post_modified_gmt ) {
			return new WP_Error( 'edit_conflict', __( 'This content changed while you were editing. Reload it before saving.', 'expert' ), array( 'status' => 409 ) );
		}
		wp_save_post_revision( $id );
		$saved = wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => wp_kses_post( $request['content'] ),
			),
			true
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		update_post_meta( $id, '_expert_human', true );
		update_post_meta( $id, '_edit_last', get_current_user_id() );
		if ( 'expert_source' === $post->post_type ) {
			foreach ( array( 'publisher', 'author', 'published' ) as $field ) {
				if ( isset( $request[ $field ] ) ) {
							update_post_meta( $id, '_expert_' . $field, mb_substr( sanitize_text_field( $request[ $field ] ), 0, 200 ) );
				}
			}
		}
		expert_event( 'human_edit', $id, __( 'A human member saved a revision.', 'expert' ), 'Human' );
		expert_engage( 'edit', $id );
		if ( ! wp_next_scheduled( 'expert_human_reflect', array( $id ) ) ) {
			wp_schedule_single_event( time() + 10, 'expert_human_reflect', array( $id ) );
		}
		return array(
			'saved'    => true,
			'modified' => get_post_field( 'post_modified_gmt', $id ),
		);
	} finally {
		expert_unlock( 'edit_' . $id, $token );
	}
}
function expert_engagement_request( $request ) {
	if ( ! expert_rate_limit() ) {
		return expert_error( 'rate_limit' );
	}
	$id   = absint( $request['id'] );
	$post = get_post( $id );
	if ( $post && 'publish' === $post->post_status && ! $post->post_password ) {
		$key = 'expert_view_' . get_current_user_id() . '_' . $id;
		if ( ! get_transient( $key ) ) {
			expert_engage( 'expert_source' === $post->post_type ? 'source' : ( 'expert_faq' === $post->post_type ? 'faq' : 'view' ), $id );
			set_transient( $key, 1, 1800 );
		}
	}
	return array( 'recorded' => true );
}
