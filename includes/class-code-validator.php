<?php
/**
 * PHP code validation.
 *
 * @package CniSiteFunctions
 */

namespace CniWorks\CniSiteFunctions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performs non-executing syntax and token validation.
 */
final class Code_Validator {

	/** Maximum stored code size in bytes. */
	const MAX_CODE_BYTES = 100000;

	/**
	 * Validate code without executing it.
	 *
	 * The editor starts in PHP mode, so an outer opening tag is not accepted.
	 * Closing and reopening PHP inside a function is accepted for inline HTML.
	 *
	 * @param string $code PHP code without outer opening or closing tags.
	 * @return true|\WP_Error
	 */
	public static function validate( $code ) {
		if ( strlen( $code ) > self::MAX_CODE_BYTES ) {
			return new \WP_Error(
				'code_too_large',
				__( 'PHPコードは100KB以内で入力してください。', 'cni-site-functions' )
			);
		}

		if ( false !== strpos( $code, "\0" ) ) {
			return new \WP_Error(
				'null_byte',
				__( 'PHPコードに使用できない文字が含まれています。', 'cni-site-functions' )
			);
		}

		if ( preg_match( '/\A\s*<\?(?:php|=)?/i', $code ) ) {
			return new \WP_Error(
				'php_tag_not_allowed',
				__( 'PHPの開始タグと終了タグは入力不要です。', 'cni-site-functions' ),
				array( 'line' => 1 )
			);
		}

		$source                 = "<?php\n" . $code;
		$raw_tokens             = token_get_all( $source );
		$synthetic_opening_seen = false;
		$has_php_content        = false;
		$in_inline_html         = false;
		$last_close_line        = 0;

		foreach ( $raw_tokens as $token ) {
			if ( ! is_array( $token ) ) {
				if ( ! $in_inline_html && '' !== trim( $token ) ) {
					$has_php_content = true;
				}
				continue;
			}

			list( $token_id, $token_text, $token_line ) = $token;
			$input_line                                  = max( 1, (int) $token_line - 1 );

			if ( T_OPEN_TAG === $token_id && ! $synthetic_opening_seen ) {
				$synthetic_opening_seen = true;
				continue;
			}

			if ( T_CLOSE_TAG === $token_id ) {
				if ( ! $has_php_content ) {
					return new \WP_Error(
						'php_tag_not_allowed',
						__( '入力欄はPHPモードから始まるため、先頭のPHPタグは入力不要です。', 'cni-site-functions' ),
						array( 'line' => $input_line )
					);
				}

				$in_inline_html  = true;
				$last_close_line = $input_line;
				continue;
			}

			if ( in_array( $token_id, array( T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO ), true ) ) {
				if ( ! $in_inline_html ) {
					return new \WP_Error(
						'php_tag_not_allowed',
						__( '入力欄の先頭にPHP開始タグは入力しないでください。', 'cni-site-functions' ),
						array( 'line' => $input_line )
					);
				}

				$in_inline_html = false;
				continue;
			}

			if ( ! $in_inline_html && ! in_array( $token_id, array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) && '' !== trim( $token_text ) ) {
				$has_php_content = true;
			}
		}

		if ( $in_inline_html ) {
			return new \WP_Error(
				'php_tag_not_allowed',
				__( 'HTML出力後は <?php でPHPへ戻してください。入力欄末尾の終了タグは不要です。', 'cni-site-functions' ),
				array( 'line' => $last_close_line )
			);
		}

		try {
			$tokens = token_get_all( $source, TOKEN_PARSE );
		} catch ( \ParseError $error ) {
			$line = max( 1, (int) $error->getLine() - 1 );

			return new \WP_Error(
				'php_syntax_error',
				$error->getMessage(),
				array( 'line' => $line )
			);
		}

		$forbidden = array(
			T_EVAL          => 'eval',
			T_EXIT          => 'exit / die',
			T_HALT_COMPILER => '__halt_compiler',
			T_NAMESPACE     => 'namespace',
			T_INCLUDE       => 'include',
			T_INCLUDE_ONCE  => 'include_once',
			T_REQUIRE       => 'require',
			T_REQUIRE_ONCE  => 'require_once',
			T_DECLARE       => 'declare',
		);

		foreach ( $tokens as $token ) {
			if ( ! is_array( $token ) ) {
				continue;
			}

			list( $token_id, , $token_line ) = $token;
			$input_line                      = max( 1, (int) $token_line - 1 );

			if ( isset( $forbidden[ $token_id ] ) ) {
				return new \WP_Error(
					'forbidden_php_token',
					sprintf(
						/* translators: %s: PHP construct. */
						__( '%s は使用できません。入力欄内で完結するコードにしてください。', 'cni-site-functions' ),
						$forbidden[ $token_id ]
					),
					array(
						'line'  => $input_line,
						'token' => $forbidden[ $token_id ],
					)
				);
			}
		}

		return true;
	}
}
