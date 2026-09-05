<?php
/** Isolated updater fixtures in a disposable WordPress installation. */
wp_set_current_user( 1 );
$GLOBALS['expert_update_test_mode'] = 'manifest'; $GLOBALS['expert_update_requests'] = array();
function expert_update_http_fixture( $pre, $args, $url ) {
 if ( ! str_contains( $url, 'cchatterton/expert' ) ) { return $pre; }
 $GLOBALS['expert_update_requests'][] = $url;
 $mode = $GLOBALS['expert_update_test_mode'];
 $response = array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '', 'cookies' => array() );
 if ( 'rate_limit' === $mode ) { $response['response']['code'] = 429; return $response; }
 if ( 'bad' === $mode ) { $response['body'] = 'not JSON'; return $response; }
 if ( str_contains( $url, 'update.json' ) && 'redirect' !== $mode ) { $response['body'] = wp_json_encode( array( 'version' => 'newer' === $mode ? '0.1.1' : '0.1.0', 'body' => 'Fixture release', 'package' => 'https://evil.example/file.zip' ) ); return $response; }
 if ( str_contains( $url, '/releases/latest' ) && str_contains( $url, 'github.com/' ) && ! str_contains( $url, 'api.github.com' ) ) { $response['response']['code'] = 302; $response['headers']['location'] = 'https://github.com/cchatterton/expert/releases/tag/v0.1.2'; return $response; }
 return new WP_Error( 'fixture_missing' );
}
add_filter( 'pre_http_request', 'expert_update_http_fixture', 10, 3 );
function expert_update_assert( $value, $label ) { if ( ! $value ) { throw new RuntimeException( $label ); } echo "PASS: $label\n"; }
expert_clear_update_cache(); $release = expert_latest_release();
expert_update_assert( $release['version'] === '0.1.0' && count( $GLOBALS['expert_update_requests'] ) === 1, 'Manifest-first avoids GitHub API' );
expert_update_assert( $release['package'] === 'https://github.com/cchatterton/expert/releases/download/v0.1.0/expert.zip', 'Package URL constructed from trusted repository' );
expert_latest_release(); expert_update_assert( count( $GLOBALS['expert_update_requests'] ) === 1, 'Successful release lookup is cached' );
expert_clear_update_cache(); $GLOBALS['expert_update_test_mode'] = 'redirect'; $release = expert_latest_release();
expert_update_assert( $release['version'] === '0.1.2', 'Public release redirect fallback' );
expert_clear_update_cache(); $GLOBALS['expert_update_test_mode'] = 'rate_limit'; $GLOBALS['expert_update_requests'] = array();
expert_update_assert( false === expert_latest_release() && false === get_site_transient( 'expert_release' ) && get_site_transient( 'expert_release_error' ), 'Rate limit has separate backoff without poisoning release data' );
expert_latest_release(); expert_update_assert( count( $GLOBALS['expert_update_requests'] ) === 1, 'Rate-limit backoff prevents repeated requests' );
expert_clear_update_cache(); $GLOBALS['expert_update_test_mode'] = 'bad';
expert_update_assert( false === expert_latest_release(), 'Malformed release responses fail closed' );
expert_clear_update_cache(); $GLOBALS['expert_update_test_mode'] = 'newer';
set_current_screen( 'dashboard-network' );
$transient = expert_theme_update_data( (object) array() );
expert_update_assert( $transient->response['expert']['new_version'] === '0.1.1', 'New version enters native theme update data' );
expert_clear_update_cache(); $GLOBALS['expert_update_test_mode'] = 'manifest';
$transient = expert_theme_update_data( $transient );
expert_update_assert( empty( $transient->response['expert'] ) && empty( $transient->no_update['expert'] ), 'Equal version clears stale update entries' );
expert_clear_update_cache();
