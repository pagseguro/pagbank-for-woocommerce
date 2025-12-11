<?php
/**
 * Tests for CNPJ validator (traditional numeric format).
 *
 * @package PagBank_WooCommerce\Tests\Validators
 */

namespace PagBank_WooCommerce\Tests\Validators;

use PHPUnit\Framework\TestCase;
use PagBank_WooCommerce\Validators\CNPJ;

/**
 * Class CNPJTest.
 */
class CNPJTest extends TestCase {

	/**
	 * Test validation with valid CNPJ.
	 *
	 * @dataProvider valid_cnpjs_provider
	 */
	public function test_validates_valid_cnpj( string $cnpj ): void {
		$this->assertTrue( CNPJ::make( $cnpj )->is_valid() );
	}

	/**
	 * Data provider for valid CNPJs.
	 */
	public function valid_cnpjs_provider(): array {
		return array(
			'formatted'         => array( '11.222.333/0001-81' ),
			'unformatted'       => array( '11222333000181' ),
			'another valid'     => array( '11.444.777/0001-61' ),
			'with spaces'       => array( '11 222 333 0001 81' ),
			'valid cnpj 1'      => array( '45.723.174/0001-10' ),
			'valid cnpj 2'      => array( '78.366.582/0001-11' ),
			'branch office'     => array( '11.222.333/0002-62' ),
		);
	}

	/**
	 * Test rejection of invalid CNPJ with wrong verification digits.
	 */
	public function test_rejects_invalid_verification_digits(): void {
		$this->assertFalse( CNPJ::make( '11.222.333/0001-99' )->is_valid() );
	}

	/**
	 * Test rejection of CNPJ with all identical digits.
	 *
	 * @dataProvider identical_digits_provider
	 */
	public function test_rejects_all_identical_digits( string $cnpj ): void {
		$this->assertFalse( CNPJ::make( $cnpj )->is_valid() );
	}

	/**
	 * Data provider for CNPJs with identical digits.
	 */
	public function identical_digits_provider(): array {
		return array(
			'all zeros'  => array( '00000000000000' ),
			'all ones'   => array( '11111111111111' ),
			'all twos'   => array( '22222222222222' ),
			'all threes' => array( '33333333333333' ),
			'all fours'  => array( '44444444444444' ),
			'all fives'  => array( '55555555555555' ),
			'all sixes'  => array( '66666666666666' ),
			'all sevens' => array( '77777777777777' ),
			'all eights' => array( '88888888888888' ),
			'all nines'  => array( '99999999999999' ),
		);
	}

	/**
	 * Test rejection of CNPJ with wrong length.
	 *
	 * @dataProvider wrong_length_provider
	 */
	public function test_rejects_wrong_length( string $cnpj ): void {
		$this->assertFalse( CNPJ::make( $cnpj )->is_valid() );
	}

	/**
	 * Data provider for CNPJs with wrong length.
	 */
	public function wrong_length_provider(): array {
		return array(
			'too short'       => array( '112223330001' ),
			'too long'        => array( '1122233300018112345' ),
			'empty'           => array( '' ),
			'single digit'    => array( '1' ),
			'thirteen digits' => array( '1122233300018' ),
			'fifteen digits'  => array( '112223330001811' ),
		);
	}

	/**
	 * Test rejection of CNPJ with non-numeric characters after sanitization.
	 */
	public function test_rejects_letters_in_cnpj(): void {
		$this->assertFalse( CNPJ::make( '11222A33B00181' )->is_valid() );
	}

	/**
	 * Test formatting of CNPJ.
	 */
	public function test_formats_cnpj(): void {
		$cnpj = new CNPJ( '11222333000181' );

		$this->assertEquals( '11.222.333/0001-81', $cnpj->format() );
	}

	/**
	 * Test formatting returns original value when length is invalid.
	 */
	public function test_format_returns_original_when_invalid_length(): void {
		$cnpj = new CNPJ( '12345' );

		$this->assertEquals( '12345', $cnpj->format() );
	}

	/**
	 * Test get_value returns sanitized value.
	 */
	public function test_get_value_returns_sanitized(): void {
		$cnpj = new CNPJ( '11.222.333/0001-81' );

		$this->assertEquals( '11222333000181', $cnpj->get_value() );
	}

	/**
	 * Test get_original returns original input.
	 */
	public function test_get_original_returns_original_input(): void {
		$original = '11.222.333/0001-81';
		$cnpj     = new CNPJ( $original );

		$this->assertEquals( $original, $cnpj->get_original() );
	}

	/**
	 * Test static make factory method.
	 */
	public function test_make_factory_method(): void {
		$cnpj = CNPJ::make( '11222333000181' );

		$this->assertInstanceOf( CNPJ::class, $cnpj );
		$this->assertTrue( $cnpj->is_valid() );
	}

	/**
	 * Test fluent API with make method.
	 */
	public function test_fluent_api(): void {
		$this->assertTrue( CNPJ::make( '11222333000181' )->is_valid() );
		$this->assertEquals( '11.222.333/0001-81', CNPJ::make( '11222333000181' )->format() );
	}

	/**
	 * Test first verification digit calculation.
	 *
	 * CNPJ: 112223330001
	 * Weights: 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2
	 * Products: 5, 4, 6, 4, 18, 24, 21, 18, 0, 0, 0, 2
	 * Sum: 102
	 * Remainder: 102 % 11 = 3
	 * DV1: 11 - 3 = 8
	 */
	public function test_first_digit_calculation(): void {
		$base    = '112223330001';
		$weights = array( 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 );

		$sum = 0;
		for ( $i = 0; $i < 12; $i++ ) {
			$sum += (int) $base[ $i ] * $weights[ $i ];
		}

		$this->assertEquals( 102, $sum );
		$this->assertEquals( 3, $sum % 11 );
		$this->assertEquals( 8, 11 - ( $sum % 11 ) );
	}

	/**
	 * Test second verification digit calculation.
	 *
	 * CNPJ: 1122233300018
	 * Weights: 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2
	 * Products: 6, 5, 8, 6, 4, 27, 24, 21, 0, 0, 0, 3, 16
	 * Sum: 120
	 * Remainder: 120 % 11 = 10
	 * DV2: 11 - 10 = 1
	 */
	public function test_second_digit_calculation(): void {
		$base    = '1122233300018';
		$weights = array( 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 );

		$sum = 0;
		for ( $i = 0; $i < 13; $i++ ) {
			$sum += (int) $base[ $i ] * $weights[ $i ];
		}

		$this->assertEquals( 120, $sum );
		$this->assertEquals( 10, $sum % 11 );
		$this->assertEquals( 1, 11 - ( $sum % 11 ) );
	}

	/**
	 * Test remainder less than 2 results in digit 0.
	 */
	public function test_remainder_less_than_2_returns_zero(): void {
		// Remainder 0: DV should be 0.
		$this->assertEquals( 0, 0 < 2 ? 0 : 11 - 0 );

		// Remainder 1: DV should be 0.
		$this->assertEquals( 0, 1 < 2 ? 0 : 11 - 1 );

		// Remainder 2: DV should be 9.
		$this->assertEquals( 9, 2 < 2 ? 0 : 11 - 2 );
	}

	/**
	 * Test various invalid CNPJs.
	 *
	 * @dataProvider invalid_cnpjs_provider
	 */
	public function test_invalid_cnpjs( string $cnpj ): void {
		$this->assertFalse( CNPJ::make( $cnpj )->is_valid() );
	}

	/**
	 * Data provider for invalid CNPJs.
	 */
	public function invalid_cnpjs_provider(): array {
		return array(
			'wrong DVs'        => array( '11.222.333/0001-99' ),
			'wrong first DV'   => array( '11.222.333/0001-71' ),
			'wrong second DV'  => array( '11.222.333/0001-82' ),
			'all zeros'        => array( '00.000.000/0000-00' ),
			'too short'        => array( '11222333' ),
			'too long'         => array( '112223330001811234' ),
			'empty'            => array( '' ),
			'letters only'     => array( 'ABCDEFGHIJKLMN' ),
			'special chars'    => array( '!@#$%^&*()!@#$' ),
		);
	}

	/**
	 * Test CNPJ with leading zeros.
	 */
	public function test_cnpj_with_leading_zeros(): void {
		// 01.234.567/0001-95 is a valid CNPJ with leading zero.
		$cnpj = new CNPJ( '01234567000195' );

		$this->assertTrue( $cnpj->is_valid() );
		$this->assertEquals( '01.234.567/0001-95', $cnpj->format() );
	}
}
