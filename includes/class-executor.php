<?php
/**
 * Executes the validated site-specific code in the global namespace.
 *
 * @package CniSiteFunctions
 */

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Keep eval out of every component except the dedicated Executor module.
	 *
	 * @param string $code Validated PHP code.
	 * @return void
	 */
	function cni_site_functions_execute_validated_code( $code ) {
		eval( $code );
	}
}

namespace CniWorks\CniSiteFunctions {
	/**
	 * Runs saved code only after all safety gates have passed.
	 */
	final class Executor {

		/** Whether saved code was loaded during this request. */
		private static $monitor_fatals = false;

		/** Whether the shutdown handler has been registered. */
		private static $shutdown_registered = false;

		/**
		 * Register execution after the active theme has loaded.
		 *
		 * @return void
		 */
		public static function init() {
			if ( '' !== self::get_safe_mode_reason() ) {
				return;
			}

			add_action( 'after_setup_theme', array( __CLASS__, 'maybe_execute' ), 0 );
		}

		/**
		 * Return the active safe-mode source, or an empty string.
		 *
		 * @return string
		 */
		public static function get_safe_mode_reason() {
			if ( defined( 'CNI_SITE_FUNCTIONS_SAFE_MODE' ) && true === CNI_SITE_FUNCTIONS_SAFE_MODE ) {
				return 'constant';
			}

			if ( file_exists( self::get_stop_file_path() ) ) {
				return 'stop_file';
			}

			return '';
		}

		/**
		 * Return the emergency stop-file path.
		 *
		 * @return string
		 */
		public static function get_stop_file_path() {
			return trailingslashit( WP_CONTENT_DIR ) . '.cni-site-functions-safe-mode';
		}

		/**
		 * Execute only an enabled, non-empty, unchanged and validated code string.
		 *
		 * @return void
		 */
		public static function maybe_execute() {
			if ( '' !== self::get_safe_mode_reason() ) {
				return;
			}

			$state = Code_Repository::get_state();

			if ( ! $state['enabled'] || '' === trim( $state['active_code'] ) ) {
				return;
			}

			$actual_hash = hash( 'sha256', $state['active_code'] );
			if ( '' === $state['code_hash'] || ! hash_equals( $state['code_hash'], $actual_hash ) ) {
				Code_Repository::disable_with_error(
					'integrity_error',
					__( '保存コードの整合性を確認できなかったため、自動停止しました。', 'cni-site-functions' )
				);
				return;
			}

			$validation = Code_Validator::validate( $state['active_code'] );
			if ( is_wp_error( $validation ) ) {
				Code_Repository::disable_with_error(
					'validation_error',
					$validation->get_error_message()
				);
				return;
			}

			self::register_shutdown_monitor();
			self::$monitor_fatals = true;

			try {
				\cni_site_functions_execute_validated_code( $state['active_code'] );
			} catch ( \Throwable $error ) {
				self::$monitor_fatals = false;
				Code_Repository::disable_with_error(
					'runtime_error',
					$error->getMessage(),
					$error->getFile(),
					$error->getLine()
				);
			}
		}

		/**
		 * Register a last-resort fatal monitor once per request.
		 *
		 * @return void
		 */
		private static function register_shutdown_monitor() {
			if ( self::$shutdown_registered ) {
				return;
			}

			self::$shutdown_registered = true;
			register_shutdown_function( array( __CLASS__, 'handle_shutdown' ) );
		}

		/**
		 * Record a fatal error later in the request.
		 *
		 * @return void
		 */
		public static function handle_shutdown() {
			if ( ! self::$monitor_fatals ) {
				return;
			}

			$error = error_get_last();
			if ( ! is_array( $error ) || ! in_array( $error['type'], self::fatal_error_types(), true ) ) {
				return;
			}

			self::$monitor_fatals = false;
			$attributable = self::is_error_attributable_to_saved_code( $error );
			Code_Repository::record_execution_issue(
				$attributable ? 'fatal_error' : 'unconfirmed_fatal_error',
				isset( $error['message'] ) ? $error['message'] : __( '保存コードの読み込み後にFatal Errorが発生しました。', 'cni-site-functions' ),
				isset( $error['file'] ) ? $error['file'] : '',
				isset( $error['line'] ) ? (int) $error['line'] : 0,
				$attributable
			);
		}

		/**
		 * Determine whether a fatal points back to code evaluated by this module.
		 *
		 * Unknown failures are recorded but are not automatically attributed.
		 *
		 * @param array<string,mixed> $error Last PHP error.
		 * @return bool
		 */
		private static function is_error_attributable_to_saved_code( $error ) {
			$file    = isset( $error['file'] ) && is_string( $error['file'] ) ? $error['file'] : '';
			$message = isset( $error['message'] ) && is_string( $error['message'] ) ? $error['message'] : '';
			$context = strtolower( str_replace( '\\', '/', $file . "\n" . $message ) );

			$has_executor_reference = false !== strpos( $context, strtolower( basename( __FILE__ ) ) )
				|| false !== strpos( $context, 'cni_site_functions_execute_validated_code' );
			$has_eval_reference = false !== strpos( $context, "eval()'d code" );

			return $has_executor_reference && $has_eval_reference;
		}

		/**
		 * Return errors that stop normal PHP execution.
		 *
		 * @return int[]
		 */
		private static function fatal_error_types() {
			return array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );
		}
	}
}
