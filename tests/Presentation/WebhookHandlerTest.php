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
	 * The parser accepts both the new format (plain order_id) and the legacy
	 * JSON-wrapped format used before the API-verification refactor.
	 *
	 * @dataProvider reference_id_provider
	 *
	 * @param string|null $raw      Raw reference_id as sent by the webhook payload.
	 * @param int|null    $expected Expected parsed order id (null = should not parse).
	 */
	public function test_parse_reference_id( ?string $raw, ?int $expected ): void {
		$this->assertSame( $expected, WebhookHandler::parse_reference_id( $raw ) );
	}

	public function reference_id_provider(): array {
		return array(
			'plain numeric string (new format)'      => array( '123', 123 ),
			'legacy json with id and password'       => array( '{"id":"456","password":"secret"}', 456 ),
			'legacy json with integer id'            => array( '{"id":789}', 789 ),
			'legacy json with id only no password'   => array( '{"id":"42"}', 42 ),
			'json without id key'                    => array( '{"foo":"bar"}', null ),
			'empty json object'                      => array( '{}', null ),
			'empty string'                           => array( '', null ),
			'null'                                   => array( null, null ),
			'non-numeric string'                     => array( 'abc', null ),
			'zero'                                   => array( '0', null ),
			'negative number'                        => array( '-5', null ),
			'numeric with leading zeros'             => array( '007', 7 ),
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
