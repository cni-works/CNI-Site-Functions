<?php
/**
 * Plugin Name: CNI Site Functions
 * Description: サイト固有のPHPコードを子テーマ更新から分離して管理します。
 * Version: 1.0.1
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Update URI: https://github.com/cni-works/CNI-Site-Functions
 * Author: Oishi Naoto
 * License: GPLv2 or later
 * Text Domain: cni-site-functions
 */

namespace CniWorks\CniSiteFunctions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CNI_SITE_FUNCTIONS_VERSION', '1.0.1' );
define( 'CNI_SITE_FUNCTIONS_FILE', __FILE__ );
define( 'CNI_SITE_FUNCTIONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CNI_SITE_FUNCTIONS_URL', plugin_dir_url( __FILE__ ) );

$cni_site_functions_updater_file = CNI_SITE_FUNCTIONS_DIR . 'includes/updater/class-github-release-updater.php';

if ( is_readable( $cni_site_functions_updater_file ) ) {
	require_once $cni_site_functions_updater_file;

	$cni_site_functions_headers = get_file_data(
		__FILE__,
		array(
			'version'      => 'Version',
			'update_uri'   => 'Update URI',
			'requires'     => 'Requires at least',
			'requires_php' => 'Requires PHP',
		),
		'plugin'
	);

	new \CniWorks\CniSiteFunctions\Updater\GitHub_Release_Updater(
		array(
			'type'          => 'plugin',
			'owner'         => 'cni-works',
			'repository'    => 'CNI-Site-Functions',
			'slug'          => 'cni-site-functions',
			'plugin_file'   => plugin_basename( __FILE__ ),
			'version'       => $cni_site_functions_headers['version'],
			'update_uri'    => $cni_site_functions_headers['update_uri'],
			'requires'      => $cni_site_functions_headers['requires'],
			'requires_php'  => $cni_site_functions_headers['requires_php'],
			'cache_hours'   => 12,
			'failure_hours' => 1,
			'timeout'       => 5,
		)
	);
}

require_once CNI_SITE_FUNCTIONS_DIR . 'includes/class-code-repository.php';
require_once CNI_SITE_FUNCTIONS_DIR . 'includes/class-code-validator.php';
require_once CNI_SITE_FUNCTIONS_DIR . 'includes/class-executor.php';
require_once CNI_SITE_FUNCTIONS_DIR . 'includes/class-admin-page.php';
require_once CNI_SITE_FUNCTIONS_DIR . 'includes/class-plugin.php';

Plugin::init();
