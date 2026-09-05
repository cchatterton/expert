<?php
/** Manifest-first GitHub release discovery through native theme update transients. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function expert_clear_update_cache() {
	delete_site_transient( 'expert_release' );
	delete_site_transient( 'expert_release_error' );
}
function expert_latest_release() {
	$cached = get_site_transient( 'expert_release' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	if ( get_site_transient( 'expert_release_error' ) ) {
		return false;
	}
	$base = 'https://github.com/cchatterton/expert';
	$urls = array( 'https://raw.githubusercontent.com/cchatterton/expert/main/update.json', $base . '/releases/latest', 'https://api.github.com/repos/cchatterton/expert/releases/latest' );
	foreach ( $urls as $index => $url ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 8,
				'redirection'         => 0,
				'limit_response_size' => 32768,
				'headers'             => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'Expert/' . EXPERT_VERSION,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			continue;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 429 === $code || ( 403 === $code && 2 === $index ) ) {
			break;
		}
		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$version = '';
		$body    = '';
		if ( 0 === $index && 200 === $code && is_array( $data ) ) {
			$version = $data['version'] ?? '';
			$body    = $data['body'] ?? '';
		}
		if ( 1 === $index && in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( preg_match( '#^https://github\.com/cchatterton/expert/releases/tag/v?([0-9]+\.[0-9]+\.[0-9]+)$#', $location, $match ) ) {
				$version = $match[1];
			}
		}
		if ( 2 === $index && 200 === $code && is_array( $data ) ) {
			$version = ltrim( $data['tag_name'] ?? '', 'vV' );
			$body    = $data['body'] ?? '';
			$assets  = array_column( $data['assets'] ?? array(), 'name' );
			if ( ! in_array( 'expert.zip', $assets, true ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
				$version = '';
			}
		}
		if ( ! is_string( $version ) || ! preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+$/', $version ) ) {
			continue;
		}
		$release = array(
			'version' => $version,
			'body'    => sanitize_textarea_field( $body ),
			'url'     => $base . '/releases/tag/v' . $version,
			'package' => $base . '/releases/download/v' . $version . '/expert.zip',
		);
		set_site_transient( 'expert_release', $release, version_compare( $version, EXPERT_VERSION, '>' ) ? 21600 : 300 );
		delete_site_transient( 'expert_release_error' );
		return $release;
	}
	set_site_transient(
		'expert_release_error',
		array(
			'checked' => time(),
			'code'    => 'lookup_failed',
		),
		600
	);
	return false;
}
function expert_theme_update_data( $transient ) {
	if ( ! is_object( $transient ) || ! is_admin() ) {
		return $transient;
	}
	$release = expert_latest_release();
	if ( ! $release ) {
		return $transient;
	}
	$transient->response  = isset( $transient->response ) && is_array( $transient->response ) ? $transient->response : array();
	$transient->no_update = isset( $transient->no_update ) && is_array( $transient->no_update ) ? $transient->no_update : array();
	unset( $transient->response['expert'], $transient->no_update['expert'] );
	if ( version_compare( $release['version'], EXPERT_VERSION, '>' ) ) {
		$transient->response['expert'] = array(
			'theme'        => 'expert',
			'new_version'  => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires'     => '6.9',
			'requires_php' => '8.1',
		);
	}
	return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', 'expert_theme_update_data' );
add_filter( 'site_transient_update_themes', 'expert_theme_update_data' );
function expert_native_update( $update, $theme_data, $stylesheet ) {
	if ( 'expert' !== $stylesheet ) {
		return $update;
	}
	$release = expert_latest_release();
	return $release ? array(
		'theme'        => 'expert',
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires'     => '6.9',
		'requires_php' => '8.1',
	) : false;
}
add_filter( 'update_themes_github.com', 'expert_native_update', 10, 3 );
function expert_forced_update_check() {
	if ( ! current_user_can( 'update_themes' ) ) {
		return;
	}
	if ( isset( $_GET['force-check'] ) || isset( $_POST['force-check'] ) || in_array( sanitize_key( $_REQUEST['action'] ?? '' ), array( 'update-selected-themes', 'upgrade-theme', 'do-theme-upgrade' ), true ) ) {
		expert_clear_update_cache();
	}
}
add_action( 'admin_init', 'expert_forced_update_check' );
function expert_upgraded( $upgrader, $options ) {
	if ( 'theme' === ( $options['type'] ?? '' ) && 'update' === ( $options['action'] ?? '' ) ) {
		expert_clear_update_cache();
	} }
add_action( 'upgrader_process_complete', 'expert_upgraded', 10, 2 );
