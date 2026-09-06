<?php
/** Accessible shared shell. */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class( 'expert-site' ); ?>><?php wp_body_open(); ?>
<a class="expert-skip" href="#expert-main"><?php esc_html_e( 'Skip to content', 'expert' ); ?></a>
<header class="expert-header"><div class="expert-container expert-header-inner"><a class="expert-brand" href="<?php echo esc_url( get_home_url( get_main_site_id(), '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Return to the Expert home page', 'expert' ); ?>"><span class="expert-mark" aria-hidden="true"></span><span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span></a>
<?php
if ( expert_network_member() && expert_is_agent() ) :
	?>
	<nav aria-label="<?php esc_attr_e( 'Main navigation', 'expert' ); ?>">
<a href="<?php echo esc_url( get_post_type_archive_link( 'expert_source' ) ); ?>"><?php esc_html_e( 'Sources', 'expert' ); ?></a><a href="<?php echo esc_url( get_post_type_archive_link( 'expert_faq' ) ); ?>"><?php esc_html_e( 'FAQs', 'expert' ); ?></a><a href="<?php echo esc_url( get_post_type_archive_link( 'expert_research' ) ); ?>"><?php esc_html_e( 'Backlog', 'expert' ); ?></a><a href="<?php echo esc_url( add_query_arg( 'expert_view', 'timeline', home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Timeline', 'expert' ); ?></a>
</nav><?php elseif ( ! expert_network_member() ) : ?>
	<a class="expert-header-login" href="<?php echo esc_url( expert_core_login_url() ); ?>"><?php esc_html_e( 'Log in', 'expert' ); ?></a>
<?php endif; ?></div></header>
<main id="expert-main" class="expert-container">
