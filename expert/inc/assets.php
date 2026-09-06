<?php
/** Native asset loading. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_assets() {
	wp_enqueue_style( 'expert', get_template_directory_uri() . '/styles/expert.css', array(), EXPERT_VERSION );
	if ( expert_network_member() && expert_is_agent() ) {
		wp_enqueue_script( 'expert', get_template_directory_uri() . '/scripts/expert.js', array(), EXPERT_VERSION, true );
		wp_localize_script(
			'expert',
			'expertConfig',
			array(
				'api'      => rest_url( 'expert/v1/' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'webllm'   => 'https://esm.run/@mlc-ai/web-llm@0.2.84',
				'model'    => 'SmolLM2-360M-Instruct-q4f32_1-MLC',
				'objectId' => is_singular() ? get_queried_object_id() : 0,
				'loading'  => __( 'Preparing local AI...', 'expert' ),
				'ready'    => __( 'Local AI ready', 'expert' ),
				'unsupported' => __( 'This device does not provide WebGPU. Use a current desktop Chrome browser for local AI.', 'expert' ),
				'working'  => __( 'Thinking…', 'expert' ),
				'failed'   => __( 'The request could not be completed. Please try again.', 'expert' ),
				'saved'    => __( 'Saved. Related knowledge will be reviewed shortly.', 'expert' ),
				'empty'    => __( 'No supported matches yet. Try another question.', 'expert' ),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'expert_assets' );
function expert_admin_assets( $hook ) {
	if ( 'toplevel_page_expert-agents' === $hook ) {
		wp_enqueue_style( 'expert-admin', get_template_directory_uri() . '/styles/expert.css', array(), EXPERT_VERSION );
	} }
add_action( 'admin_enqueue_scripts', 'expert_admin_assets' );
