<?php
/**
 * Minimal CLI regression checks for Code_Repository.
 */

define( 'ABSPATH', __DIR__ );

$cni_test_options = array();

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
	return '2026-09-02 00:00:00';
}

require dirname( __DIR__ ) . '/includes/class-code-repository.php';

use CniWorks\CniSiteFunctions\Code_Repository;

$failed = 0;

function cni_assert_repository( $condition, $message ) {
	global $failed;

	if ( ! $condition ) {
		$failed++;
		fwrite( STDERR, "FAIL: {$message}\n" );
	} else {
		echo "PASS: {$message}\n";
	}
}

$state = Code_Repository::get_state();
cni_assert_repository( false === $state['enabled'] && '' === $state['active_code'] && false === $state['has_previous'], 'empty default state' );

Code_Repository::save( 'first();', true );
$state = Code_Repository::get_state();
cni_assert_repository( true === $state['enabled'] && 'first();' === $state['active_code'], 'first code and enabled state are saved' );
cni_assert_repository( true === $state['has_previous'] && '' === $state['previous_code'], 'the initial empty code can be restored' );

Code_Repository::save( 'first();', false );
$state = Code_Repository::get_state();
cni_assert_repository( false === $state['enabled'] && '' === $state['previous_code'], 'a toggle-only save does not rotate previous code' );

Code_Repository::save( 'second();', false );
$state = Code_Repository::get_state();
cni_assert_repository( 'second();' === $state['active_code'] && 'first();' === $state['previous_code'], 'a code change retains the previous version' );

$restored = Code_Repository::restore_previous();
$state    = Code_Repository::get_state();
cni_assert_repository( true === $restored && 'first();' === $state['active_code'] && 'second();' === $state['previous_code'], 'restore swaps current and previous versions' );
cni_assert_repository( hash( 'sha256', 'first();' ) === $state['code_hash'], 'active code hash is refreshed' );

Code_Repository::save( 'runtime_failure();', true );
Code_Repository::disable_with_error( 'runtime_error', 'Test failure', 'site-functions.php', 12 );
$state = Code_Repository::get_state();
cni_assert_repository( false === $state['enabled'], 'an attributable runtime error disables execution' );
cni_assert_repository( 'runtime_failure();' === $state['active_code'], 'automatic disabling preserves the saved code' );
cni_assert_repository( true === $state['execution_error']['auto_disabled'], 'automatic disabling is recorded' );

Code_Repository::save( 'unknown_source();', true );
Code_Repository::record_execution_issue( 'unconfirmed_fatal_error', 'Unknown failure', 'other-plugin.php', 20, false );
$state = Code_Repository::get_state();
cni_assert_repository( true === $state['enabled'], 'an unattributed fatal does not disable execution' );
cni_assert_repository( false === $state['execution_error']['auto_disabled'], 'an unattributed fatal is recorded as a warning' );

$cni_test_options[ Code_Repository::OPTION_NAME ]['active_code'] = 'tampered();';
$state = Code_Repository::get_state();
cni_assert_repository( hash( 'sha256', 'unknown_source();' ) === $state['code_hash'], 'normalization does not conceal a code-hash mismatch' );

exit( $failed > 0 ? 1 : 0 );
