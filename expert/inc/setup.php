<?php
/** Native theme and content lifecycle. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_theme_setup() {
	load_theme_textdomain( 'expert', get_template_directory() . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	register_nav_menus( array( 'primary' => __( 'Primary navigation', 'expert' ) ) );
}
add_action( 'after_setup_theme', 'expert_theme_setup' );
function expert_register_content() {
	foreach ( array(
		'expert_source'   => __( 'Sources', 'expert' ),
		'expert_faq'      => __( 'FAQs', 'expert' ),
		'expert_research' => __( 'Research backlog', 'expert' ),
		'expert_subject'  => __( 'Subject description', 'expert' ),
	) as $type => $label ) {
		register_post_type(
			$type,
			array(
				'label'           => $label,
				'public'          => 'expert_subject' !== $type,
				'show_ui'         => true,
				'show_in_rest'    => true,
				'has_archive'     => 'expert_subject' !== $type,
				'rewrite'         => array( 'slug' => str_replace( 'expert_', '', $type ) ),
				'supports'        => array( 'title', 'editor', 'author', 'revisions', 'custom-fields' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}
	foreach ( array( 'expert_source', 'expert_faq' ) as $type ) {
		register_taxonomy_for_object_type( 'category', $type );
		register_taxonomy_for_object_type( 'post_tag', $type );
	}
	register_post_meta(
		'',
		'_expert_human',
		array(
			'type'         => 'boolean',
			'single'       => true,
			'show_in_rest' => false,
		)
	);
}
add_action( 'init', 'expert_register_content' );
function expert_initialise() {
	if ( ! is_multisite() ) {
		return;
	}
	expert_install_storage();
	if ( ! wp_next_scheduled( 'expert_network_tick' ) ) {
		wp_schedule_single_event( time() + 60, 'expert_network_tick' );
	}
}
add_action( 'init', 'expert_initialise', 20 );
function expert_theme_switched() {
	expert_register_content();
	if ( is_multisite() && current_user_can( 'manage_network_themes' ) ) {
		$allowed           = get_site_option( 'allowedthemes', array() );
		$allowed['expert'] = true;
		update_site_option( 'allowedthemes', $allowed );
	}
	update_option( 'comment_registration', 1 );
	flush_rewrite_rules();
	expert_initialise();
}
add_action( 'after_switch_theme', 'expert_theme_switched' );
function expert_theme_stop() {
	foreach ( array( 'expert_network_tick', 'expert_human_reflect', 'expert_index_post', 'expert_index_comment', 'expert_reindex_batch', 'expert_manual_loop' ) as $hook ) {
		wp_unschedule_hook( $hook );
	}
}
add_action( 'switch_theme', 'expert_theme_stop' );
function expert_single_site_notice() {
	if ( ! is_multisite() ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Expert requires WordPress Multisite for Agent operation. Existing content remains available.', 'expert' ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'expert_single_site_notice' );
