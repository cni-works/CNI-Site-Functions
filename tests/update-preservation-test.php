<?php
/**
 * Confirms that a previously stored state is normalized without data loss.
 */

define( 'ABSPATH', __DIR__ );

$saved_code = "function cni_update_test_shortcode() { return 'preserved'; }";
$previous_code = "function cni_update_test_shortcode() { return 'previous'; }";
$cni_test_options = array(
	'cni_site_functions_state' => array(
		'schema_version'  => 2,
		'enabled'         => true,
		'active_code'     => $saved_code,
		'previous_code'   => $previous_code,
		'has_previous'    => true,
		'updated_at'      => '2026-09-02 00:00:00',
		'code_hash'       => hash( 'sha256', $saved_code ),
		'execution_error' => array(
			'type'          => 'runtime_error',
			'message'       => 'Preserved diagnostic',
			'file'          => 'site-functions.php',
			'line'          => 12,
			'occurred_at'   => '2026-09-02 00:01:00',
			'auto_disabled' => false,
		),
	),
);

function get_option( $name, $default = false ) {
	global $cni_test_options;
	return array_key_exists( $name, $cni_test_options ) ? $cni_test_options[ $name ] : $default;
}

function update_option( $name, $value ) {
	global $cni_test_options;
	$cni_test_options[ $name ] = $value;
	return true;
}

function current_time() {
	return '2026-09-03 00:00:00';
}

require dirname( __DIR__ ) . '/includes/class-code-repository.php';

$state = CniWorks\CniSiteFunctions\Code_Repository::get_state();
$checks = array(
	'active_code'     => $saved_code === $state['active_code'],
	'enabled'         => true === $state['enabled'],
	'previous_code'   => $previous_code === $state['previous_code'] && true === $state['has_previous'],
	'code_hash'       => hash( 'sha256', $saved_code ) === $state['code_hash'],
	'execution_error' => 'Preserved diagnostic' === $state['execution_error']['message'] && false === $state['execution_error']['auto_disabled'],
);

$failed = 0;
foreach ( $checks as $name => $passed ) {
	if ( $passed ) {
		echo "PASS: {$name} is preserved across a plugin file update\n";
	} else {
		$failed++;
		fwrite( STDERR, "FAIL: {$name} was not preserved\n" );
	}
}

exit( $failed > 0 ? 1 : 0 );
