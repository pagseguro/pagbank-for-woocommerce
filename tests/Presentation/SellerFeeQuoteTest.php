<?php
/**
 * Seller fee quotation tests without a live WordPress installation or API.
 *
 * @package PagBank_WooCommerce\Tests\Presentation
 */

namespace {
	if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
		define( 'MINUTE_IN_SECONDS', 60 );
	}

	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			private string $code;

			public function __construct( string $code, string $message = '', $data = null ) {
				$this->code = $code;
			}

			public function get_error_code(): string {
				return $this->code;
			}
		}
	}

	if ( ! class_exists( 'WC_Order' ) ) {
		class WC_Order {}
	}
}

namespace PagBank_WooCommerce\Presentation {
	use PagBank_WooCommerce\Tests\Presentation\SellerFeeQuoteTest;

	function add_query_arg( array $query, string $url ): string {
		return $url . '?' . http_build_query( $query );
	}

	function wp_parse_args( array $args, array $defaults ): array {
		return array_merge( $defaults, $args );
	}

	function wp_remote_request( string $url, array $args ): array {
		SellerFeeQuoteTest::$requests[] = array( 'url' => $url, 'args' => $args );
		return array_shift( SellerFeeQuoteTest::$responses );
	}

	function wp_remote_retrieve_response_code( array $response ): int {
		return $response['response']['code'];
	}

	function wp_remote_retrieve_body( array $response ): string {
		return $response['body'];
	}

	function get_transient( string $key ) {
		return SellerFeeQuoteTest::$cache[ $key ] ?? false;
	}

	function set_transient( string $key, string $value, int $expiration ): void {
		SellerFeeQuoteTest::$cache[ $key ] = $value;
	}

	function is_wp_error( $value ): bool {
		return $value instanceof \WP_Error;
	}

	function wc_get_logger() {
		return SellerFeeQuoteTest::$logger;
	}
}

namespace PagBank_WooCommerce\Tests\Presentation {
	use PagBank_WooCommerce\Presentation\Api;
	use PagBank_WooCommerce\Presentation\Connect;
	use PagBank_WooCommerce\Presentation\WebhookHandler;
	use PHPUnit\Framework\TestCase;
	use ReflectionClass;
	use ReflectionMethod;
	use ReflectionProperty;
	use WC_Order;
	use WP_Error;

	/**
	 * Tests API parameters, quote selection and order metadata.
	 */
	class SellerFeeQuoteTest extends TestCase {
		public static array $requests = array();
		public static array $responses = array();
		public static array $cache = array();
		public static $logger;
		private Api $api;

		protected function setUp(): void {
			self::$requests  = array();
			self::$responses = array();
			self::$cache     = array();
			self::$logger    = new class() {
				public array $warnings = array();

				public function warning( string $message, array $context ): void {
					$this->warnings[] = $context;
				}
			};

			$connect = $this->createMock( Connect::class );
			$connect->method( 'get_access_token' )->willReturn( 'test-token' );
			$this->api = ( new ReflectionClass( Api::class ) )->newInstanceWithoutConstructor();
			foreach ( array( 'connect' => $connect, 'is_sandbox' => true, 'log_id' => null ) as $name => $value ) {
				$property = new ReflectionProperty( Api::class, $name );
				$property->setAccessible( true );
				$property->setValue( $this->api, $value );
			}
		}

		public function test_pix_quote_and_zero_fee_are_persisted_without_caching(): void {
			foreach ( array( 99, 0 ) as $fee ) {
				$this->respond( array( 'payment_methods' => array( 'pix' => array( 'amount' => $this->amount( 10000, $fee ) ) ) ) );
				$quote = $this->api->get_seller_fee_quote( $this->charge( 'PIX' ) );
				$this->assertSame( $fee, $quote['fee'] );
				$this->assertSame( 'BRL', $quote['currency'] );
				$this->assertSame( 'CHAR_TEST', $quote['charge_id'] );
				$this->assertSame( 10000, $quote['amount'] );

				$order = $this->order();
				$order->expects( $this->once() )->method( 'delete_meta_data' )->with( '_pagbank_seller_fee_quote' );
				$order->expects( $this->once() )->method( 'update_meta_data' )->with( '_pagbank_seller_fee_quote', $quote );
				$order->expects( $this->once() )->method( 'set_date_modified' )->with( $this->isType( 'integer' ) );
				$order->expects( $this->once() )->method( 'save' );
				$api = $this->createMock( Api::class );
				$api->method( 'get_seller_fee_quote' )->willReturn( $quote );
				$this->save_quote( $api, $order, $this->charge( 'PIX' ) );
			}

			$this->assertCount( 2, self::$requests );
			$this->assertSame( array(), self::$cache );
			$this->assertSame( 5, self::$requests[0]['args']['timeout'] );
			parse_str( parse_url( self::$requests[0]['url'], PHP_URL_QUERY ), $query );
			$this->assertSame( array( 'payment_methods' => 'PIX', 'value' => '10000', 'show_seller_fees' => 'true' ), $query );
		}

		public function test_credit_quote_selects_matching_brand_and_installments(): void {
			$charge = $this->charge( 'CREDIT_CARD' );
			$this->respond(
				array(
					'payment_methods' => array(
						'credit_card' => array(
							'visa'       => array( 'installment_plans' => array( array( 'installments' => 1, 'amount' => $this->amount( 10000, 999 ) ) ) ),
							'mastercard' => array(
								'installment_plans' => array(
									array( 'installments' => 2, 'amount' => $this->amount( 10000, 500 ) ),
									array( 'installments' => 1, 'amount' => $this->amount( 10000, 419 ) ),
								),
							),
						),
					),
				)
			);

			$quote = $this->api->get_seller_fee_quote( $charge );
			$this->assertSame( 419, $quote['fee'] );
			$this->assertSame( 1, $quote['installments'] );
			parse_str( parse_url( self::$requests[0]['url'], PHP_URL_QUERY ), $query );
			$this->assertSame( '552100', $query['credit_card_bin'] );
			$this->assertSame( '0', $query['max_installments_no_interest'] );
			$this->assertSame( '1', $query['max_installments'] );
		}

		public function test_buyer_interest_is_not_quoted_twice(): void {
			$charge = $this->charge( 'CREDIT_CARD' );
			$charge['payment_method']['installments'] = 8;
			$charge['amount']['value'] = 10887;
			$charge['amount']['fees']['buyer']['interest'] = array( 'total' => 887, 'installments' => 4 );
			$this->respond(
				array(
					'payment_methods' => array(
						'credit_card' => array(
							'mastercard' => array(
								'installment_plans' => array( array( 'installments' => 8, 'amount' => $this->amount( 10887, 600 ) ) ),
							),
						),
					),
				)
			);
			$quote = $this->api->get_seller_fee_quote( $charge );
			$this->assertSame( 600, $quote['fee'] );
			parse_str( parse_url( self::$requests[0]['url'], PHP_URL_QUERY ), $query );
			$this->assertSame( '10000', $query['value'] );
			$this->assertSame( '4', $query['max_installments_no_interest'] );
		}

		/**
		 * @dataProvider unavailable_quote_provider
		 */
		public function test_missing_or_mismatched_quotes_remain_unknown( array $amount ): void {
			$this->respond( array( 'payment_methods' => array( 'pix' => array( 'amount' => $amount ) ) ) );
			$this->assertInstanceOf( WP_Error::class, $this->api->get_seller_fee_quote( $this->charge( 'PIX' ) ) );
		}

		public function unavailable_quote_provider(): array {
			return array(
				'absent fee'     => array( array( 'value' => 10000, 'currency' => 'BRL' ) ),
				'negative fee'   => array( $this->amount( 10000, -1 ) ),
				'string fee'     => array( $this->amount( 10000, '99' ) ),
				'changed amount' => array( $this->amount( 9999, 99 ) ),
				'wrong currency' => array( array_merge( $this->amount( 10000, 99 ), array( 'currency' => 'USD' ) ) ),
			);
		}

		public function test_invalid_card_does_not_request_a_quote(): void {
			$card = $this->charge( 'CREDIT_CARD' );
			unset( $card['payment_method']['card']['first_digits'] );
			$this->assertInstanceOf( WP_Error::class, $this->api->get_seller_fee_quote( $card ) );
			$this->assertSame( array(), self::$requests );
		}

		public function test_empty_debit_response_remains_unknown(): void {
			$this->respond( array( 'payment_methods' => array() ) );
			$this->assertInstanceOf( WP_Error::class, $this->api->get_seller_fee_quote( $this->charge( 'DEBIT_CARD' ) ) );
			parse_str( parse_url( self::$requests[0]['url'], PHP_URL_QUERY ), $query );
			$this->assertSame( 'DEBIT_CARD', $query['payment_methods'] );
			$this->assertArrayNotHasKey( 'max_installments', $query );
		}

		public function test_boleto_quote_uses_the_documented_direct_amount_shape(): void {
			$charge = $this->charge( 'BOLETO' );
			$charge['amount']['value'] = 10100;
			$this->respond( array( 'payment_methods' => array( 'boleto' => array( 'amount' => $this->amount( 10100, 539 ) ) ) ) );
			$quote = $this->api->get_seller_fee_quote( $charge );
			$this->assertSame( 539, $quote['fee'] );
			$this->assertSame( 'BOLETO', $quote['payment_method'] );
			parse_str( parse_url( self::$requests[0]['url'], PHP_URL_QUERY ), $query );
			$this->assertSame( 'BOLETO', $query['payment_methods'] );
		}

		public function test_failed_lookup_clears_stale_metadata_and_logs_without_blocking_payment(): void {
			$this->respond( array( 'error_messages' => array() ), 400 );
			$error = $this->api->get_seller_fee_quote( $this->charge( 'PIX' ) );
			$this->assertInstanceOf( WP_Error::class, $error );
			$api = $this->createMock( Api::class );
			$api->method( 'get_seller_fee_quote' )->willReturn( $error );
			$order = $this->order();
			$order->method( 'get_meta' )->willReturn( array( 'charge_id' => 'CHAR_OLD', 'amount' => 10000, 'fee' => 99 ) );
			$order->expects( $this->once() )->method( 'delete_meta_data' )->with( '_pagbank_seller_fee_quote' );
			$order->expects( $this->never() )->method( 'update_meta_data' );
			$order->expects( $this->once() )->method( 'save' );

			$this->save_quote( $api, $order, $this->charge( 'PIX' ) );
			$this->assertSame( 'pagbank_charge_calculate_fees_failed', self::$logger->warnings[0]['error_code'] );
		}

		public function test_repeated_webhook_reuses_a_quote_for_the_same_charge_and_amount(): void {
			$api = $this->createMock( Api::class );
			$api->expects( $this->never() )->method( 'get_seller_fee_quote' );
			$order = $this->order();
			$order->method( 'get_meta' )->willReturn( array( 'charge_id' => 'CHAR_TEST', 'amount' => 10000, 'fee' => 0 ) );
			$order->expects( $this->never() )->method( 'delete_meta_data' );
			$order->expects( $this->never() )->method( 'update_meta_data' );
			$order->expects( $this->never() )->method( 'save' );
			$this->save_quote( $api, $order, $this->charge( 'PIX' ) );
		}

		public function test_changed_charge_amount_refreshes_the_quote(): void {
			$charge = $this->charge( 'PIX' );
			$charge['amount']['value'] = 11000;
			$quote = array( 'charge_id' => 'CHAR_TEST', 'amount' => 11000, 'fee' => 100 );
			$api = $this->createMock( Api::class );
			$api->expects( $this->once() )->method( 'get_seller_fee_quote' )->with( $charge )->willReturn( $quote );
			$order = $this->order();
			$order->method( 'get_meta' )->willReturn( array( 'charge_id' => 'CHAR_TEST', 'amount' => 10000, 'fee' => 99 ) );
			$order->expects( $this->once() )->method( 'update_meta_data' )->with( '_pagbank_seller_fee_quote', $quote );
			$order->expects( $this->once() )->method( 'save' );
			$this->save_quote( $api, $order, $charge );
		}

		public function test_existing_installment_lookup_keeps_its_parameters_and_cache(): void {
			$response = array( 'payment_methods' => array( 'credit_card' => array() ) );
			$this->respond( $response );
			$this->assertSame( $response, $this->api->charge_fees( 10000, 10, 4, '552100' ) );
			$this->assertSame( $response, $this->api->charge_fees( 10000, 10, 4, '552100' ) );
			$this->assertCount( 1, self::$requests );
			parse_str( parse_url( self::$requests[0]['url'], PHP_URL_QUERY ), $query );
			$this->assertArrayNotHasKey( 'show_seller_fees', $query );
			$this->assertSame( '4', $query['max_installments_no_interest'] );
			$this->assertSame( 15, self::$requests[0]['args']['timeout'] );
		}

		private function order() {
			$order = $this->getMockBuilder( WC_Order::class )->addMethods( array( 'get_id', 'get_meta', 'delete_meta_data', 'update_meta_data', 'set_date_modified', 'save' ) )->getMock();
			$order->method( 'get_id' )->willReturn( 123 );
			return $order;
		}

		private function save_quote( Api $api, WC_Order $order, array $charge ): void {
			$method = new ReflectionMethod( WebhookHandler::class, 'save_seller_fee_quote' );
			$method->setAccessible( true );
			$method->invoke( null, $api, $order, $charge );
		}

		private function respond( array $body, int $status = 200 ): void {
			self::$responses[] = array( 'response' => array( 'code' => $status ), 'body' => json_encode( $body ) );
		}

		private function amount( int $value, $fee ): array {
			return array( 'value' => $value, 'currency' => 'BRL', 'fees' => array( 'seller' => array( 'total' => $fee ) ) );
		}

		private function charge( string $method ): array {
			$charge = array(
				'id'             => 'CHAR_TEST',
				'amount'         => array( 'value' => 10000, 'currency' => 'BRL' ),
				'payment_method' => array(
					'type' => $method,
				),
			);
			if ( in_array( $method, array( 'CREDIT_CARD', 'DEBIT_CARD' ), true ) ) {
				$charge['payment_method']['installments'] = 1;
				$charge['payment_method']['card'] = array( 'brand' => 'MASTERCARD', 'first_digits' => '55210000' );
			}
			return $charge;
		}
	}
}
