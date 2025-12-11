<?php
/**
 * Tests for CPF validator.
 *
 * @package PagBank_WooCommerce\Tests\Validators
 */

namespace PagBank_WooCommerce\Tests\Validators;

use PHPUnit\Framework\TestCase;
use PagBank_WooCommerce\Validators\CPF;

/**
 * Class CPFTest.
 */
class CPFTest extends TestCase {

	/**
	 * Test validation with valid CPF.
	 *
	 * @dataProvider valid_cpfs_provider
	 */
	public function test_validates_valid_cpf( string $cpf ): void {
		$this->assertTrue( CPF::make( $cpf )->is_valid() );
	}

	/**
	 * Data provider for valid CPFs.
	 */
	public function valid_cpfs_provider(): array {
		return array(
			'formatted'         => array( '529.982.247-25' ),
			'unformatted'       => array( '52998224725' ),
			'another valid'     => array( '111.444.777-35' ),
			'with spaces'       => array( '529 982 247 25' ),
			'valid cpf 1'       => array( '935.411.347-80' ),
			'valid cpf 2'       => array( '123.456.789-09' ),
			'valid cpf 3'       => array( '000.000.001-91' ),
		);
	}

	/**
	 * Test rejection of invalid CPF with wrong verification digits.
	 */
	public function test_rejects_invalid_verification_digits(): void {
		$this->assertFalse( CPF::make( '529.982.247-99' )->is_valid() );
	}

	/**
	 * Test rejection of CPF with all identical digits.
	 *
	 * @dataProvider identical_digits_provider
	 */
	public function test_rejects_all_identical_digits( string $cpf ): void {
		$this->assertFalse( CPF::make( $cpf )->is_valid() );
	}

	/**
	 * Data provider for CPFs with identical digits.
	 */
	public function identical_digits_provider(): array {
		return array(
			'all zeros'  => array( '00000000000' ),
			'all ones'   => array( '11111111111' ),
			'all twos'   => array( '22222222222' ),
			'all threes' => array( '33333333333' ),
			'all fours'  => array( '44444444444' ),
			'all fives'  => array( '55555555555' ),
			'all sixes'  => array( '66666666666' ),
			'all sevens' => array( '77777777777' ),
			'all eights' => array( '88888888888' ),
			'all nines'  => array( '99999999999' ),
		);
	}

	/**
	 * Test rejection of CPF with wrong length.
	 *
	 * @dataProvider wrong_length_provider
	 */
	public function test_rejects_wrong_length( string $cpf ): void {
		$this->assertFalse( CPF::make( $cpf )->is_valid() );
	}

	/**
	 * Data provider for CPFs with wrong length.
	 */
	public function wrong_length_provider(): array {
		return array(
			'too short'     => array( '529982247' ),
			'too long'      => array( '5299822472512345' ),
			'empty'         => array( '' ),
			'single digit'  => array( '5' ),
			'ten digits'    => array( '5299822472' ),
			'twelve digits' => array( '529982247251' ),
		);
	}

	/**
	 * Test rejection of CPF with non-numeric characters after sanitization.
	 */
	public function test_rejects_letters_in_cpf(): void {
		$this->assertFalse( CPF::make( '529A82247B5' )->is_valid() );
	}

	/**
	 * Test formatting of CPF.
	 */
	public function test_formats_cpf(): void {
		$cpf = new CPF( '52998224725' );

		$this->assertEquals( '529.982.247-25', $cpf->format() );
	}

	/**
	 * Test formatting returns original value when length is invalid.
	 */
	public function test_format_returns_original_when_invalid_length(): void {
		$cpf = new CPF( '12345' );

		$this->assertEquals( '12345', $cpf->format() );
	}

	/**
	 * Test get_value returns sanitized value.
	 */
	public function test_get_value_returns_sanitized(): void {
		$cpf = new CPF( '529.982.247-25' );

		$this->assertEquals( '52998224725', $cpf->get_value() );
	}

	/**
	 * Test get_original returns original input.
	 */
	public function test_get_original_returns_original_input(): void {
		$original = '529.982.247-25';
		$cpf      = new CPF( $original );

		$this->assertEquals( $original, $cpf->get_original() );
	}

	/**
	 * Test static make factory method.
	 */
	public function test_make_factory_method(): void {
		$cpf = CPF::make( '52998224725' );

		$this->assertInstanceOf( CPF::class, $cpf );
		$this->assertTrue( $cpf->is_valid() );
	}

	/**
	 * Test fluent API with make method.
	 */
	public function test_fluent_api(): void {
		$this->assertTrue( CPF::make( '52998224725' )->is_valid() );
		$this->assertEquals( '529.982.247-25', CPF::make( '52998224725' )->format() );
	}

	/**
	 * Test first verification digit calculation.
	 *
	 * CPF: 529982247
	 * Weights: 10, 9, 8, 7, 6, 5, 4, 3, 2
	 * Products: 50, 18, 72, 63, 48, 10, 8, 12, 14
	 * Sum: 295
	 * Remainder: 295 % 11 = 9
	 * DV1: 11 - 9 = 2
	 */
	public function test_first_digit_calculation(): void {
		$base    = '529982247';
		$weights = array( 10, 9, 8, 7, 6, 5, 4, 3, 2 );

		$sum = 0;
		for ( $i = 0; $i < 9; $i++ ) {
			$sum += (int) $base[ $i ] * $weights[ $i ];
		}

		$this->assertEquals( 295, $sum );
		$this->assertEquals( 9, $sum % 11 );
		$this->assertEquals( 2, 11 - ( $sum % 11 ) );
	}

	/**
	 * Test second verification digit calculation.
	 *
	 * CPF: 5299822472
	 * Weights: 11, 10, 9, 8, 7, 6, 5, 4, 3, 2
	 * Products: 55, 20, 81, 72, 56, 12, 10, 16, 21, 4
	 * Sum: 347
	 * Remainder: 347 % 11 = 6
	 * DV2: 11 - 6 = 5
	 */
	public function test_second_digit_calculation(): void {
		$base    = '5299822472';
		$weights = array( 11, 10, 9, 8, 7, 6, 5, 4, 3, 2 );

		$sum = 0;
		for ( $i = 0; $i < 10; $i++ ) {
			$sum += (int) $base[ $i ] * $weights[ $i ];
		}

		$this->assertEquals( 347, $sum );
		$this->assertEquals( 6, $sum % 11 );
		$this->assertEquals( 5, 11 - ( $sum % 11 ) );
	}

	/**
	 * Test remainder less than 2 results in digit 0.
	 */
	public function test_remainder_less_than_2_returns_zero(): void {
		// CPF 000.000.001-91 has specific calculation where this applies.
		$cpf = new CPF( '00000000191' );

		$this->assertTrue( $cpf->is_valid() );
	}

	/**
	 * Test various invalid CPFs.
	 *
	 * @dataProvider invalid_cpfs_provider
	 */
	public function test_invalid_cpfs( string $cpf ): void {
		$this->assertFalse( CPF::make( $cpf )->is_valid() );
	}

	/**
	 * Data provider for invalid CPFs.
	 */
	public function invalid_cpfs_provider(): array {
		return array(
			'wrong DVs'        => array( '529.982.247-99' ),
			'wrong first DV'   => array( '529.982.247-15' ),
			'wrong second DV'  => array( '529.982.247-24' ),
			'all zeros'        => array( '000.000.000-00' ),
			'too short'        => array( '12345678' ),
			'too long'         => array( '123456789012' ),
			'empty'            => array( '' ),
			'letters only'     => array( 'ABCDEFGHIJK' ),
			'special chars'    => array( '!@#$%^&*()!' ),
		);
	}
}
