<?php
/** One bounded two-source research cycle. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_source_duplicate( $source ) {
	$posts = get_posts(
		array(
			'post_type'   => 'expert_source',
			'post_status' => 'any',
			'numberposts' => 1,
			'meta_query'  => array(
				'relation' => 'OR',
				array(
					'key'   => '_expert_canonical',
					'value' => $source['canonical'],
				),
				array(
					'key'   => '_expert_hash',
					'value' => $source['hash'],
				),
			),
		)
	);
	if ( $posts ) {
		return $posts[0]->ID;
	}
	return expert_equivalent( $source['title'], 'expert_source', 0.96 );
}
function expert_research_cycle() {
	$agent   = get_option( 'expert_agent' );
	$backlog = get_posts(
		array(
			'post_type'   => 'expert_research',
			'numberposts' => 3,
			'meta_key'    => '_expert_priority',
			'orderby'     => 'meta_value_num',
			'order'       => 'DESC',
			'meta_query'  => array(
				array(
					'key'     => '_expert_status',
					'value'   => array( 'Candidate', 'Queued', 'Deferred' ),
					'compare' => 'IN',
				),
			),
		)
	);
	$recent  = get_posts( array( 'numberposts' => 4 ) );
	$terms   = get_terms(
		array(
			'taxonomy'   => array( 'category', 'post_tag' ),
			'number'     => 40,
			'hide_empty' => false,
			'fields'     => 'names',
		)
	);
	global $wpdb;
	$popular = $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM {$wpdb->base_prefix}expert_engagement WHERE blog_id=%d AND object_id>0 AND hour>=%d GROUP BY object_id ORDER BY SUM(score) DESC LIMIT 3", get_current_blog_id(), (int) floor( time() / 3600 ) - 24 ) );
	$plan    = expert_generate(
		'research_focus',
		array(
			'area'     => $agent['area'],
			'subject'  => get_post_field( 'post_content', $agent['subject'] ),
			'backlog'  => wp_list_pluck( $backlog, 'post_title', 'ID' ),
			'recent'   => wp_list_pluck( $recent, 'post_title' ),
			'popular'  => array_map( 'get_the_title', $popular ),
			'taxonomy' => is_wp_error( $terms ) ? array() : $terms,
		),
		array(
			'topic'      => 'string; one useful subtopic within area',
			'reason'     => 'string; public rationale',
			'backlog_id' => 'integer; selected supplied backlog ID or 0',
		)
	);
	if ( is_wp_error( $plan ) ) {
		throw new RuntimeException( $plan->get_error_code() );
	}
	$topic = mb_substr( sanitize_text_field( $plan['topic'] ?? '' ), 0, 300 );
	if ( ! $topic ) {
		throw new RuntimeException( 'empty_topic' );
	}
	$GLOBALS['expert_topic'] = $topic;
	$backlog_id              = (int) ( $plan['backlog_id'] ?? 0 );
	if ( ! in_array( $backlog_id, array_map( 'intval', wp_list_pluck( $backlog, 'ID' ) ), true ) ) {
		$backlog_id = 0;
	}
	if ( $backlog_id ) {
		update_post_meta( $backlog_id, '_expert_status', 'Researching' );
		update_post_meta( $backlog_id, '_expert_last_considered', gmdate( 'c' ) );
	}
	expert_event( 'research_started', $backlog_id, $topic . ': ' . sanitize_text_field( $plan['reason'] ?? '' ) );
	try {
		$candidates = expert_discover( $topic );
		if ( is_wp_error( $candidates ) ) {
			throw new RuntimeException( $candidates->get_error_code() );
		}
		$sources = array();
		$ids     = array();
		foreach ( $candidates as $candidate ) {
			if ( count( $sources ) >= 2 ) {
				break;
			}
			if ( ! is_string( $candidate['url'] ?? null ) || ! expert_public_url( $candidate['url'] ) ) {
				continue;
			}
			$normal = expert_normalise_url( $candidate['url'] );
			$known  = get_posts(
				array(
					'post_type'   => 'expert_source',
					'numberposts' => 1,
					'meta_key'    => '_expert_canonical',
					'meta_value'  => $normal,
				)
			);
			if ( $known ) {
				continue;
			}
			$source = expert_fetch_source( $candidate['url'] );
			if ( is_wp_error( $source ) ) {
				expert_event( 'source_skipped', 0, $source->get_error_code(), 'System' );
				continue;
			}
			if ( expert_source_duplicate( $source ) ) {
				continue;
			}
			$assessment = expert_generate(
				'source_assessment',
				array(
					'area'   => $agent['area'],
					'topic'  => $topic,
					'source' => $source,
				),
				array(
					'credible'        => 'boolean; false for spam, syndication, unverifiable publisher or weak claims',
					'relevant'        => 'boolean',
					'classification'  => 'primary|academic|institutional|journalism|weak',
					'primary'         => 'boolean',
					'original_report' => 'string; original evidence/publisher identity',
					'summary'         => 'string; original notes, max 120 words, no long quotations',
					'claims'          => 'array of concise original claim summaries',
				)
			);
			if ( is_wp_error( $assessment ) ) {
				throw new RuntimeException( $assessment->get_error_code() );
			}
			if ( true !== ( $assessment['credible'] ?? false ) || true !== ( $assessment['relevant'] ?? false ) || ! in_array( $assessment['classification'] ?? '', array( 'primary', 'academic', 'institutional', 'journalism' ), true ) || empty( $assessment['summary'] ) ) {
				continue;
			}
			$source = array_merge( $source, $assessment );
			// Persist only bounded original notes, never the fetched article body.
			expert_budget( 'sources' );
			$id = expert_save_knowledge( 'expert_source', $source['title'], wp_trim_words( sanitize_textarea_field( $source['summary'] ), 120 ) );
			if ( is_wp_error( $id ) ) {
				throw new RuntimeException( $id->get_error_code() );
			}
			foreach ( array( 'url', 'canonical', 'publisher', 'author', 'published', 'retrieved', 'type', 'hash', 'classification', 'primary', 'original_report' ) as $key ) {
				update_post_meta( $id, '_expert_' . $key, sanitize_text_field( (string) ( $source[ $key ] ?? '' ) ) );
			}
			update_post_meta( $id, '_expert_claims', array_map( 'sanitize_text_field', array_slice( (array) ( $source['claims'] ?? array() ), 0, 5 ) ) );
			update_post_meta( $id, '_expert_topic', $topic );
			unset( $source['text'] );
			$sources[] = $source;
			$ids[]     = $id;
		}
		if ( count( $sources ) < 2 ) {
			expert_backlog( $topic, 'weak_evidence', $ids );
			return 'incomplete';
		}
		$existing = expert_retrieve( $topic );
		if ( is_wp_error( $existing ) ) {
			throw new RuntimeException( $existing->get_error_code() );
		}
		$analysis = expert_generate(
			'two_source_synthesis',
			array(
				'area'               => $agent['area'],
				'topic'              => $topic,
				'sources'            => $sources,
				'existing_knowledge' => $existing,
			),
			array(
				'independent'      => 'boolean; false for same publisher, syndication, common underlying report or circular references',
				'justified'        => 'boolean; genuinely new perspective supported by BOTH sources',
				'agreement'        => 'string',
				'difference'       => 'string',
				'uncertainty'      => 'string',
				'title'            => 'string',
				'post'             => 'string; original considered perspective beyond paraphrase; no URLs',
				'categories'       => 'array; max 2',
				'tags'             => 'array; max 2',
				'subject_changed'  => 'boolean',
				'subject'          => 'string; evolving understanding',
				'backlog_answered' => 'boolean',
			)
		);
		if ( is_wp_error( $analysis ) ) {
			throw new RuntimeException( $analysis->get_error_code() );
		}
		$same_publisher = strtolower( $sources[0]['publisher'] ) === strtolower( $sources[1]['publisher'] );
		$same_origin    = wp_parse_url( $sources[0]['canonical'], PHP_URL_HOST ) === wp_parse_url( $sources[1]['canonical'], PHP_URL_HOST );
		$same_report    = ! empty( $sources[0]['original_report'] ) && strtolower( $sources[0]['original_report'] ) === strtolower( $sources[1]['original_report'] );
		if ( $same_publisher || $same_origin || $same_report || true !== ( $analysis['independent'] ?? false ) || true !== ( $analysis['justified'] ?? false ) || empty( $analysis['post'] ) || empty( $analysis['title'] ) ) {
			expert_backlog( $topic, 'insufficient_independence', $ids );
			return 'incomplete';
		}
		expert_budget( 'posts' );
		$content = sanitize_textarea_field( $analysis['post'] ) . "\n\n" . __( 'Where the evidence agrees:', 'expert' ) . ' ' . sanitize_textarea_field( $analysis['agreement'] ?? '' ) . "\n\n" . __( 'Differences:', 'expert' ) . ' ' . sanitize_textarea_field( $analysis['difference'] ?? '' ) . "\n\n" . __( 'Uncertainty:', 'expert' ) . ' ' . sanitize_textarea_field( $analysis['uncertainty'] ?? '' );
		$post    = expert_save_knowledge( 'post', $analysis['title'], $content, $ids );
		if ( is_wp_error( $post ) ) {
			throw new RuntimeException( $post->get_error_code() );
		}
		expert_assign_terms( $post, $analysis['categories'] ?? array(), 'category' );
		expert_assign_terms( $post, $analysis['tags'] ?? array(), 'post_tag' );
		foreach ( $ids as $id ) {
			foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
					wp_set_object_terms( $id, wp_get_object_terms( $post, $taxonomy, array( 'fields' => 'ids' ) ), $taxonomy );
			}
		}
		// Draft mode keeps follow-up publication behind the same review boundary.
		if ( 'publish' === get_post_status( $post ) ) {
			expert_reflect( $content, $ids, $post );
			if ( true === ( $analysis['subject_changed'] ?? false ) && ! empty( $analysis['subject'] ) ) {
				expert_save_knowledge( 'expert_subject', $agent['name'], sanitize_textarea_field( $analysis['subject'] ), $ids, $agent['subject'] );
			}
			$faqs = expert_retrieve( $topic, array( 'expert_faq' ), 1, 0.65 );
			if ( ! is_wp_error( $faqs ) && $faqs ) {
				$revision = expert_generate(
					'faq_review',
					array(
						'faq'      => $faqs[0],
						'evidence' => $content,
					),
					array(
						'material' => 'boolean',
						'answer'   => 'string; evidence grounded',
					)
				);
				if ( ! is_wp_error( $revision ) && true === ( $revision['material'] ?? false ) && ! empty( $revision['answer'] ) ) {
					expert_faq( $faqs[0]['title'], sanitize_textarea_field( $revision['answer'] ), $ids );
				}
			}
			if ( $backlog_id && true === ( $analysis['backlog_answered'] ?? false ) ) {
				update_post_meta( $backlog_id, '_expert_status', 'Answered' );
				update_post_meta( $backlog_id, '_expert_related', array( $post ) );
				expert_event( 'backlog_answered', $backlog_id, __( 'Research produced a supported answer.', 'expert' ), 'Agent', $ids );
			}
		}
		return 'completed';
	} finally {
		if ( $backlog_id && 'Researching' === get_post_meta( $backlog_id, '_expert_status', true ) ) {
			update_post_meta( $backlog_id, '_expert_status', 'Deferred' );
			expert_event( 'backlog_deferred', $backlog_id, __( 'Further evidence or publication review is needed.', 'expert' ) );
		}
	}
}
