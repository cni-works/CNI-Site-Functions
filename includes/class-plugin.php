<?php
/**
 * Plugin bootstrap.
 *
 * @package CniSiteFunctions
 */

namespace CniWorks\CniSiteFunctions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates the plugin features.
 */
final class Plugin {

	/**
	 * Register plugin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		Executor::init();
		Admin_Page::init();
	}
}
