<?php
/**
 * Alphanumeric CNPJ validator (new format starting July 2026).
 *
 * The alphanumeric CNPJ has 14 positions:
 * - First 12 positions: alphanumeric (0-9, A-Z)
 * - Last 2 positions: numeric digits (verification digits)
 *
 * The verification digits are calculated using module 11, where each character
 * is converted to its ASCII code minus 48 (so '0' = 0, 'A' = 17, 'Z' = 42).
 *
 * @see https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/cnpj-alfanumerico
 * @see https://www.serpro.gov.br/menu/noticias/noticias-2024/cnpj-alfanumerico
 *
 * @package PagBank_WooCommerce\Validators
 */

namespace PagBank_WooCommerce\Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AlphanumericCNPJ.
 */
class AlphanumericCNPJ {

	/**
	 * Weights for first verification digit calculation.
	 *
	 * @var array
	 */
	private const WEIGHTS_FIRST = array( 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 );

	/**
	 * Weights for second verification digit calculation.
	 *
	 * @var array
	 */
	private const WEIGHTS_SECOND = array( 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 );

	/**
	 * The raw CNPJ value (only alphanumeric characters, uppercase).
	 */
	private string $value;

	/**
	 * The original input value.
	 */
	private string $original;

	/**
	 * Constructor.
	 *
	 * @param string $cnpj The CNPJ value (with or without formatting).
	 */
	public function __construct( string $cnpj ) {
		$this->original = $cnpj;
		$this->value    = $this->sanitize( $cnpj );
	}

	/**
	 * Sanitize the CNPJ value.
	 *
	 * Removes formatting characters and converts to uppercase.
	 *
	 * @param string $cnpj The CNPJ value.
	 *
	 * @return string The sanitized CNPJ (only alphanumeric, uppercase).
	 */
	private function sanitize( string $cnpj ): string {
		return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $cnpj ) );
	}

	/**
	 * Check if the CNPJ is valid.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid(): bool {
		// Must have exactly 14 characters.
		if ( strlen( $this->value ) !== 14 ) {
			return false;
		}

		// Last 2 characters must be numeric (verification digits).
		$verification_digits = substr( $this->value, 12, 2 );
		if ( ! ctype_digit( $verification_digits ) ) {
			return false;
		}

		// First 12 characters must be alphanumeric (0-9, A-Z).
		$base = substr( $this->value, 0, 12 );
		if ( ! preg_match( '/^[0-9A-Z]{12}$/', $base ) ) {
			return false;
		}

		// Check for all identical characters (invalid).
		if ( preg_match( '/^(.)\1{13}$/', $this->value ) ) {
			return false;
		}

		// Calculate verification digits.
		$first_digit  = $this->calculate_digit( $base, self::WEIGHTS_FIRST );
		$second_digit = $this->calculate_digit( $base . $first_digit, self::WEIGHTS_SECOND );

		$calculated_digits = $first_digit . $second_digit;

		return $verification_digits === $calculated_digits;
	}

	/**
	 * Calculate a single verification digit.
	 *
	 * @param string $base    The base string (12 or 13 characters).
	 * @param array  $weights The weights for multiplication.
	 *
	 * @return string The calculated digit (0-9).
	 */
	private function calculate_digit( string $base, array $weights ): string {
		$sum    = 0;
		$length = strlen( $base );

		for ( $i = 0; $i < $length; $i++ ) {
			// Convert character to numeric value using ASCII.
			// ASCII: '0' = 48, 'A' = 65, 'Z' = 90.
			// Formula: ASCII code - 48.
			// Result: '0' = 0, '9' = 9, 'A' = 17, 'Z' = 42.
			$value = ord( $base[ $i ] ) - 48;
			$sum  += $value * $weights[ $i ];
		}

		$remainder = $sum % 11;

		// If remainder is less than 2, digit is 0; otherwise, digit is 11 - remainder.
		return (string) ( $remainder < 2 ? 0 : 11 - $remainder );
	}

	/**
	 * Format the CNPJ with standard mask.
	 *
	 * @return string Formatted CNPJ (XX.XXX.XXX/XXXX-XX) or original if invalid length.
	 */
	public function format(): string {
		if ( strlen( $this->value ) !== 14 ) {
			return $this->original;
		}

		return sprintf(
			'%s.%s.%s/%s-%s',
			substr( $this->value, 0, 2 ),
			substr( $this->value, 2, 3 ),
			substr( $this->value, 5, 3 ),
			substr( $this->value, 8, 4 ),
			substr( $this->value, 12, 2 )
		);
	}

	/**
	 * Get the raw CNPJ value (sanitized, without formatting).
	 *
	 * @return string The raw CNPJ value.
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Get the original input value.
	 *
	 * @return string The original input value.
	 */
	public function get_original(): string {
		return $this->original;
	}

	/**
	 * Check if the CNPJ contains any alphabetic characters.
	 *
	 * This can be used to determine if a CNPJ is in the new alphanumeric format
	 * or the traditional numeric-only format.
	 *
	 * @return bool True if contains letters, false if numeric only.
	 */
	public function is_alphanumeric(): bool {
		return preg_match( '/[A-Z]/', $this->value ) === 1;
	}

	/**
	 * Static factory method for fluent API.
	 *
	 * @param string $cnpj The CNPJ value.
	 */
	public static function make( string $cnpj ): self {
		return new self( $cnpj );
	}
}
