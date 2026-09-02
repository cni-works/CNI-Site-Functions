<?php
/**
 * Minimal CLI regression checks for Code_Validator.
 */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code, $message, $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

function __( $text ) {
	return $text;
}

require dirname( __DIR__ ) . '/includes/class-code-validator.php';

use CniWorks\CniSiteFunctions\Code_Validator;

$cases = array(
	'valid shortcode' => array(
		"function cni_test_shortcode() { return 'TEST'; }\nadd_shortcode( 'cni_test', 'cni_test_shortcode' );",
		true,
	),
	'syntax error' => array(
		'function broken( {',
		'php_syntax_error',
	),
	'opening tag' => array(
		"?><?php echo 'x';",
		'php_tag_not_allowed',
	),
	'direct opening tag' => array(
		"<?php echo 'x';",
		'php_tag_not_allowed',
	),
	'trailing closing tag' => array(
		"function example() {}\n?>",
		'php_tag_not_allowed',
	),
	'mixed PHP and HTML shortcode' => array(
		"function cni_mixed_shortcode() {\n\tob_start();\n\t?>\n\t<div class=\"example\">\n\t\t<?php echo esc_html( 'Example' ); ?>\n\t</div>\n\t<?php\n\treturn ob_get_clean();\n}\nadd_shortcode( 'cni_mixed', 'cni_mixed_shortcode' );",
		true,
	),
	'multiple PHP and HTML transitions' => array(
		"function cni_multiple_transitions() {\n\t?>\n\t<p><?php echo 'One'; ?></p>\n\t<p><?= 'Two'; ?></p>\n\t<?php\n}",
		true,
	),
	'eval' => array(
		"eval( '\$x = 1;' );",
		'forbidden_php_token',
	),
	'exit' => array(
		'exit;',
		'forbidden_php_token',
	),
	'require once' => array(
		"require_once 'example.php';",
		'forbidden_php_token',
	),
	'duplicate functions are runtime-valid syntax' => array(
		'function duplicate_name() {} function duplicate_name() {}',
		true,
	),
);

$failed = 0;

foreach ( $cases as $label => $case ) {
	$result = Code_Validator::validate( $case[0] );
	$actual = true === $result ? true : $result->get_error_code();

	if ( $actual !== $case[1] ) {
		$failed++;
		fwrite( STDERR, "FAIL: {$label} (expected " . var_export( $case[1], true ) . ', got ' . var_export( $actual, true ) . ")\n" );
	} else {
		echo "PASS: {$label}\n";
	}
}

exit( $failed > 0 ? 1 : 0 );
