<?php
/** All setup and operations live in Network Admin. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_admin_menu() {
	add_menu_page( __( 'Expert Agents', 'expert' ), __( 'Expert Agents', 'expert' ), 'manage_network_options', 'expert-agents', 'expert_admin_page', 'dashicons-lightbulb', 25 );
}
add_action( 'network_admin_menu', 'expert_admin_menu' );
function expert_admin_url() {
	return network_admin_url( 'admin.php?page=expert-agents' );
}
function expert_admin_action() {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		wp_die( esc_html__( 'Network administrator access is required.', 'expert' ), 403 );
	}
	check_admin_referer( 'expert_network' );
	$action = sanitize_key( wp_unslash( $_POST['expert_action'] ?? '' ) );
	$result = true;
	if ( 'settings' === $action ) {
		$settings = expert_config();
		foreach ( expert_defaults() as $key => $default ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			if ( is_int( $default ) ) {
				$value = max( 1, min( 86400, (int) $value ) );
			}
			$settings[ $key ] = $value;
		}
		if ( ! expert_loopback_url( $settings['runtime_url'] ) || ( $settings['discovery_url'] && ! expert_loopback_url( $settings['discovery_url'] ) ) || ! in_array( $settings['publishing'], array( 'draft', 'publish' ), true ) ) {
			$result = expert_error( 'invalid_settings' );
		} else {
				$settings['growth_interval']     = max( 600, $settings['growth_interval'] );
					$settings['steady_interval'] = max( 600, $settings['steady_interval'] );
				$settings['high']                = max( $settings['moderate'] + 1, $settings['high'] );
					$settings['very_high']       = max( $settings['high'] + 1, $settings['very_high'] );
				update_site_option( 'expert_settings', $settings );
		}
	} elseif ( 'create' === $action ) {
		$name   = sanitize_text_field( wp_unslash( $_POST['agent_name'] ?? '' ) );
		$area   = sanitize_textarea_field( wp_unslash( $_POST['knowledge_area'] ?? '' ) );
		$result = expert_create_agent_site( $name, $area );
	} elseif ( 'health' === $action ) {
		$health = expert_runtime( '/health' );
		$models = expert_runtime( '/models' );
		update_site_option(
			'expert_runtime_check',
			array(
				'health'  => is_wp_error( $health ) ? $health->get_error_code() : $health,
				'models'  => is_wp_error( $models ) ? $models->get_error_code() : $models,
				'checked' => time(),
			)
		);
		if ( is_wp_error( $health ) || is_wp_error( $models ) ) {
			$result = expert_error( 'runtime_unavailable' );
		}
	} elseif ( 'updates' === $action ) {
		if ( ! current_user_can( 'update_themes' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'expert' ), 403 );
		}
		expert_clear_update_cache();
		delete_site_transient( 'update_themes' );
		wp_update_themes();
	} else {
		$site_id = absint( $_POST['agent_id'] ?? 0 );
		$site    = get_site( $site_id );
		if ( ! $site || (int) $site->network_id !== get_current_network_id() ) {
			wp_die( esc_html__( 'Agent not found.', 'expert' ), 404 );
		}
		switch_to_blog( $site_id );
		try {
			if ( ! expert_is_agent() ) {
				$result = expert_error( 'not_agent' );
			} else {
						$state = expert_state();
				if ( in_array( $action, array( 'pause', 'resume' ), true ) ) {
					$state['paused']    = 'pause' === $action;
					$state['mode']      = $state['paused'] ? 'Paused' : 'Growth';
					$state['next_loop'] = time() + 60;
					update_option( 'expert_state', $state, false );
					expert_event( 'agent_' . $action, 0, $action, 'Human' );
				} elseif ( 'run' === $action ) {
					if ( ! $state['paused'] ) {
						$state['next_loop'] = time();
						update_option( 'expert_state', $state, false );
						expert_event( 'loop_queued', 0, __( 'The next browser-local learning station will claim this job.', 'expert' ), 'Human' );
					}
				} elseif ( 'agent_settings' === $action ) {
					$agent = get_option( 'expert_agent' );
					$mode  = sanitize_key( $_POST['publishing'] ?? '' );
					if ( in_array( $mode, array( 'draft', 'publish' ), true ) ) {
										$agent['publishing'] = $mode;
					} else {
																unset( $agent['publishing'] );
					}
											$agent['cadence'] = empty( $_POST['cadence'] ) ? 0 : max( 600, min( 86400, absint( $_POST['cadence'] ) ) );
											update_option( 'expert_agent', $agent, false );
											expert_event( 'agent_settings', 0, __( 'Agent publishing and cadence preferences saved.', 'expert' ), 'Human' );
				} elseif ( 'publish' === $action ) {
					$post_id = absint( $_POST['post_id'] ?? 0 );
					if ( 'draft' === get_post_status( $post_id ) && get_post_meta( $post_id, '_expert_authored', true ) ) {
										wp_publish_post( $post_id );
										expert_event( 'human_published', $post_id, __( 'An administrator approved publication.', 'expert' ), 'Human' );
					}
				}
			}
		} finally {
			restore_current_blog();
		}
	}
	set_transient( 'expert_admin_notice_' . get_current_user_id(), is_wp_error( $result ) ? __( 'The action could not be completed. Check the configuration and service status, then retry.', 'expert' ) . ' (' . $result->get_error_code() . ')' : __( 'Saved. Your network is up to date.', 'expert' ), 60 );
	wp_safe_redirect( expert_admin_url() );
	exit;
}
add_action( 'admin_post_expert_network', 'expert_admin_action' );
function expert_create_agent_site( $name, $area ) {
	if ( ! $name || ! $area || strlen( $name ) > 200 || strlen( $area ) > 2000 ) {
		return expert_error( 'invalid_identity' );
	}
	$lock = expert_lock( 'create_site', 120 );
	if ( ! $lock ) {
		return expert_error( 'creation_busy' );
	}
	try {
		$network = get_network();
		$slug    = substr( sanitize_title( $name ), 0, 48 ) ?: 'agent';
		$base    = $slug;
		for ( $suffix = 1; $suffix <= 100; ++$suffix ) {
			$domain = is_subdomain_install() ? $slug . '.' . $network->domain : $network->domain;
			$path   = is_subdomain_install() ? $network->path : trailingslashit( $network->path ) . $slug . '/';
			if ( ! domain_exists( $domain, $path, $network->id ) ) {
				break;
			}
			$slug = $base . '-' . $suffix;
		}
		$id = wpmu_create_blog( $domain, $path, $name, get_current_user_id(), array( 'public' => 1 ), $network->id );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		switch_to_blog( $id );
		try {
			switch_theme( 'expert' );
			expert_register_content();
			update_option( 'permalink_structure', '/%postname%/' );
			// Remove only WordPress-created sample content on this newly created site.
			foreach ( get_posts(
				array(
					'post_type'   => array( 'post', 'page' ),
					'post_status' => 'any',
					'numberposts' => 10,
				)
			) as $sample ) {
				wp_delete_post( $sample->ID, true );
			}
			$agent = expert_bootstrap_agent( $name, $area );
			flush_rewrite_rules( false );
			expert_initialise();
			if ( is_wp_error( $agent ) ) {
				update_option( 'expert_setup_error', $agent->get_error_code(), false );
				return $agent;
			}
		} finally {
			restore_current_blog();
		}
		return $id;
	} finally {
		expert_unlock( 'create_site', $lock );
	}
}
function expert_admin_form_start( $action, $id = 0 ) {
	echo '<form method="post" action="' . esc_url( get_admin_url( get_main_site_id(), 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'expert_network' );
	echo '<input type="hidden" name="action" value="expert_network"><input type="hidden" name="expert_action" value="' . esc_attr( $action ) . '"><input type="hidden" name="agent_id" value="' . esc_attr( $id ) . '">';
}
function expert_admin_button( $action, $label, $id = 0 ) {
	expert_admin_form_start( $action, $id );
	submit_button( $label, 'secondary', 'submit', false );
	echo '</form>';
}
function expert_admin_page() {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		return;
	}
	$settings = expert_config();
	echo '<div class="wrap expert-admin"><h1>' . esc_html__( 'Expert Agents', 'expert' ) . '</h1>';
	$notice = get_transient( 'expert_admin_notice_' . get_current_user_id() );
	if ( $notice ) {
		echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		delete_transient( 'expert_admin_notice_' . get_current_user_id() );
	}
	echo '<div class="expert-hero"><p class="expert-eyebrow">' . esc_html__( 'Expert by Techn', 'expert' ) . '</p><h2>' . esc_html__( 'Give curiosity a subject.', 'expert' ) . '</h2><p>' . esc_html__( 'Create an Agent. Its site, knowledge, navigation and learning schedule are prepared automatically.', 'expert' ) . '</p></div>';
	echo '<section class="expert-panel"><h2>' . esc_html__( 'Add an Agent', 'expert' ) . '</h2>';
	expert_admin_form_start( 'create' );
	echo '<p><label for="agent-name">' . esc_html__( 'Agent Name', 'expert' ) . '</label><input id="agent-name" name="agent_name" maxlength="200" required class="regular-text"></p><p><label for="knowledge-area">' . esc_html__( 'Knowledge Area', 'expert' ) . '</label><textarea id="knowledge-area" name="knowledge_area" maxlength="2000" required rows="3" class="large-text"></textarea></p>';
	submit_button( __( 'Create Agent', 'expert' ) );
	echo '</form></section>';
	echo '<section class="expert-panel"><h2>' . esc_html__( 'Your Agents', 'expert' ) . '</h2><div class="expert-table-scroll"><table class="widefat striped"><thead><tr>';
	foreach ( array( 'Agent', 'Knowledge Area', 'Knowledge pieces', 'Mode', 'Loop frequency', 'Average engagement / loop', 'Engagement / hour', 'Trend', 'Last loop', 'Next loop', 'Local AI' ) as $heading ) {
		echo '<th>' . esc_html( $heading ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	$page = max( 1, absint( $_GET['agent_page'] ?? 1 ) );
	foreach ( expert_agent_sites( false, ( $page - 1 ) * 50 ) as $site ) {
		switch_to_blog( $site->blog_id );
		try {
			if ( ! expert_is_agent() ) {
				continue;
			}
			$agent   = get_option( 'expert_agent' );
			$state   = expert_state();
			$station = get_option( 'expert_browser_station', array() );
			$runtime = ! empty( $station['seen'] ) && (int) $station['seen'] > time() - 150 ? 'Connected' : 'Waiting';
			echo '<tr><td><a href="' . esc_url( add_query_arg( 'agent', $site->blog_id, expert_admin_url() ) ) . '">' . esc_html( $agent['name'] ) . '</a></td>';
			foreach ( array( $agent['area'], expert_knowledge_count(), $state['mode'], round( $state['interval'] / 60 ) . ' min', round( $state['average'], 2 ), round( $state['per_hour'], 2 ), round( $state['trend'], 2 ), $state['last_loop'] ? gmdate( 'Y-m-d H:i', $state['last_loop'] ) . ' UTC' : 'Not yet', gmdate( 'Y-m-d H:i', $state['next_loop'] ) . ' UTC', $runtime ) as $value ) {
				echo '<td>' . esc_html( $value ) . '</td>';
			}
			echo '</tr>';
		} finally {
			restore_current_blog();
		}
	}
	echo '</tbody></table></div><p><a href="' . esc_url( add_query_arg( 'agent_page', $page + 1, expert_admin_url() ) ) . '">' . esc_html__( 'Next page', 'expert' ) . '</a></p></section>';
	$id   = absint( $_GET['agent'] ?? 0 );
	$site = $id ? get_site( $id ) : null;
	if ( $site && (int) $site->network_id === get_current_network_id() ) {
		switch_to_blog( $id );
		try {
			if ( expert_is_agent() ) {
				expert_admin_detail( $id );
			}
		} finally {
			restore_current_blog();
		}
	}
	echo '<details class="expert-panel"><summary>' . esc_html__( 'Network setup and settings', 'expert' ) . '</summary><p>' . esc_html__( 'Local AI runs with permission in a member browser. Models are cached on that device; Agent memory remains in WordPress.', 'expert' ) . '</p>';
	expert_admin_form_start( 'settings' );
	$labels = array(
		'growth_threshold' => 'Growth knowledge threshold',
		'growth_interval'  => 'Growth interval (seconds)',
		'steady_interval'  => 'Steady interval (seconds)',
		'moderate'         => 'Moderate engagement / hour',
		'high'             => 'High engagement / hour',
		'very_high'        => 'Very high engagement / hour',
		'max_ai'           => 'AI requests per loop',
		'max_seconds'      => 'Maximum loop seconds',
		'max_fetches'      => 'Source fetch limit',
		'max_bytes'        => 'Maximum source bytes',
	);
	foreach ( $labels as $key => $label ) {
		echo '<p><label for="expert-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><input class="regular-text" id="expert-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . ( is_int( expert_defaults()[ $key ] ) ? 'number' : 'text' ) . '" value="' . esc_attr( $settings[ $key ] ) . '"></p>';
	}
	echo '<p><label for="publishing">' . esc_html__( 'Default publication', 'expert' ) . '</label><select id="publishing" name="publishing"><option value="draft"' . selected( $settings['publishing'], 'draft', false ) . '>' . esc_html__( 'Draft for review', 'expert' ) . '</option><option value="publish"' . selected( $settings['publishing'], 'publish', false ) . '>' . esc_html__( 'Auto publish', 'expert' ) . '</option></select></p>';
	submit_button( __( 'Save network settings', 'expert' ) );
	echo '</form>';
	echo '</details><section class="expert-panel"><h2>' . esc_html__( 'Theme updates', 'expert' ) . '</h2><p>Expert ' . esc_html( EXPERT_VERSION ) . ' · <a href="https://github.com/cchatterton/expert">GitHub</a></p>';
	expert_admin_button( 'updates', __( 'Check for updates', 'expert' ) );
	echo '<p><a href="' . esc_url( network_admin_url( 'update-core.php' ) ) . '">' . esc_html__( 'Open WordPress updates', 'expert' ) . '</a></p></section></div>';
}
function expert_admin_detail( $id ) {
	$agent = get_option( 'expert_agent' );
	$state = expert_state();
	echo '<section class="expert-panel"><h2>' . esc_html( $agent['name'] ) . '</h2><p>' . esc_html( get_post_field( 'post_content', $agent['subject'] ) ) . '</p><p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Visit Agent', 'expert' ) . '</a></p><div class="expert-actions">';
	expert_admin_button( $state['paused'] ? 'resume' : 'pause', $state['paused'] ? __( 'Resume learning', 'expert' ) : __( 'Pause learning', 'expert' ), $id );
	expert_admin_button( 'run', __( 'Queue next local loop', 'expert' ), $id );
	echo '</div>';
	expert_admin_form_start( 'agent_settings', $id );
	echo '<p><label for="agent-publishing">' . esc_html__( 'Publication override', 'expert' ) . '</label><select id="agent-publishing" name="publishing">';
	foreach ( array(
		''        => 'Network default',
		'draft'   => 'Draft for review',
		'publish' => 'Auto publish',
	) as $value => $label ) {
		echo '<option value="' . esc_attr( $value ) . '"' . selected( $agent['publishing'] ?? '', $value, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></p><p><label for="cadence">' . esc_html__( 'Testing cadence override (seconds; 0 uses adaptive cadence)', 'expert' ) . '</label><input id="cadence" name="cadence" type="number" min="0" max="86400" value="' . esc_attr( $agent['cadence'] ?? 0 ) . '"></p>';
	submit_button( __( 'Save Agent settings', 'expert' ) );
	echo '</form><h3>' . esc_html__( 'Recent loop history', 'expert' ) . '</h3><div class="expert-table-scroll"><table class="widefat striped"><thead><tr><th>Time (UTC)</th><th>Topic</th><th>Result</th><th>Error</th></tr></thead><tbody>';
	foreach ( expert_loop_history( 20 ) as $loop ) {
		echo '<tr><td>' . esc_html( $loop['started_at'] ) . '</td><td>' . esc_html( $loop['research_topic'] ) . '</td><td>' . esc_html( $loop['status'] ) . '</td><td>' . esc_html( $loop['error_code'] ) . '</td></tr>';
	}
	echo '</tbody></table></div><h3>' . esc_html__( 'Drafts awaiting review', 'expert' ) . '</h3>';
	foreach ( get_posts(
		array(
			'post_status' => 'draft',
			'numberposts' => 10,
			'meta_key'    => '_expert_authored',
			'meta_value'  => 1,
		)
	) as $post ) {
		echo '<details><summary>' . esc_html( $post->post_title ) . '</summary><div>' . wp_kses_post( wpautop( $post->post_content ) ) . '</div>';
		expert_admin_form_start( 'publish', $id );
		echo '<input type="hidden" name="post_id" value="' . esc_attr( $post->ID ) . '">';
		submit_button( __( 'Approve and publish', 'expert' ), 'secondary' );
		echo '</form></details>';
	}
	echo '</section>';
}
