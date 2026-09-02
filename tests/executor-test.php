<?php
/**
 * Minimal CLI regression checks for Executor.
 */

define( 'ABSPATH', __DIR__ );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/cni-site-functions-executor-test-' . getmypid() );

mkdir( WP_CONTENT_DIR );

$cni_test_options   = array();
$cni_test_actions   = array();
$cni_test_shortcodes = array();
$cni_get_option_calls = 0;

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code, $message, $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function __( $text ) {
	return $text;
}

function get_option( $name, $default = false ) {
	global $cni_get_option_calls, $cni_test_options;
	$cni_get_option_calls++;
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

function trailingslashit( $path ) {
	return rtrim( $path, '/\\' ) . '/';
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function add_action( $hook, $callback, $priority = 10 ) {
	global $cni_test_actions;
	$cni_test_actions[] = array( $hook, $callback, $priority );
}

function add_shortcode( $tag, $callback ) {
	global $cni_test_shortcodes;
	$cni_test_shortcodes[ $tag ] = $callback;
}

require dirname( __DIR__ ) . '/includes/class-code-repository.php';
require dirname( __DIR__ ) . '/includes/class-code-validator.php';
require dirname( __DIR__ ) . '/includes/class-executor.php';

use CniWorks\CniSiteFunctions\Code_Repository;
use CniWorks\CniSiteFunctions\Executor;

$failed = 0;

function cni_assert_executor( $condition, $message ) {
	global $failed;
	if ( ! $condition ) {
		$failed++;
		fwrite( STDERR, "FAIL: {$message}\n" );
	} else {
		echo "PASS: {$message}\n";
	}
}

Executor::init();
cni_assert_executor( 'after_setup_theme' === $cni_test_actions[0][0] && 0 === $cni_test_actions[0][2], 'execution is registered on after_setup_theme priority 0' );

Code_Repository::save( "add_shortcode( 'disabled_test', function () { return 'disabled'; } );", false );
Executor::maybe_execute();
cni_assert_executor( ! isset( $cni_test_shortcodes['disabled_test'] ), 'disabled code is not executed' );

$shortcode_code = <<<'PHP'
function cni_executor_test_shortcode() {
	ob_start();
	?>
	<span>Executor OK</span>
	<?php
	return trim( ob_get_clean() );
}
add_shortcode( 'cni_executor_test', 'cni_executor_test_shortcode' );
PHP;
Code_Repository::save( $shortcode_code, true );
Executor::maybe_execute();
cni_assert_executor( isset( $cni_test_shortcodes['cni_executor_test'] ), 'an enabled shortcode is registered' );
cni_assert_executor( '<span>Executor OK</span>' === call_user_func( $cni_test_shortcodes['cni_executor_test'] ), 'mixed PHP and HTML shortcode executes in the global namespace' );

$stop_file = Executor::get_stop_file_path();
file_put_contents( $stop_file, '' );
$action_count = count( $cni_test_actions );
Executor::init();
cni_assert_executor( $action_count === count( $cni_test_actions ), 'the emergency stop file prevents execution-hook registration' );
Code_Repository::save( "add_shortcode( 'stop_file_test', function () { return 'blocked'; } );", true );
$cni_get_option_calls = 0;
Executor::maybe_execute();
cni_assert_executor( ! isset( $cni_test_shortcodes['stop_file_test'] ), 'the emergency stop file bypasses execution' );
cni_assert_executor( 0 === $cni_get_option_calls, 'the emergency stop file is checked before repository access' );
unlink( $stop_file );

Code_Repository::save( 'cni_missing_executor_test_function();', true );
Executor::maybe_execute();
$state = Code_Repository::get_state();
cni_assert_executor( false === $state['enabled'], 'a caught runtime Error automatically disables execution' );
cni_assert_executor( 'cni_missing_executor_test_function();' === $state['active_code'], 'runtime auto-stop preserves saved code' );

Code_Repository::save( "add_shortcode( 'tampered_test', function () { return 'tampered'; } );", true );
$cni_test_options[ Code_Repository::OPTION_NAME ]['active_code'] .= ' ';
Executor::maybe_execute();
$state = Code_Repository::get_state();
cni_assert_executor( false === $state['enabled'] && 'integrity_error' === $state['execution_error']['type'], 'a hash mismatch prevents execution and disables the code' );

$invalid_code = 'function invalid_syntax( {';
Code_Repository::save( $invalid_code, true );
$cni_test_options[ Code_Repository::OPTION_NAME ]['code_hash'] = hash( 'sha256', $invalid_code );
Executor::maybe_execute();
$state = Code_Repository::get_state();
cni_assert_executor( false === $state['enabled'] && 'validation_error' === $state['execution_error']['type'], 'execution-time validation rejects invalid saved code' );

$method = new ReflectionMethod( Executor::class, 'is_error_attributable_to_saved_code' );
$method->setAccessible( true );
$attributed = $method->invoke(
	null,
	array(
		'file'    => dirname( __DIR__ ) . "/includes/class-executor.php(18) : eval()'d code",
		'message' => 'Call to undefined function example()',
	)
);
$unattributed = $method->invoke(
	null,
	array(
		'file'    => '/wp-content/plugins/other-plugin/plugin.php',
		'message' => 'Unrelated plugin failure',
	)
);
cni_assert_executor( true === $attributed, 'an eval-origin fatal is attributable to saved code' );
cni_assert_executor( false === $unattributed, 'an unrelated fatal is not attributed to saved code' );

define( 'CNI_SITE_FUNCTIONS_SAFE_MODE', true );
$action_count = count( $cni_test_actions );
Executor::init();
cni_assert_executor( $action_count === count( $cni_test_actions ), 'the safe-mode constant prevents execution-hook registration' );
Code_Repository::save( "add_shortcode( 'constant_safe_mode_test', function () { return 'blocked'; } );", true );
$cni_get_option_calls = 0;
Executor::maybe_execute();
cni_assert_executor( ! isset( $cni_test_shortcodes['constant_safe_mode_test'] ), 'the safe-mode constant bypasses execution' );
cni_assert_executor( 0 === $cni_get_option_calls, 'the safe-mode constant is checked before repository access' );

rmdir( WP_CONTENT_DIR );
exit( $failed > 0 ? 1 : 0 );
