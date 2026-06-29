<?php
/**
 * Tests for CPF/CNPJ parsing helpers, including the new alphanumeric CNPJ format.
 *
 * @package PagBank_WooCommerce\Tests\Presentation
 */

namespace PagBank_WooCommerce\Tests\Presentation;

use PagBank_WooCommerce\Presentation\Helpers;
use PHPUnit\Framework\TestCase;

/**
 * Class HelpersTest.
 */
class HelpersTest extends TestCase {

	/**
	 * With the alphanumeric flag OFF (default), an alphanumeric CNPJ must be
	 * rejected because letters are stripped and the value no longer validates.
	 */
	public function test_parse_rejects_alphanumeric_cnpj_when_flag_disabled(): void {
		$parsed = Helpers::parse_cpf_or_cnpj( '12.ABC.345/01DE-35' );

		$this->assertFalse( $parsed['is_valid'] );
		$this->assertSame( 'unknown', $parsed['type'] );
		$this->assertNull( $parsed['value'] );
	}

	/**
	 * A traditional numeric CNPJ keeps working regardless of the flag.
	 */
	public function test_parse_accepts_numeric_cnpj_when_flag_disabled(): void {
		$parsed = Helpers::parse_cpf_or_cnpj( '11.222.333/0001-81' );

		$this->assertTrue( $parsed['is_valid'] );
		$this->assertSame( 'cnpj', $parsed['type'] );
		$this->assertSame( '11222333000181', $parsed['value'] );
	}

	/**
	 * With the flag OFF, sanitize_cnpj keeps the legacy digits-only behavior.
	 */
	public function test_sanitize_cnpj_strips_letters_when_flag_disabled(): void {
		$this->assertSame( '123450135', Helpers::sanitize_cnpj( '12.ABC.345/01DE-35' ) );
	}

	/**
	 * With the alphanumeric flag ON, the whole CPF/CNPJ pipeline must preserve
	 * letters and validate/format the new alphanumeric CNPJ end-to-end.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_alphanumeric_cnpj_is_supported_when_flag_enabled(): void {
		define( 'PAGBANK_FEATURE_FLAG_ALPHANUMERIC_CNPJ_ENABLED', true );

		// Sanitization preserves letters and uppercases them.
		$this->assertSame( '12ABC34501DE35', Helpers::sanitize_cnpj( '12.abc.345/01de-35' ) );

		// Parsing recognizes the alphanumeric CNPJ and returns the raw value.
		$parsed = Helpers::parse_cpf_or_cnpj( '12.ABC.345/01DE-35' );
		$this->assertTrue( $parsed['is_valid'] );
		$this->assertSame( 'cnpj', $parsed['type'] );
		$this->assertSame( '12ABC34501DE35', $parsed['value'] );

		// Formatting applies the standard CNPJ mask to the alphanumeric value.
		$this->assertSame( '12.ABC.345/01DE-35', Helpers::format_cnpj( '12ABC34501DE35' ) );

		// Traditional numeric CNPJ and CPF remain valid with the flag enabled.
		$numeric = Helpers::parse_cpf_or_cnpj( '11.222.333/0001-81' );
		$this->assertTrue( $numeric['is_valid'] );
		$this->assertSame( 'cnpj', $numeric['type'] );
		$this->assertSame( '11222333000181', $numeric['value'] );

		$cpf = Helpers::parse_cpf_or_cnpj( '111.444.777-35' );
		$this->assertTrue( $cpf['is_valid'] );
		$this->assertSame( 'cpf', $cpf['type'] );
		$this->assertSame( '11144477735', $cpf['value'] );
	}
}
