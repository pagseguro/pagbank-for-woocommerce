<?php
/**
 * CNPJ validator (traditional numeric format).
 *
 * The CNPJ (Cadastro Nacional da Pessoa Jurídica) has 14 positions:
 * - First 8 positions: numeric base (company identifier)
 * - Positions 9-12: numeric order (branch identifier, 0001 for headquarters)
 * - Last 2 positions: numeric verification digits
 *
 * The verification digits are calculated using module 11.
 *
 * @package PagBank_WooCommerce\Validators
 */

namespace PagBank_WooCommerce\Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CNPJ.
 */
class CNPJ {

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
	 * The raw CNPJ value (only numeric characters).
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
	 * Removes all non-numeric characters.
	 *
	 * @param string $cnpj The CNPJ value.
	 *
	 * @return string The sanitized CNPJ (only numbers).
	 */
	private function sanitize( string $cnpj ): string {
		return preg_replace( '/[^0-9]/', '', $cnpj );
	}

	/**
	 * Check if the CNPJ is valid.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid(): bool {
		// Must have exactly 14 digits.
		if ( strlen( $this->value ) !== 14 ) {
			return false;
		}

		// Must be all numeric.
		if ( ! ctype_digit( $this->value ) ) {
			return false;
		}

		// Check for all identical digits (invalid).
		if ( preg_match( '/^(\d)\1{13}$/', $this->value ) ) {
			return false;
		}

		// Calculate verification digits.
		$base         = substr( $this->value, 0, 12 );
		$first_digit  = $this->calculate_digit( $base, self::WEIGHTS_FIRST );
		$second_digit = $this->calculate_digit( $base . $first_digit, self::WEIGHTS_SECOND );

		$calculated_digits = $first_digit . $second_digit;
		$provided_digits   = substr( $this->value, 12, 2 );

		return $calculated_digits === $provided_digits;
	}

	/**
	 * Calculate a single verification digit.
	 *
	 * @param string $base    The base string (12 or 13 digits).
	 * @param array  $weights The weights for multiplication.
	 *
	 * @return string The calculated digit (0-9).
	 */
	private function calculate_digit( string $base, array $weights ): string {
		$sum    = 0;
		$length = strlen( $base );

		for ( $i = 0; $i < $length; $i++ ) {
			$sum += (int) $base[ $i ] * $weights[ $i ];
		}

		$remainder = $sum % 11;

		// If remainder is less than 2, digit is 0; otherwise, digit is 11 - remainder.
		return (string) ( $remainder < 2 ? 0 : 11 - $remainder );
	}

	/**
	 * Format the CNPJ with standard mask.
	 *
	 * @return string Formatted CNPJ (00.000.000/0000-00) or original if invalid length.
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
	 * Static factory method for fluent API.
	 *
	 * @param string $cnpj The CNPJ value.
	 */
	public static function make( string $cnpj ): self {
		return new self( $cnpj );
	}
}
