<?php
/**
 * Tests for the pure parts of WebhookHandler.
 *
 * @package PagBank_WooCommerce\Tests\Presentation
 */

namespace PagBank_WooCommerce\Tests\Presentation;

use PagBank_WooCommerce\Presentation\WebhookHandler;
use PHPUnit\Framework\TestCase;

/**
 * Class WebhookHandlerTest.
 */
class WebhookHandlerTest extends TestCase {

	/**
	 * The parser returns a discriminated structure: legacy JSON payloads carry
	 * id+password, the new `{order_id}:{hash}` form returns the order id with
	 * the 32-char hex signature, and anything else is rejected.
	 *
	 * @dataProvider reference_id_provider
	 *
	 * @param string|null $raw      Raw reference_id as sent by the webhook payload.
	 * @param array|null  $expected Expected parse result (null = should not parse).
	 */
	public function test_parse_reference_id( ?string $raw, ?array $expected ): void {
		$this->assertSame( $expected, WebhookHandler::parse_reference_id( $raw ) );
	}

	public function reference_id_provider(): array {
		$hash = str_repeat( 'a1b2c3d4', 4 );

		return array(
			'legacy json with id and password'    => array(
				'{"id":"456","password":"secret"}',
				array(
					'kind'     => 'json',
					'order_id' => 456,
					'password' => 'secret',
				),
			),
			'legacy json with integer id'         => array(
				'{"id":789,"password":"x"}',
				array(
					'kind'     => 'json',
					'order_id' => 789,
					'password' => 'x',
				),
			),
			'legacy json without password'        => array( '{"id":"42"}', null ),
			'legacy json with empty password'     => array( '{"id":"42","password":""}', null ),
			'json without id key'                 => array( '{"password":"x"}', null ),
			'json with zero id'                   => array( '{"id":0,"password":"x"}', null ),
			'json with negative id'               => array( '{"id":-5,"password":"x"}', null ),
			'empty json object'                   => array( '{}', null ),
			'signed id:hash'                      => array(
				"123:{$hash}",
				array(
					'kind'     => 'signed',
					'order_id' => 123,
					'hash'     => $hash,
				),
			),
			'signed hash too short'               => array( '123:' . str_repeat( 'a', 16 ), null ),
			'signed hash too long'                => array( '123:' . str_repeat( 'a', 33 ), null ),
			'signed uppercase hex rejected'       => array( '123:' . strtoupper( $hash ), null ),
			'signed with zero id'                 => array( "0:{$hash}", null ),
			'signed missing separator'            => array( '123' . $hash, null ),
			'signed bare hash without id'         => array( $hash, null ),
			'plain numeric string rejected'       => array( '123', null ),
			'non-numeric non-hex string rejected' => array( 'abc', null ),
			'empty string'                        => array( '', null ),
			'null'                                => array( null, null ),
		);
	}

	/**
	 * The status mapper covers every status the plugin reacts to. Unknown
	 * statuses fall through to null so the handler can no-op safely.
	 *
	 * @dataProvider charge_status_provider
	 *
	 * @param string      $charge_status The status string from the PagBank charge payload.
	 * @param string|null $expected      Expected action keyword.
	 */
	public function test_map_charge_status( string $charge_status, ?string $expected ): void {
		$this->assertSame( $expected, WebhookHandler::map_charge_status( $charge_status ) );
	}

	public function charge_status_provider(): array {
		return array(
			'IN_ANALYSIS maps to on-hold' => array( 'IN_ANALYSIS', 'on-hold' ),
			'WAITING maps to on-hold'     => array( 'WAITING', 'on-hold' ),
			'DECLINED maps to failed'     => array( 'DECLINED', 'failed' ),
			'PAID maps to completed'      => array( 'PAID', 'completed' ),
			'CANCELED maps to cancelled'  => array( 'CANCELED', 'cancelled' ),
			'unknown status is ignored'   => array( 'AUTHORIZED', null ),
			'empty string is ignored'     => array( '', null ),
		);
	}
}
