<?php
/**
 * Tests for pure helpers in ApiHelpers (name sanitizer + idempotency keys).
 *
 * @package PagBank_WooCommerce\Tests\Presentation
 */

namespace PagBank_WooCommerce\Tests\Presentation;

use PagBank_WooCommerce\Presentation\ApiHelpers;
use PHPUnit\Framework\TestCase;

/**
 * Class ApiHelpersTest.
 */
class ApiHelpersTest extends TestCase {

	/**
	 * The sanitizer must replace any non-letter, non-digit, non-whitespace
	 * character with a space, collapse repeats and trim the result.
	 *
	 * @dataProvider sanitize_name_provider
	 */
	public function test_sanitize_pagbank_name_strips_rejected_characters( string $input, string $expected ): void {
		$this->assertSame( $expected, ApiHelpers::sanitize_pagbank_name( $input ) );
	}

	public function sanitize_name_provider(): array {
		return array(
			'plain name unchanged'             => array( 'Maria Silva', 'Maria Silva' ),
			'accents preserved'                => array( 'João Pereira de Açaí', 'João Pereira de Açaí' ),
			'cedilla and tilde preserved'      => array( 'Conceição Cunha', 'Conceição Cunha' ),
			'parens removed'                   => array( 'João (Tete) Silva', 'João Tete Silva' ),
			'quotes removed'                   => array( '"Maria" Silva', 'Maria Silva' ),
			'hash removed'                     => array( 'Bob #2', 'Bob 2' ),
			'apostrophe removed'               => array( "D'Ávila", 'D Ávila' ),
			'hyphen removed'                   => array( 'Silva-Neto', 'Silva Neto' ),
			'semicolons and angle brackets'    => array( 'Bob; <DROP>', 'Bob DROP' ),
			'pipes and braces'                 => array( 'Empresa | {teste}', 'Empresa teste' ),
			'square brackets'                  => array( 'Maria & Cia [Ltda]', 'Maria Cia Ltda' ),
			'collapses repeated whitespace'    => array( 'Ana    Silva', 'Ana Silva' ),
			'trims leading and trailing space' => array( '  Carlos  ', 'Carlos' ),
			'only forbidden chars'             => array( '!@#$%', '' ),
			'empty string'                     => array( '', '' ),
			'digits preserved'                 => array( 'Empresa 2024', 'Empresa 2024' ),
		);
	}

	/**
	 * Two requests with the same payment intent must produce the same key
	 * so the PagBank API can dedupe them server-side.
	 */
	public function test_get_create_order_idempotency_key_is_stable_for_same_intent(): void {
		$data = $this->sample_order_payload();

		$first  = ApiHelpers::get_create_order_idempotency_key( $data );
		$second = ApiHelpers::get_create_order_idempotency_key( $data );

		$this->assertSame( $first, $second );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $first );
	}

	/**
	 * Changing the customer must yield a different key.
	 */
	public function test_get_create_order_idempotency_key_changes_with_customer(): void {
		$data           = $this->sample_order_payload();
		$other_customer = $data;

		$other_customer['customer']['email'] = 'other@example.com';

		$this->assertNotSame(
			ApiHelpers::get_create_order_idempotency_key( $data ),
			ApiHelpers::get_create_order_idempotency_key( $other_customer )
		);
	}

	/**
	 * Changing the cart items must yield a different key.
	 */
	public function test_get_create_order_idempotency_key_changes_with_items(): void {
		$data        = $this->sample_order_payload();
		$other_items = $data;

		$other_items['items'][0]['quantity'] = 5;

		$this->assertNotSame(
			ApiHelpers::get_create_order_idempotency_key( $data ),
			ApiHelpers::get_create_order_idempotency_key( $other_items )
		);
	}

	/**
	 * Changing the amount (e.g., different installments plan with interest)
	 * must yield a different key.
	 */
	public function test_get_create_order_idempotency_key_changes_with_amount(): void {
		$data         = $this->sample_order_payload();
		$other_amount = $data;

		$other_amount['charges'][0]['amount']['value'] = 12000;

		$this->assertNotSame(
			ApiHelpers::get_create_order_idempotency_key( $data ),
			ApiHelpers::get_create_order_idempotency_key( $other_amount )
		);
	}

	/**
	 * Changing the payment method (e.g., card → boleto) must yield a different key.
	 */
	public function test_get_create_order_idempotency_key_changes_with_payment_method(): void {
		$data         = $this->sample_order_payload();
		$other_method = $data;

		$other_method['charges'][0]['payment_method']['type'] = 'BOLETO';

		$this->assertNotSame(
			ApiHelpers::get_create_order_idempotency_key( $data ),
			ApiHelpers::get_create_order_idempotency_key( $other_method )
		);
	}

	/**
	 * Missing fields must not crash; the function should still produce a deterministic key.
	 */
	public function test_get_create_order_idempotency_key_handles_missing_fields(): void {
		$key = ApiHelpers::get_create_order_idempotency_key( array() );

		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $key );
	}

	/**
	 * Same charge + same amount = same refund key.
	 */
	public function test_get_refund_idempotency_key_is_stable(): void {
		$first  = ApiHelpers::get_refund_idempotency_key( 'CHAR_ABC', 25.50 );
		$second = ApiHelpers::get_refund_idempotency_key( 'CHAR_ABC', 25.50 );

		$this->assertSame( $first, $second );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $first );
	}

	/**
	 * Different charge IDs must produce different keys, so unrelated refunds
	 * do not collide in PagBank's dedup window.
	 */
	public function test_get_refund_idempotency_key_changes_with_charge_id(): void {
		$this->assertNotSame(
			ApiHelpers::get_refund_idempotency_key( 'CHAR_ABC', 25.50 ),
			ApiHelpers::get_refund_idempotency_key( 'CHAR_XYZ', 25.50 )
		);
	}

	/**
	 * A partial refund of a different amount must produce a different key,
	 * so the merchant can issue multiple partial refunds on the same charge.
	 */
	public function test_get_refund_idempotency_key_changes_with_amount(): void {
		$this->assertNotSame(
			ApiHelpers::get_refund_idempotency_key( 'CHAR_ABC', 25.50 ),
			ApiHelpers::get_refund_idempotency_key( 'CHAR_ABC', 30.00 )
		);
	}

	/**
	 * Build a representative create-order payload.
	 */
	private function sample_order_payload(): array {
		return array(
			'customer' => array(
				'email'  => 'maria@example.com',
				'tax_id' => array( 'value' => '12345678901' ),
			),
			'charges'  => array(
				array(
					'amount'         => array( 'value' => 10000 ),
					'payment_method' => array( 'type' => 'CREDIT_CARD' ),
				),
			),
			'items'    => array(
				array(
					'name'        => 'Pizza',
					'unit_amount' => 5000,
					'quantity'    => 2,
				),
			),
		);
	}
}
