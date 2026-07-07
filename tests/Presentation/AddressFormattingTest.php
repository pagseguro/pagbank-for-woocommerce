<?php
/**
 * Tests for the Brazilian address formatting helpers.
 *
 * @package PagBank_WooCommerce\Tests\Presentation
 */

namespace PagBank_WooCommerce\Tests\Presentation;

use PagBank_WooCommerce\Presentation\AddressFormatting;
use PHPUnit\Framework\TestCase;

/**
 * Class AddressFormattingTest.
 */
class AddressFormattingTest extends TestCase {

	/**
	 * The custom placeholders must be removed from the format used by the
	 * Blocks checkout address card, without leaving stray separators.
	 */
	public function test_strip_custom_address_placeholders(): void {
		$this->assertSame(
			"{name}\n{address_1}\n{address_2}\n{city}\n{state}\n{postcode}\n{country}",
			AddressFormatting::strip_custom_address_placeholders(
				"{name}\n{address_1}, {number}\n{address_2}\n{neighborhood}\n{city}\n{state}\n{postcode}\n{country}\n{cellphone}"
			)
		);
	}

	/**
	 * Formats without the custom placeholders pass through untouched.
	 */
	public function test_strip_custom_address_placeholders_noop(): void {
		$format = "{name}\n{address_1}\n{address_2}\n{city}\n{state}\n{postcode}\n{country}";

		$this->assertSame( $format, AddressFormatting::strip_custom_address_placeholders( $format ) );
	}

	/**
	 * Placeholder variants with different separators are also removed.
	 */
	public function test_strip_custom_address_placeholders_variants(): void {
		$this->assertSame(
			"{address_1}\n{city} {state}",
			AddressFormatting::strip_custom_address_placeholders( "{address_1} {number}\n{neighborhood}\n{city} {neighborhood} {state}" )
		);
	}
}
