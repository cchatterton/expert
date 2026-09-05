<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
get_header();
$expert_view = sanitize_key( $_GET['expert_view'] ?? '' );
if ( 'timeline' === $expert_view && expert_is_agent() ) {
	get_template_part( 'templates/timeline' ); } elseif ( 'about' === $expert_view ) {
	get_template_part( 'templates/about' ); } elseif ( is_main_site() ) {
		get_template_part( 'templates/home-root' ); } elseif ( expert_is_agent() ) {
		get_template_part( 'templates/home-agent' ); } else {
			echo '<h1>' . esc_html__( 'This Agent is not ready yet.', 'expert' ) . '</h1><p>' . esc_html__( 'A network administrator can complete setup from Expert Agents.', 'expert' ) . '</p>'; }
		get_footer();
