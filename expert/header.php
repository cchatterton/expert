<?php
/** Accessible shared shell. */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#020712"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><?php wp_head(); ?></head>
<body <?php body_class( 'expert-site' ); ?>><?php wp_body_open(); ?>
<a class="expert-skip" href="#expert-main"><?php esc_html_e( 'Skip to content', 'expert' ); ?></a>
<header class="expert-header"><div class="expert-container expert-header-inner"><a class="expert-brand" href="<?php echo esc_url( get_home_url( get_main_site_id(), '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Return to the Expert home page', 'expert' ); ?>"><span class="expert-mark" aria-hidden="true"></span><span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span></a>
<?php
if ( expert_is_agent() ) :
	$expert_header_state = expert_state();
	$expert_loop_lock    = get_option( 'expert_lock_loop' );
	$expert_browser_lock = get_option( 'expert_lock_browser_learning' );
	$expert_station      = get_option( 'expert_browser_station', array() );
	$expert_is_learning  = ( $expert_loop_lock && (int) $expert_loop_lock > time() ) || ( $expert_browser_lock && (int) $expert_browser_lock > time() );
	$expert_station_live = ! empty( $expert_station['seen'] ) && (int) $expert_station['seen'] > time() - 150;
	if ( $expert_is_learning ) {
		$expert_presence_label = __( 'Learning now', 'expert' );
		$expert_presence_class = 'learning';
	} elseif ( $expert_header_state['paused'] ) {
		$expert_presence_label = __( 'Paused', 'expert' );
		$expert_presence_class = 'paused';
	} elseif ( 'Error' === $expert_header_state['mode'] ) {
		$expert_presence_label = __( 'Needs attention', 'expert' );
		$expert_presence_class = 'error';
	} elseif ( ! $expert_station_live ) {
		$expert_presence_label = __( 'Waiting for local AI', 'expert' );
		$expert_presence_class = 'paused';
	} elseif ( $expert_header_state['next_loop'] > time() ) {
		$expert_presence_label = sprintf( __( 'Next learning in %s', 'expert' ), human_time_diff( time(), $expert_header_state['next_loop'] ) );
		$expert_presence_class = 'ready';
	} else {
		$expert_presence_label = __( 'Queued for local learning', 'expert' );
		$expert_presence_class = 'ready';
	}
	?>
	<div class="expert-agent-presence <?php echo esc_attr( $expert_presence_class ); ?>" role="status"><i aria-hidden="true"></i><span><?php echo esc_html( $expert_presence_label ); ?></span></div>
	<?php
endif;
if ( expert_network_member() && expert_is_agent() ) :
	?>
	<nav aria-label="<?php esc_attr_e( 'Main navigation', 'expert' ); ?>">
<a href="<?php echo esc_url( get_post_type_archive_link( 'expert_source' ) ); ?>"><?php esc_html_e( 'Sources', 'expert' ); ?></a><a href="<?php echo esc_url( get_post_type_archive_link( 'expert_faq' ) ); ?>"><?php esc_html_e( 'FAQs', 'expert' ); ?></a><a href="<?php echo esc_url( get_post_type_archive_link( 'expert_research' ) ); ?>"><?php esc_html_e( 'Backlog', 'expert' ); ?></a><a href="<?php echo esc_url( add_query_arg( 'expert_view', 'timeline', home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Timeline', 'expert' ); ?></a>
</nav><?php elseif ( ! expert_network_member() ) : ?>
	<a class="expert-header-login" href="<?php echo esc_url( expert_core_login_url() ); ?>"><?php esc_html_e( 'Log in', 'expert' ); ?></a>
<?php endif; ?></div></header>
<main id="expert-main" class="expert-container">
