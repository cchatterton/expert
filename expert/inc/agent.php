<?php
/** Two-field Agent identity and limited network metadata. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_bootstrap_agent( $name, $area ) {
	if ( ! expert_active() || is_main_site() ) {
		return expert_error( 'not_subsite' );
	}
	$name = sanitize_text_field( $name );
	$area = sanitize_textarea_field( $area );
	if ( '' === $name || '' === $area || strlen( $name ) > 200 || strlen( $area ) > 2000 ) {
		return expert_error( 'invalid_identity' );
	}
	$agent = get_option( 'expert_agent' );
	if ( ! $agent ) {
		$login = 'expert-agent-' . get_current_network_id() . '-' . get_current_blog_id();
		$user  = get_user_by( 'login', $login );
		if ( $user ) {
			return expert_error( 'identity_collision', __( 'The reserved Agent account already exists. Choose a fresh site or resolve the account collision.', 'expert' ) );
		}
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 64, true, true ),
				'display_name' => $name,
				'role'         => 'subscriber',
				'description'  => 'Autonomous Expert identity; interactive login disabled.',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		update_user_meta( $user_id, 'expert_machine_identity', 1 );
		$subject = wp_insert_post(
			array(
				'post_type'    => 'expert_subject',
				'post_status'  => 'publish',
				'post_title'   => $name,
				'post_content' => $area,
				'post_author'  => $user_id,
			),
			true
		);
		if ( is_wp_error( $subject ) ) {
			return $subject;
		}
		$agent = array(
			'user'    => $user_id,
			'subject' => $subject,
		);
	}
	$agent['name'] = $name;
	$agent['area'] = $area;
	update_option( 'expert_agent', $agent, false );
	update_option( 'blogname', $name );
	$state = expert_state();
	if ( ! $state['next_loop'] ) {
		$state['interval']  = expert_config()['growth_interval'];
		$state['next_loop'] = time() + $state['interval'];
	}
	update_option( 'expert_state', $state, false );
	expert_event( 'agent_setup', 0, __( 'Agent identity and research boundary saved.', 'expert' ), 'Human' );
	return $agent;
}
function expert_block_machine_login( $user ) {
	return $user instanceof WP_User && get_user_meta( $user->ID, 'expert_machine_identity', true ) ? expert_error( 'machine_identity' ) : $user;
}
add_filter( 'authenticate', 'expert_block_machine_login', 99 );
function expert_knowledge_count() {
	$count = 0;
	foreach ( array( 'post', 'expert_source', 'expert_faq' ) as $type ) {
		$query  = new WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'meta_key'       => '_expert_authored',
				'meta_value'     => '1',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		$count += $query->found_posts;
	}
	return $count;
}
function expert_agent_sites( $public = false, $offset = 0, $count = 50 ) {
	$args = array(
		'network_id' => get_current_network_id(),
		'number'     => $count,
		'offset'     => $offset,
		'deleted'    => 0,
		'spam'       => 0,
		'archived'   => 0,
	);
	if ( $public ) {
		$args['public'] = 1;
	}
	return get_sites( $args );
}
function expert_directory( $offset = 0 ) {
	$result = array();
	foreach ( expert_agent_sites( true, $offset ) as $site ) {
		switch_to_blog( $site->blog_id );
		try {
			if ( ! expert_is_agent() ) {
				continue;
			}
			$agent    = get_option( 'expert_agent' );
			$state    = expert_state();
			$result[] = array(
				'name'        => $agent['name'],
				'area'        => $agent['area'],
				'description' => wp_trim_words( get_post_field( 'post_content', $agent['subject'] ), 35 ),
				'count'       => expert_knowledge_count(),
				'mode'        => $state['mode'],
				'last'        => $state['last_loop'],
				'url'         => home_url( '/' ),
				'activity'    => get_posts(
					array(
						'numberposts'  => 1,
						'has_password' => false,
						'post_status'  => 'publish',
						'meta_key'     => '_expert_authored',
						'meta_value'   => 1,
					)
				),
			);
		} finally {
			restore_current_blog();
		}
	}
	foreach ( $result as &$entry ) {
		$entry['activity'] = array_map(
			static fn( $post ) => array(
				'title' => $post->post_title,
				'date'  => $post->post_date,
			),
			$entry['activity']
		); }
	unset( $entry );
	return $result;
}
