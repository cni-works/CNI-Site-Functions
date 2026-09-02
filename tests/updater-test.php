<?php
/**
 * Minimal CLI regression checks for the GitHub Release updater.
 */

define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );

$cni_updater_actions    = array();
$cni_updater_filters    = array();
$cni_updater_transients = array();
$cni_updater_response   = array();

class WP_Error {}

function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, $args );
}

function add_action( $hook, $callback, $priority = 10 ) {
	global $cni_updater_actions;
	$cni_updater_actions[] = array( $hook, $callback, $priority );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	global $cni_updater_filters;
	$cni_updater_filters[] = array( $hook, $callback, $priority, $accepted_args );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function untrailingslashit( $value ) {
	return rtrim( $value, '/\\' );
}

function get_site_transient( $key ) {
	global $cni_updater_transients;
	return array_key_exists( $key, $cni_updater_transients ) ? $cni_updater_transients[ $key ] : false;
}

function set_site_transient( $key, $value, $expiration ) {
	global $cni_updater_transients;
	$cni_updater_transients[ $key ] = $value;
	return true;
}

function delete_site_transient( $key ) {
	global $cni_updater_transients;
	unset( $cni_updater_transients[ $key ] );
	return true;
}

function wp_safe_remote_get() {
	global $cni_updater_response;
	return $cni_updater_response;
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? $response['body'] : '';
}

function wp_http_validate_url( $url ) {
	return false !== filter_var( $url, FILTER_VALIDATE_URL );
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

require dirname( __DIR__ ) . '/includes/updater/class-github-release-updater.php';

use CniWorks\CniSiteFunctions\Updater\GitHub_Release_Updater;

$failed = 0;

function cni_assert_updater( $condition, $message ) {
	global $failed;
	if ( ! $condition ) {
		$failed++;
		fwrite( STDERR, "FAIL: {$message}\n" );
	} else {
		echo "PASS: {$message}\n";
	}
}

function cni_updater_config( $version = '1.0.0', $plugin_file = 'cni-site-functions/cni-site-functions.php' ) {
	return array(
		'type'          => 'plugin',
		'owner'         => 'cni-works',
		'repository'    => 'CNI-Site-Functions',
		'slug'          => 'cni-site-functions',
		'plugin_file'   => $plugin_file,
		'version'       => $version,
		'update_uri'    => 'https://github.com/cni-works/CNI-Site-Functions',
		'requires'      => '6.5',
		'requires_php'  => '7.4',
		'cache_hours'   => 12,
		'failure_hours' => 1,
		'timeout'       => 5,
	);
}

$headers  = array( 'UpdateURI' => 'https://github.com/cni-works/CNI-Site-Functions' );
$cache_key = 'cniworks_gh_release_' . md5( strtolower( 'cni-works/CNI-Site-Functions' ) );

$updater = new GitHub_Release_Updater( cni_updater_config() );
cni_assert_updater( 'update_plugins_github.com' === $cni_updater_filters[0][0], 'the WordPress Update URI filter is registered' );

$cni_updater_transients[ $cache_key ] = array( 'state' => 'failure' );
$updater = new GitHub_Release_Updater( cni_updater_config() );
$result  = $updater->filter_plugin_update( false, $headers, 'cni-site-functions/cni-site-functions.php', array() );
cni_assert_updater( '1.0.0' === $result['new_version'] && ! isset( $result['package'] ), 'API failure returns safe no-update metadata without a package' );
cni_assert_updater( 'cni-site-functions/cni-site-functions.php' === $result['plugin'], 'no-update metadata preserves the plugin basename' );

$cni_updater_transients[ $cache_key ] = array(
	'state'   => 'success',
	'release' => array(
		'version' => '1.0.1',
		'package' => 'https://github.com/cni-works/CNI-Site-Functions/releases/download/v1.0.1/cni-site-functions-1.0.1.zip',
		'url'     => 'https://github.com/cni-works/CNI-Site-Functions/releases/tag/v1.0.1',
	),
);
$updater = new GitHub_Release_Updater( cni_updater_config() );
$result  = $updater->filter_plugin_update( false, $headers, 'cni-site-functions/cni-site-functions.php', array() );
cni_assert_updater( '1.0.1' === $result['new_version'] && isset( $result['package'] ), 'a newer cached release produces update metadata' );

$filter_count = count( $cni_updater_filters );
new GitHub_Release_Updater( cni_updater_config( '1.0.0', 'CNI-Site-Functions/cni-site-functions.php' ) );
cni_assert_updater( $filter_count === count( $cni_updater_filters ), 'a noncanonical installation directory does not register the updater' );

unset( $cni_updater_transients[ $cache_key ] );
$cni_updater_response = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'draft'      => false,
			'prerelease' => false,
			'tag_name'   => 'v1.0.1',
			'html_url'   => 'https://github.com/cni-works/CNI-Site-Functions/releases/tag/v1.0.1',
			'assets'     => array(
				array(
					'name'                 => 'cni-site-functions-1.0.1.zip',
					'state'                => 'uploaded',
					'browser_download_url' => 'https://github.com/cni-works/another-repository/releases/download/v1.0.1/cni-site-functions-1.0.1.zip',
				),
			),
		)
	),
);
$updater = new GitHub_Release_Updater( cni_updater_config() );
$result  = $updater->filter_plugin_update( false, $headers, 'cni-site-functions/cni-site-functions.php', array() );
cni_assert_updater( ! isset( $result['package'] ), 'a Release Asset URL from another repository is rejected' );

exit( $failed > 0 ? 1 : 0 );
