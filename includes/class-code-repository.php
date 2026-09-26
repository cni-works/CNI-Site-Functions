<?php
/**
 * Persistent code state.
 *
 * @package CniSiteFunctions
 */

namespace CniWorks\CniSiteFunctions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores the active and previous code in one atomic option.
 */
final class Code_Repository {

	/** WordPress option name. */
	const OPTION_NAME = 'cni_site_functions_state';

	/**
	 * Return an empty state.
	 *
	 * @return array<string,mixed>
	 */
	private static function defaults() {
		return array(
			'schema_version' => 3,
			'enabled'        => false,
			'active_code'    => '',
			'previous_code'  => '',
			'has_previous'   => false,
			'updated_at'     => '',
			'code_hash'      => hash( 'sha256', '' ),
			'execution_error' => array(),
		);
	}

	/**
	 * Get a normalized state.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_state() {
		$stored = get_option( self::OPTION_NAME, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$state  = array_merge( self::defaults(), $stored );

		$state['schema_version'] = 3;
		$state['enabled']        = (bool) $state['enabled'];
		$state['active_code']    = is_string( $state['active_code'] ) ? $state['active_code'] : '';
		$state['previous_code']  = is_string( $state['previous_code'] ) ? $state['previous_code'] : '';
		$state['has_previous']   = (bool) $state['has_previous'];
		$state['updated_at']     = is_string( $state['updated_at'] ) ? $state['updated_at'] : '';
		$state['code_hash']      = is_string( $state['code_hash'] ) ? $state['code_hash'] : '';
		$state['execution_error'] = self::normalize_execution_error( $state['execution_error'] );

		return $state;
	}

	/**
	 * Save validated code and its desired enabled state.
	 *
	 * A toggle-only change does not rotate the previous-code slot.
	 *
	 * @param string $code    Validated PHP code without PHP tags.
	 * @param bool   $enabled Desired enabled state.
	 * @return void
	 */
	public static function save( $code, $enabled ) {
		$state = self::get_state();

		if ( $code !== $state['active_code'] ) {
			$state['previous_code'] = $state['active_code'];
			$state['has_previous']  = true;
		}

		$state['active_code'] = $code;
		$state['enabled']     = (bool) $enabled;
		$state['updated_at']  = current_time( 'mysql', true );
		$state['code_hash']   = hash( 'sha256', $code );

		update_option( self::OPTION_NAME, $state );
	}

	/**
	 * Swap the active and previous saved code.
	 *
	 * @return bool Whether a previous version existed.
	 */
	public static function restore_previous() {
		$state = self::get_state();

		if ( ! $state['has_previous'] ) {
			return false;
		}

		$current                = $state['active_code'];
		$state['active_code']    = $state['previous_code'];
		$state['previous_code']  = $current;
		$state['has_previous']   = true;
		$state['updated_at']     = current_time( 'mysql', true );
		$state['code_hash']      = hash( 'sha256', $state['active_code'] );

		update_option( self::OPTION_NAME, $state );

		return true;
	}

	/**
	 * Disable execution and retain an administrator-visible error summary.
	 *
	 * @param string $type    Error category.
	 * @param string $message Error message.
	 * @param string $file    Source file when available.
	 * @param int    $line    Source line when available.
	 * @return void
	 */
	public static function disable_with_error( $type, $message, $file = '', $line = 0 ) {
		self::record_execution_issue( $type, $message, $file, $line, true );
	}

	/**
	 * Store an execution issue and optionally disable future execution.
	 *
	 * @param string $type         Error category.
	 * @param string $message      Error message.
	 * @param string $file         Source file when available.
	 * @param int    $line         Source line when available.
	 * @param bool   $auto_disable Whether the failure is attributable to saved code.
	 * @return void
	 */
	public static function record_execution_issue( $type, $message, $file = '', $line = 0, $auto_disable = false ) {
		$state = self::get_state();

		if ( $auto_disable ) {
			$state['enabled'] = false;
		}

		$previous = $state['execution_error'];
		$error = self::normalize_execution_error( array(
			'type'          => is_string( $type ) ? $type : 'runtime_error',
			'message'       => is_string( $message ) ? $message : '',
			'file'          => is_string( $file ) ? $file : '',
			'line'          => max( 0, (int) $line ),
			'occurred_at'   => current_time( 'mysql', true ),
			'auto_disabled' => (bool) $auto_disable,
		) );
		if ( ! empty( $previous ) && ! empty( $error ) && $previous['fingerprint'] === $error['fingerprint'] ) {
			$error['first_seen_at'] = $previous['first_seen_at'];
			$error['count'] = min( PHP_INT_MAX - 1, $previous['count'] ) + 1;
			$error['auto_disabled'] = $previous['auto_disabled'] || $auto_disable;
			$error['attributable'] = $previous['attributable'] || $auto_disable;
		}
		$state['execution_error'] = $error;

		update_option( self::OPTION_NAME, $state );
	}

	/** Clear diagnostics without changing code or enabling execution. */
	public static function clear_execution_error() {
		$state = self::get_state();
		$state['execution_error'] = array();
		update_option( self::OPTION_NAME, $state );
	}

	/**
	 * Normalize a stored execution error.
	 *
	 * @param mixed $error Stored value.
	 * @return array<string,mixed>
	 */
	private static function normalize_execution_error( $error ) {
		if ( ! is_array( $error ) || empty( $error['message'] ) || ! is_string( $error['message'] ) ) {
			return array();
		}

		$normalized = array(
			'type'          => isset( $error['type'] ) && is_string( $error['type'] ) ? $error['type'] : 'runtime_error',
			'message'       => $error['message'],
			'file'          => isset( $error['file'] ) && is_string( $error['file'] ) ? $error['file'] : '',
			'line'          => isset( $error['line'] ) ? max( 0, (int) $error['line'] ) : 0,
			'occurred_at'   => isset( $error['occurred_at'] ) && is_string( $error['occurred_at'] ) ? $error['occurred_at'] : '',
			'auto_disabled' => ! empty( $error['auto_disabled'] ),
		);
		$normalized['attributable'] = isset( $error['attributable'] ) ? (bool) $error['attributable'] : $normalized['auto_disabled'];
		$normalized['first_seen_at'] = isset( $error['first_seen_at'] ) && is_string( $error['first_seen_at'] ) ? $error['first_seen_at'] : $normalized['occurred_at'];
		$normalized['last_seen_at'] = isset( $error['last_seen_at'] ) && is_string( $error['last_seen_at'] ) ? $error['last_seen_at'] : $normalized['occurred_at'];
		$normalized['occurred_at'] = $normalized['last_seen_at']; // Compatibility with older readers.
		$normalized['count'] = isset( $error['count'] ) ? max( 1, (int) $error['count'] ) : 1;
		$normalized['fingerprint'] = hash( 'sha256', serialize( array( $normalized['type'], $normalized['message'], $normalized['file'], $normalized['line'] ) ) );
		return $normalized;
	}
}
