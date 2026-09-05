<?php
/** Knowledge persistence, semantic reuse and bounded reflections. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_save_knowledge( $type, $title, $text, $sources = array(), $id = 0 ) {
	$agent  = get_option( 'expert_agent' );
	$status = 'post' === $type ? ( $agent['publishing'] ?? expert_config()['publishing'] ) : 'publish';
	$args   = array(
		'post_type'    => $type,
		'post_title'   => sanitize_text_field( $title ),
		'post_content' => wp_kses_post( $text ),
		'post_status'  => 'publish' === $status ? 'publish' : 'draft',
		'post_author'  => $agent['user'],
		'meta_input'   => array(
			'_expert_authored' => 1,
			'_expert_sources'  => array_map( 'absint', $sources ),
			'_expert_loop'     => $GLOBALS['expert_loop_uuid'] ?? '',
		),
	);
	if ( $id ) {
		// AI never overwrites a human-edited asset; reflection preserves both perspectives.
		if ( get_post_meta( $id, '_expert_human', true ) ) {
			return expert_error( 'human_edit_preserved' );
		}
		$args['ID'] = $id;
		wp_save_post_revision( $id );
	}
	$saved = wp_insert_post( $args, true );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	if ( ! $id && isset( $GLOBALS['expert_counts'] ) ) {
		$counter = array(
			'post'            => 'posts',
			'expert_source'   => 'sources',
			'expert_faq'      => 'faqs',
			'expert_research' => 'backlog',
		)[ $type ] ?? '';
		if ( $counter ) {
			$GLOBALS['expert_counts'][ $counter ] = 1 + ( $GLOBALS['expert_counts'][ $counter ] ?? 0 );
		}
	}
	expert_event( $id ? 'knowledge_updated' : 'knowledge_created', $saved, $id ? __( 'Updated in light of new evidence; revisions retained.', 'expert' ) : __( 'Added to the Agent knowledge base.', 'expert' ), 'Agent', $sources );
	$indexed = expert_index( $saved );
	if ( is_wp_error( $indexed ) ) {
		expert_event( 'index_failed', $saved, $indexed->get_error_code(), 'System' );
	}
	return $saved;
}
function expert_equivalent( $question, $type, $threshold = 0.9 ) {
	$exact = get_posts(
		array(
			'post_type'   => $type,
			'post_status' => 'publish',
			'title'       => $question,
			'numberposts' => 1,
		)
	);
	if ( $exact ) {
		return $exact[0]->ID;
	}
	$matches = expert_retrieve( $question, array( $type ), 1, $threshold );
	return is_wp_error( $matches ) || ! $matches ? 0 : $matches[0]['id'];
}
function expert_backlog_unlocked( $question, $origin = 'chat', $related = array() ) {
	expert_budget( 'backlog' );
	$question = mb_substr( sanitize_text_field( $question ), 0, 500 );
	$id       = expert_equivalent( $question, 'expert_research' );
	if ( $id ) {
		$demand = 1 + (int) get_post_meta( $id, '_expert_demand', true );
		update_post_meta( $id, '_expert_demand', $demand );
		update_post_meta( $id, '_expert_priority', min( 100, $demand * 5 ) );
		if ( 'Answered' === get_post_meta( $id, '_expert_status', true ) ) {
			update_post_meta( $id, '_expert_status', 'Queued' );
		}
		expert_event( 'backlog_demand', $id, __( 'A related question increased research demand.', 'expert' ) );
		return $id;
	}
	$id = expert_save_knowledge( 'expert_research', $question, __( 'An open research question, not established knowledge.', 'expert' ) );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	foreach ( array(
		'origin'          => $origin,
		'created'         => gmdate( 'c' ),
		'demand'          => 1,
		'priority'        => 5,
		'status'          => 'Candidate',
		'last_considered' => '',
		'related'         => $related,
	) as $key => $value ) {
		update_post_meta( $id, '_expert_' . $key, $value );
	}
	return $id;
}
function expert_faq_unlocked( $question, $answer, $sources ) {
	expert_budget( 'faqs' );
	$id = expert_equivalent( $question, 'expert_faq' );
	if ( $id && trim( wp_strip_all_tags( get_post_field( 'post_content', $id ) ) ) === trim( $answer ) ) {
		return $id;
	}
	return expert_save_knowledge( 'expert_faq', $question, $answer, $sources, $id );
}
function expert_assign_terms( $id, $names, $taxonomy ) {
	$names = array_slice( is_array( $names ) ? $names : array(), 0, 2 );
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 100,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return;
	}
	$assigned = array();
	foreach ( $names as $name ) {
		$name = mb_substr( sanitize_text_field( $name ), 0, 80 );
		if ( ! $name ) {
			continue;
		}
		$existing = term_exists( $name, $taxonomy );
		if ( $existing ) {
			$assigned[] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
			continue;
		}
		$vector = expert_vector( $name );
		if ( is_wp_error( $vector ) ) {
			continue;
		}
		$match = 0;
		foreach ( $terms as $term ) {
			$cache = get_term_meta( $term->term_id, '_expert_vector', true );
			if ( ! is_array( $cache ) || ( $cache['model'] ?? '' ) !== expert_config()['embedding_model'] ) {
				// A bounded language comparison covers unindexed legacy taxonomy without 100 AI calls.
				continue;
			}
			if ( expert_cosine( $vector, $cache['vector'] ) >= 0.9 ) {
				$match = $term->term_id;
				break;
			}
		}
		if ( ! $match && $terms ) {
			$reuse = expert_generate(
				'taxonomy_equivalence',
				array(
					'candidate' => $name,
					'existing'  => array_map(
						static fn( $term ) => array(
							'id'   => $term->term_id,
							'name' => $term->name,
						),
						$terms
					),
				),
				array( 'reuse_id' => 'integer; 0 only if no semantic equivalent' )
			);
			if ( is_wp_error( $reuse ) ) {
				continue;
			}
			$candidate = (int) ( $reuse['reuse_id'] ?? 0 );
			if ( in_array( $candidate, array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) ), true ) ) {
				$match = $candidate;
			}
		}
		if ( ! $match ) {
			expert_budget( 'terms' );
			$created = wp_insert_term( $name, $taxonomy );
			if ( is_wp_error( $created ) ) {
				continue;
			}
			$match = $created['term_id'];
			update_term_meta(
				$match,
				'_expert_vector',
				array(
					'model'  => expert_config()['embedding_model'],
					'vector' => $vector,
				)
			);
			expert_event( 'taxonomy_created', $match, $name, 'Agent', array(), $taxonomy );
		}
		$assigned[] = (int) $match;
	}
	if ( $assigned ) {
		wp_set_object_terms( $id, $assigned, $taxonomy );
	}
}
function expert_reflect( $text, $sources = array(), $exclude = 0 ) {
	$related = expert_retrieve( $text, array( 'post' ), 3, 0.45 );
	if ( is_wp_error( $related ) || ! $related ) {
		return;
	}
	$agent = get_option( 'expert_agent' );
	$added = 0;
	foreach ( $related as $item ) {
		if ( $item['id'] === $exclude || $added >= 2 ) {
			continue;
		}
		$recent = get_comments(
			array(
				'post_id' => $item['id'],
				'status'  => 'approve',
				'number'  => 3,
			)
		);
		$result = expert_generate(
			'reflection',
			array(
				'area'               => $agent['area'],
				'new_evidence'       => mb_substr( $text, 0, 4000 ),
				'existing'           => $item,
				'recent_reflections' => wp_list_pluck( $recent, 'comment_content' ),
			),
			array(
				'material' => 'boolean; false for mere agreement',
				'comment'  => 'string; concise evidence-based qualification or challenge',
			)
		);
		if ( is_wp_error( $result ) || true !== ( $result['material'] ?? false ) || empty( $result['comment'] ) ) {
			continue;
		}
		$comment = mb_substr( sanitize_textarea_field( $result['comment'] ), 0, 3000 );
		$hash    = hash( 'sha256', $comment );
		if ( get_comments(
			array(
				'post_id'    => $item['id'],
				'meta_key'   => '_expert_hash',
				'meta_value' => $hash,
				'count'      => true,
			)
		) ) {
			continue;
		}
		expert_budget( 'comments' );
		$id = wp_insert_comment(
			array(
				'comment_post_ID'  => $item['id'],
				'comment_content'  => $comment,
				'comment_author'   => $agent['name'],
				'user_id'          => $agent['user'],
				'comment_approved' => ( $agent['publishing'] ?? expert_config()['publishing'] ) === 'publish' ? 1 : 0,
				'comment_meta'     => array(
					'_expert_hash'    => $hash,
					'_expert_sources' => $sources,
				),
			)
		);
		if ( ! $id ) {
			throw new RuntimeException( 'reflection_write' );
		}
		++$added;
		if ( isset( $GLOBALS['expert_counts'] ) ) {
			$GLOBALS['expert_counts']['comments'] = 1 + ( $GLOBALS['expert_counts']['comments'] ?? 0 );
		}
		expert_event( 'reflection_added', $id, __( 'New evidence qualified related thinking.', 'expert' ), 'Agent', $sources, 'comment' );
		expert_index( $id, 'comment' );
	}
}
function expert_human_reflect_job( $id ) {
	if ( ! expert_is_agent() || expert_state()['paused'] ) {
		return;
	}
	$token = expert_lock( 'loop' );
	if ( ! $token ) {
		wp_schedule_single_event( time() + 300, 'expert_human_reflect', array( $id ) );
		return;
	}
	$GLOBALS['expert_budget'] = array( 'deadline' => microtime( true ) + 90 );
	try {
		$post = get_post( $id );
		if ( $post && 'publish' === $post->post_status && ! $post->post_password ) {
			expert_index( $id );
			expert_reflect( $post->post_content, array( $id ), $id );
			expert_event( 'human_edit_reviewed', $id, __( 'Related knowledge reviewed; only material reflections retained.', 'expert' ) );
		}
	} catch ( Throwable $error ) {
		expert_event( 'reflection_failed', $id, sanitize_key( $error->getMessage() ), 'System' );
	} finally {
		unset( $GLOBALS['expert_budget'] );
		expert_unlock( 'loop', $token );
	}
}
add_action( 'expert_human_reflect', 'expert_human_reflect_job' );

/** Serialise semantic deduplication and demand increments across chat/research. */
function expert_backlog( $question, $origin = 'chat', $related = array() ) {
	$token = expert_lock( 'backlog_write', 120 );
	if ( ! $token ) {
		return expert_error( 'backlog_busy' ); }
	try {
		return expert_backlog_unlocked( $question, $origin, $related ); } finally {
		expert_unlock( 'backlog_write', $token ); }
}
function expert_faq( $question, $answer, $sources ) {
	$token = expert_lock( 'faq_write', 120 );
	if ( ! $token ) {
		return expert_error( 'faq_busy' ); }
	try {
		return expert_faq_unlocked( $question, $answer, $sources ); } finally {
		expert_unlock( 'faq_write', $token ); }
}
function expert_record_human_comment( $id ) {
	if ( ! expert_is_agent() ) {
		return; }
	expert_engage( 'comment', (int) get_comment( $id )->comment_post_ID );
	expert_event( 'human_comment', $id, __( 'A member submitted a comment for discussion.', 'expert' ), 'Human', array(), 'comment' );
}
add_action( 'comment_post', 'expert_record_human_comment' );
