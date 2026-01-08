<?php
/**
 * Detects implicitly nullable parameters (deprecated in PHP 8.4).
 *
 * @package PagBank_WooCommerce\PHPCSSniffs
 */

namespace PagBankStandard\Sniffs\FunctionDeclarations;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Class ImplicitlyNullableParamSniff.
 *
 * Detects function/method parameters that have a type declaration and a default
 * value of null, but are not explicitly nullable with the `?` prefix.
 *
 * Example of what this sniff detects:
 * - function foo(string $param = null) - BAD
 * - function foo(?string $param = null) - GOOD
 */
class ImplicitlyNullableParamSniff implements Sniff {

	/**
	 * Returns the token types that this sniff is interested in.
	 *
	 * @return array
	 */
	public function register(): array {
		return array(
			T_FUNCTION,
			T_CLOSURE,
			T_FN,
		);
	}

	/**
	 * Processes this sniff when one of its tokens is encountered.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ): void {
		$params = $phpcs_file->getMethodParameters( $stack_ptr );

		foreach ( $params as $param ) {
			// Skip parameters without type hints.
			if ( empty( $param['type_hint'] ) ) {
				continue;
			}

			// Skip parameters without default values.
			if ( ! isset( $param['default'] ) ) {
				continue;
			}

			// Check if the default value is null.
			$default = strtolower( trim( $param['default'] ) );
			if ( 'null' !== $default ) {
				continue;
			}

			// Check if the type is already nullable.
			if ( true === $param['nullable_type'] ) {
				continue;
			}

			// Check for union types that include null.
			$type_hint = $param['type_hint'];
			if ( strpos( $type_hint, '|' ) !== false ) {
				$types = explode( '|', $type_hint );
				$has_null = false;
				foreach ( $types as $type ) {
					if ( strtolower( trim( $type ) ) === 'null' ) {
						$has_null = true;
						break;
					}
				}
				if ( $has_null ) {
					continue;
				}
			}

			// Report the error.
			$phpcs_file->addError(
				'Implicitly nullable parameter %s is deprecated in PHP 8.4. Use ?%s instead of %s.',
				$param['token'],
				'Deprecated',
				array( $param['name'], $type_hint, $type_hint )
			);
		}
	}
}
