<?php
/** Core membership is the access boundary for every Agent. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_network_member() {
	return is_user_logged_in() && ( is_super_admin() || is_user_member_of_blog( get_current_user_id(), get_main_site_id() ) );
}
function expert_core_login_url() {
	return get_site_url( get_main_site_id(), 'wp-login.php', 'login' );
}
function expert_public_home_request() {
	return is_main_site()
		&& ( is_front_page() || is_home() )
		&& ! is_feed()
		&& ! is_search()
		&& empty( $_GET['expert_view'] );
}
function expert_access_gate() {
	if ( ! is_multisite() || expert_network_member() || expert_public_home_request() ) {
		return;
	}
	nocache_headers();
	if ( ! is_main_site() ) {
		wp_safe_redirect( get_home_url( get_main_site_id(), '/' ) );
		exit;
	}
	status_header( 200 );
	get_header();
	echo '<section class="expert-login"><p class="expert-eyebrow">' . esc_html__( 'Expert · Techn', 'expert' ) . '</p><h1>' . esc_html__( 'A place for evolving knowledge.', 'expert' ) . '</h1><p>' . esc_html__( 'Sign in to explore the Expert network.', 'expert' ) . '</p><a class="expert-button" href="' . esc_url( expert_core_login_url() ) . '">' . esc_html__( 'Log in', 'expert' ) . '</a><p>' . esc_html__( 'Access is available to members of this network. You can close this page to leave.', 'expert' ) . '</p></section>';
	get_footer();
	exit;
}
add_action( 'template_redirect', 'expert_access_gate', 0 );
function expert_rest_access_gate( $result ) {
	if ( ! expert_network_member() ) {
		return new WP_Error( 'expert_login_required', __( 'Please log in through the core site.', 'expert' ), array( 'status' => 401 ) );
	}
	return $result;
}
add_filter( 'rest_authentication_errors', 'expert_rest_access_gate', 99 );
function expert_disable_xmlrpc( $methods ) {
	return array();
}
add_filter( 'xmlrpc_methods', 'expert_disable_xmlrpc' );
function expert_login_destination( $redirect, $requested, $user ) {
	return $user instanceof WP_User ? get_home_url( get_main_site_id(), '/' ) : $redirect;
}
add_filter( 'login_redirect', 'expert_login_destination', 10, 3 );

function expert_core_login_gate() {
	if ( ! is_multisite() || is_main_site() ) {
		return;
	}
	wp_safe_redirect( expert_core_login_url() );
	exit;
}
add_action( 'login_init', 'expert_core_login_gate' );
function expert_comment_membership( $data ) {
	if ( ! expert_network_member() ) {
		wp_die( esc_html__( 'Please log in through the core site before commenting.', 'expert' ), 403 );
	}
	return $data;
}
add_filter( 'preprocess_comment', 'expert_comment_membership' );
function expert_member_cache_headers() {
	if ( is_multisite() ) {
		nocache_headers();
	} }
add_action( 'template_redirect', 'expert_member_cache_headers', 1 );

/** Permit the intentional Agent-to-core redirect on subdomain networks. */
function expert_core_redirect_host( $hosts ) {
	$hosts[] = wp_parse_url( get_home_url( get_main_site_id(), '/' ), PHP_URL_HOST );
	return array_unique( array_filter( $hosts ) );
}
add_filter( 'allowed_redirect_hosts', 'expert_core_redirect_host' );
function expert_member_robots( $robots ) {
	if ( expert_public_home_request() && ! expert_network_member() ) {
		return $robots;
	}
	$robots['noindex']  = true;
	$robots['nofollow'] = true;
	return $robots; }
add_filter( 'wp_robots', 'expert_member_robots' );
