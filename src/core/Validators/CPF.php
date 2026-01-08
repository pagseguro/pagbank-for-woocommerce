<?php
/**
 * CPF validator.
 *
 * The CPF (Cadastro de Pessoas Físicas) has 11 positions:
 * - First 9 positions: numeric base
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
 * Class CPF.
 */
class CPF {

	/**
	 * The raw CPF value (only numeric characters).
	 */
	private string $value;

	/**
	 * The original input value.
	 */
	private string $original;

	/**
	 * Constructor.
	 *
	 * @param string $cpf The CPF value (with or without formatting).
	 */
	public function __construct( string $cpf ) {
		$this->original = $cpf;
		$this->value    = $this->sanitize( $cpf );
	}

	/**
	 * Sanitize the CPF value.
	 *
	 * Removes all non-numeric characters.
	 *
	 * @param string $cpf The CPF value.
	 *
	 * @return string The sanitized CPF (only numbers).
	 */
	private function sanitize( string $cpf ): string {
		return preg_replace( '/[^0-9]/', '', $cpf );
	}

	/**
	 * Check if the CPF is valid.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid(): bool {
		// Must have exactly 11 digits.
		if ( strlen( $this->value ) !== 11 ) {
			return false;
		}

		// Must be all numeric.
		if ( ! ctype_digit( $this->value ) ) {
			return false;
		}

		// Check for all identical digits (invalid).
		if ( preg_match( '/^(\d)\1{10}$/', $this->value ) ) {
			return false;
		}

		// Calculate verification digits.
		$first_digit  = $this->calculate_digit( substr( $this->value, 0, 9 ), 10 );
		$second_digit = $this->calculate_digit( substr( $this->value, 0, 10 ), 11 );

		$calculated_digits = $first_digit . $second_digit;
		$provided_digits   = substr( $this->value, 9, 2 );

		return $calculated_digits === $provided_digits;
	}

	/**
	 * Calculate a single verification digit.
	 *
	 * @param string $base           The base string (9 or 10 digits).
	 * @param int    $initial_weight The initial weight for multiplication.
	 *
	 * @return string The calculated digit (0-9).
	 */
	private function calculate_digit( string $base, int $initial_weight ): string {
		$sum    = 0;
		$weight = $initial_weight;
		$length = strlen( $base );

		for ( $i = 0; $i < $length; $i++ ) {
			$sum += (int) $base[ $i ] * $weight;
			--$weight;
		}

		$remainder = $sum % 11;

		// If remainder is less than 2, digit is 0; otherwise, digit is 11 - remainder.
		return (string) ( $remainder < 2 ? 0 : 11 - $remainder );
	}

	/**
	 * Format the CPF with standard mask.
	 *
	 * @return string Formatted CPF (000.000.000-00) or original if invalid length.
	 */
	public function format(): string {
		if ( strlen( $this->value ) !== 11 ) {
			return $this->original;
		}

		return sprintf(
			'%s.%s.%s-%s',
			substr( $this->value, 0, 3 ),
			substr( $this->value, 3, 3 ),
			substr( $this->value, 6, 3 ),
			substr( $this->value, 9, 2 )
		);
	}

	/**
	 * Get the raw CPF value (sanitized, without formatting).
	 *
	 * @return string The raw CPF value.
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
	 * @param string $cpf The CPF value.
	 */
	public static function make( string $cpf ): self {
		return new self( $cpf );
	}
}
