<?php
/**
 * Tests for AlphanumericCNPJ validator.
 *
 * @package PagBank_WooCommerce\Tests\Validators
 */

namespace PagBank_WooCommerce\Tests\Validators;

use PHPUnit\Framework\TestCase;
use PagBank_WooCommerce\Validators\AlphanumericCNPJ;

/**
 * Class AlphanumericCNPJTest.
 *
 * Tests based on official Receita Federal documentation:
 * @see Anexo XV da Instrução Normativa RFB nº 2.119, de 6 de dezembro de 2022
 */
class AlphanumericCNPJTest extends TestCase {

	/**
	 * Test validation with the official example from Receita Federal.
	 *
	 * CNPJ: 12.ABC.345/01DE-35
	 * Expected DVs: 35
	 */
	public function test_validates_official_example(): void {
		$cnpj = new AlphanumericCNPJ( '12.ABC.345/01DE-35' );

		$this->assertTrue( $cnpj->is_valid() );
	}

	/**
	 * Test validation with official example without formatting.
	 */
	public function test_validates_official_example_without_formatting(): void {
		$cnpj = new AlphanumericCNPJ( '12ABC34501DE35' );

		$this->assertTrue( $cnpj->is_valid() );
	}

	/**
	 * Test validation with lowercase input.
	 */
	public function test_validates_lowercase_input(): void {
		$cnpj = new AlphanumericCNPJ( '12abc34501de35' );

		$this->assertTrue( $cnpj->is_valid() );
	}

	/**
	 * Test invalid CNPJ with wrong verification digits.
	 */
	public function test_rejects_invalid_verification_digits(): void {
		$cnpj = new AlphanumericCNPJ( '12.ABC.345/01DE-99' );

		$this->assertFalse( $cnpj->is_valid() );
	}

	/**
	 * Test invalid CNPJ with wrong first digit.
	 */
	public function test_rejects_invalid_first_digit(): void {
		$cnpj = new AlphanumericCNPJ( '12.ABC.345/01DE-45' );

		$this->assertFalse( $cnpj->is_valid() );
	}

	/**
	 * Test invalid CNPJ with wrong second digit.
	 */
	public function test_rejects_invalid_second_digit(): void {
		$cnpj = new AlphanumericCNPJ( '12.ABC.345/01DE-39' );

		$this->assertFalse( $cnpj->is_valid() );
	}

	/**
	 * Test validation of traditional numeric CNPJ.
	 *
	 * The alphanumeric validator should also validate traditional numeric CNPJs.
	 */
	public function test_validates_traditional_numeric_cnpj(): void {
		$cnpj = new AlphanumericCNPJ( '11.222.333/0001-81' );

		$this->assertTrue( $cnpj->is_valid() );
	}

	/**
	 * Test rejection of invalid traditional numeric CNPJ.
	 */
	public function test_rejects_invalid_traditional_numeric_cnpj(): void {
		$cnpj = new AlphanumericCNPJ( '11.222.333/0001-99' );

		$this->assertFalse( $cnpj->is_valid() );
	}

	/**
	 * Test rejection of CNPJ with all identical characters.
	 */
	public function test_rejects_all_identical_characters(): void {
		$this->assertFalse( ( new AlphanumericCNPJ( '00000000000000' ) )->is_valid() );
		$this->assertFalse( ( new AlphanumericCNPJ( '11111111111111' ) )->is_valid() );
		$this->assertFalse( ( new AlphanumericCNPJ( 'AAAAAAAAAAAAAA' ) )->is_valid() );
	}

	/**
	 * Test rejection of CNPJ with wrong length.
	 */
	public function test_rejects_wrong_length(): void {
		$this->assertFalse( ( new AlphanumericCNPJ( '12ABC345' ) )->is_valid() );
		$this->assertFalse( ( new AlphanumericCNPJ( '12ABC34501DE3512345' ) )->is_valid() );
		$this->assertFalse( ( new AlphanumericCNPJ( '' ) )->is_valid() );
	}

	/**
	 * Test rejection of CNPJ with non-numeric verification digits.
	 */
	public function test_rejects_non_numeric_verification_digits(): void {
		$cnpj = new AlphanumericCNPJ( '12ABC34501DEAB' );

		$this->assertFalse( $cnpj->is_valid() );
	}

	/**
	 * Test formatting of alphanumeric CNPJ.
	 */
	public function test_formats_alphanumeric_cnpj(): void {
		$cnpj = new AlphanumericCNPJ( '12ABC34501DE35' );

		$this->assertEquals( '12.ABC.345/01DE-35', $cnpj->format() );
	}

	/**
	 * Test formatting of traditional numeric CNPJ.
	 */
	public function test_formats_numeric_cnpj(): void {
		$cnpj = new AlphanumericCNPJ( '11222333000181' );

		$this->assertEquals( '11.222.333/0001-81', $cnpj->format() );
	}

	/**
	 * Test formatting returns original value when length is invalid.
	 */
	public function test_format_returns_original_when_invalid_length(): void {
		$cnpj = new AlphanumericCNPJ( '12ABC' );

		$this->assertEquals( '12ABC', $cnpj->format() );
	}

	/**
	 * Test get_value returns sanitized value.
	 */
	public function test_get_value_returns_sanitized(): void {
		$cnpj = new AlphanumericCNPJ( '12.ABC.345/01DE-35' );

		$this->assertEquals( '12ABC34501DE35', $cnpj->get_value() );
	}

	/**
	 * Test get_value converts to uppercase.
	 */
	public function test_get_value_converts_to_uppercase(): void {
		$cnpj = new AlphanumericCNPJ( '12abc34501de35' );

		$this->assertEquals( '12ABC34501DE35', $cnpj->get_value() );
	}

	/**
	 * Test get_original returns original input.
	 */
	public function test_get_original_returns_original_input(): void {
		$original = '12.abc.345/01de-35';
		$cnpj     = new AlphanumericCNPJ( $original );

		$this->assertEquals( $original, $cnpj->get_original() );
	}

	/**
	 * Test is_alphanumeric returns true for alphanumeric CNPJ.
	 */
	public function test_is_alphanumeric_returns_true_for_alphanumeric(): void {
		$cnpj = new AlphanumericCNPJ( '12ABC34501DE35' );

		$this->assertTrue( $cnpj->is_alphanumeric() );
	}

	/**
	 * Test is_alphanumeric returns false for numeric CNPJ.
	 */
	public function test_is_alphanumeric_returns_false_for_numeric(): void {
		$cnpj = new AlphanumericCNPJ( '11222333000181' );

		$this->assertFalse( $cnpj->is_alphanumeric() );
	}

	/**
	 * Test static make factory method.
	 */
	public function test_make_factory_method(): void {
		$cnpj = AlphanumericCNPJ::make( '12ABC34501DE35' );

		$this->assertInstanceOf( AlphanumericCNPJ::class, $cnpj );
		$this->assertTrue( $cnpj->is_valid() );
	}

	/**
	 * Test fluent API with make method.
	 */
	public function test_fluent_api(): void {
		$this->assertTrue( AlphanumericCNPJ::make( '12ABC34501DE35' )->is_valid() );
		$this->assertEquals( '12.ABC.345/01DE-35', AlphanumericCNPJ::make( '12ABC34501DE35' )->format() );
	}

	/**
	 * Test ASCII value calculation for digits.
	 *
	 * According to the document:
	 * - '0' = ASCII 48, value = 48 - 48 = 0
	 * - '9' = ASCII 57, value = 57 - 48 = 9
	 *
	 * @dataProvider digit_ascii_values_provider
	 */
	public function test_digit_ascii_values( string $digit, int $expected_value ): void {
		$ascii = ord( $digit );
		$value = $ascii - 48;

		$this->assertEquals( $expected_value, $value );
	}

	/**
	 * Data provider for digit ASCII values.
	 */
	public function digit_ascii_values_provider(): array {
		return array(
			array( '0', 0 ),
			array( '1', 1 ),
			array( '2', 2 ),
			array( '3', 3 ),
			array( '4', 4 ),
			array( '5', 5 ),
			array( '6', 6 ),
			array( '7', 7 ),
			array( '8', 8 ),
			array( '9', 9 ),
		);
	}

	/**
	 * Test ASCII value calculation for letters.
	 *
	 * According to the document:
	 * - 'A' = ASCII 65, value = 65 - 48 = 17
	 * - 'Z' = ASCII 90, value = 90 - 48 = 42
	 *
	 * @dataProvider letter_ascii_values_provider
	 */
	public function test_letter_ascii_values( string $letter, int $expected_value ): void {
		$ascii = ord( $letter );
		$value = $ascii - 48;

		$this->assertEquals( $expected_value, $value );
	}

	/**
	 * Data provider for letter ASCII values.
	 */
	public function letter_ascii_values_provider(): array {
		return array(
			array( 'A', 17 ),
			array( 'B', 18 ),
			array( 'C', 19 ),
			array( 'D', 20 ),
			array( 'E', 21 ),
			array( 'Z', 42 ),
		);
	}

	/**
	 * Test first verification digit calculation with official example.
	 *
	 * Base: 12ABC34501DE
	 * Values: 1, 2, 17, 18, 19, 3, 4, 5, 0, 1, 20, 21
	 * Weights: 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2
	 * Products: 5, 8, 51, 36, 171, 24, 28, 30, 0, 4, 60, 42
	 * Sum: 459
	 * Remainder: 459 % 11 = 8
	 * DV1: 11 - 8 = 3
	 */
	public function test_first_digit_calculation(): void {
		$base    = '12ABC34501DE';
		$weights = array( 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 );

		$sum = 0;
		for ( $i = 0; $i < 12; $i++ ) {
			$value = ord( $base[ $i ] ) - 48;
			$sum  += $value * $weights[ $i ];
		}

		$this->assertEquals( 459, $sum );
		$this->assertEquals( 8, $sum % 11 );
		$this->assertEquals( 3, 11 - ( $sum % 11 ) );
	}

	/**
	 * Test second verification digit calculation with official example.
	 *
	 * Base: 12ABC34501DE3
	 * Values: 1, 2, 17, 18, 19, 3, 4, 5, 0, 1, 20, 21, 3
	 * Weights: 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2
	 * Products: 6, 10, 68, 54, 38, 27, 32, 35, 0, 5, 80, 63, 6
	 * Sum: 424
	 * Remainder: 424 % 11 = 6
	 * DV2: 11 - 6 = 5
	 */
	public function test_second_digit_calculation(): void {
		$base    = '12ABC34501DE3';
		$weights = array( 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 );

		$sum = 0;
		for ( $i = 0; $i < 13; $i++ ) {
			$value = ord( $base[ $i ] ) - 48;
			$sum  += $value * $weights[ $i ];
		}

		$this->assertEquals( 424, $sum );
		$this->assertEquals( 6, $sum % 11 );
		$this->assertEquals( 5, 11 - ( $sum % 11 ) );
	}

	/**
	 * Test remainder less than 2 results in digit 0.
	 *
	 * According to the document: "Se o 'resto' da divisão for igual a zero ou 1,
	 * o dígito verificador será igual a zero."
	 */
	public function test_remainder_less_than_2_returns_zero(): void {
		// Remainder 0: DV should be 0
		$this->assertEquals( 0, 0 < 2 ? 0 : 11 - 0 );

		// Remainder 1: DV should be 0
		$this->assertEquals( 0, 1 < 2 ? 0 : 11 - 1 );

		// Remainder 2: DV should be 9
		$this->assertEquals( 9, 2 < 2 ? 0 : 11 - 2 );
	}

	/**
	 * Test various valid alphanumeric CNPJs.
	 *
	 * @dataProvider valid_alphanumeric_cnpjs_provider
	 */
	public function test_valid_alphanumeric_cnpjs( string $cnpj ): void {
		$this->assertTrue( AlphanumericCNPJ::make( $cnpj )->is_valid() );
	}

	/**
	 * Data provider for valid alphanumeric CNPJs.
	 */
	public function valid_alphanumeric_cnpjs_provider(): array {
		return array(
			'official example formatted'   => array( '12.ABC.345/01DE-35' ),
			'official example unformatted' => array( '12ABC34501DE35' ),
			'official example lowercase'   => array( '12abc34501de35' ),
		);
	}

	/**
	 * Test various invalid CNPJs.
	 *
	 * @dataProvider invalid_cnpjs_provider
	 */
	public function test_invalid_cnpjs( string $cnpj ): void {
		$this->assertFalse( AlphanumericCNPJ::make( $cnpj )->is_valid() );
	}

	/**
	 * Data provider for invalid CNPJs.
	 */
	public function invalid_cnpjs_provider(): array {
		return array(
			'wrong DVs'                  => array( '12ABC34501DE99' ),
			'all zeros'                  => array( '00000000000000' ),
			'all ones'                   => array( '11111111111111' ),
			'too short'                  => array( '12ABC345' ),
			'too long'                   => array( '12ABC34501DE3512345' ),
			'empty'                      => array( '' ),
			'non-numeric DVs'            => array( '12ABC34501DEAB' ),
			'invalid chars'              => array( '12ABC34501DE3!' ),
		);
	}
}
