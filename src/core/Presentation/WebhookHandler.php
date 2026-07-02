<?php
/**
 * Handle webhooks.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use WooCommerce;

/**
 * Class WebhookHandler.
 *
 * Webhook bodies are not trusted. Each event is treated as a hint to reconcile;
 * the canonical status is fetched from the PagBank API via Api::get_charge().
 */
class WebhookHandler {

	/**
	 * Instance.
	 */
	private static ?WebhookHandler $instance = null;

	/**
	 * Logger.
	 */
	private \WC_Logger_Interface $logger;

	/**
	 * Initialize webhook handler.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_pagbank_woocommerce_handler', array( $this, 'handle' ) );
		add_action( 'init', array( $this, 'init_logs' ) );
	}

	/**
	 * Initialize logs.
	 */
	public function init_logs(): void {
		$this->logger = wc_get_logger();
	}

	/**
	 * Get instance.
	 */
	public static function get_instance(): WebhookHandler {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get webhook url.
	 */
	public static function get_webhook_url(): string {
		$custom_site_url = Helpers::get_constant_value( 'PAGBANK_WEBHOOK_SITE_URL' );

		if ( $custom_site_url ) {
			return $custom_site_url . '/wc-api/pagbank_woocommerce_handler';
		}

		return WooCommerce::instance()->api_request_url( 'pagbank_woocommerce_handler' );
	}

	/**
	 * Parse the reference_id sent on the webhook payload.
	 *
	 * Two formats are accepted:
	 *
	 *  - Legacy JSON `{ id, password }` issued before the signature refactor.
	 *    Returns `[ 'kind' => 'json', 'order_id' => int, 'password' => string ]`.
	 *    Both fields are required; missing password rejects the payload.
	 *  - Signed form `{order_id}:{hash}` where hash is 32 chars hex. Returns
	 *    `[ 'kind' => 'signed', 'order_id' => int, 'hash' => string ]`.
	 *
	 * Returns null for any other shape (plain integers, malformed JSON, etc).
	 *
	 * @param string|null $raw The raw reference_id from the webhook payload.
	 * @return array{kind:string,order_id:int,password?:string,hash?:string}|null
	 */
	public static function parse_reference_id( ?string $raw ): ?array {
		if ( null === $raw || '' === $raw ) {
			return null;
		}

		$decoded = json_decode( $raw, true );

		if ( is_array( $decoded ) ) {
			if ( ! isset( $decoded['id'] ) || ! is_numeric( $decoded['id'] ) ) {
				return null;
			}

			$password = isset( $decoded['password'] ) ? $decoded['password'] : null;

			if ( ! is_string( $password ) || '' === $password ) {
				return null;
			}

			$id = (int) $decoded['id'];

			if ( $id <= 0 ) {
				return null;
			}

			return array(
				'kind'     => 'json',
				'order_id' => $id,
				'password' => $password,
			);
		}

		if ( 1 === preg_match( '/^(\d+):([a-f0-9]{32})$/', $raw, $matches ) ) {
			$id = (int) $matches[1];

			if ( $id <= 0 ) {
				return null;
			}

			return array(
				'kind'     => 'signed',
				'order_id' => $id,
				'hash'     => $matches[2],
			);
		}

		return null;
	}

	/**
	 * Resolve the WC order from a parsed reference_id payload.
	 *
	 * Both kinds load the order by primary key and then verify a secret
	 * persisted on the order meta with `hash_equals`. JSON-legacy checks
	 * `_pagbank_password`, written by older plugin versions; signed checks
	 * `_pagbank_reference_signature`. Missing or mismatched secrets reject.
	 *
	 * Returns null on any mismatch; the caller turns that into a 400.
	 *
	 * @param array $parsed Parsed reference_id from parse_reference_id().
	 */
	private static function resolve_order_from_reference( array $parsed ): ?\WC_Order {
		$order = wc_get_order( $parsed['order_id'] );

		if ( ! $order ) {
			return null;
		}

		if ( 'json' === $parsed['kind'] ) {
			$stored = (string) $order->get_meta( '_pagbank_password' );

			if ( '' === $stored || ! hash_equals( $stored, $parsed['password'] ) ) {
				return null;
			}

			return $order;
		}

		if ( 'signed' === $parsed['kind'] ) {
			$stored = (string) $order->get_meta( '_pagbank_reference_signature' );

			if ( '' === $stored || ! hash_equals( $stored, $parsed['hash'] ) ) {
				return null;
			}

			return $order;
		}

		return null;
	}

	/**
	 * Map a PagBank charge status to the WooCommerce action the handler should take.
	 *
	 * Returns one of: 'on-hold', 'failed', 'cancelled', 'completed', or null when
	 * the status is unknown / should be ignored.
	 *
	 * @param string $charge_status The status string from the PagBank charge payload.
	 */
	public static function map_charge_status( string $charge_status ): ?string {
		switch ( $charge_status ) {
			case 'IN_ANALYSIS':
			case 'WAITING':
				return 'on-hold';
			case 'DECLINED':
				return 'failed';
			case 'CANCELED':
				return 'cancelled';
			case 'PAID':
				return 'completed';
			default:
				return null;
		}
	}

	/**
	 * Log.
	 *
	 * @param string $message Message.
	 * @param array  $context Additional context data.
	 */
	private function log( string $message, array $context = array() ): void {
		if ( ! $this->logger ) {
			return;
		}

		$log_context = array_merge( array( 'source' => 'pagbank_webhook' ), $context );
		$this->logger->info( $message, $log_context );
	}

	/**
	 * Handle webhook.
	 */
	public function handle(): void {
		try {
			$input   = file_get_contents( 'php://input' );
			$headers = array_change_key_case( getallheaders(), CASE_LOWER );

			$content_type = $headers['content-type'] ?? '';

			if ( 'application/json' !== $content_type ) {
				$this->log(
					'Webhook received but ignored due to invalid content type',
					array(
						'content_type' => $content_type,
						'input'        => $input,
						'headers'      => $headers,
					)
				);

				wp_send_json_error(
					array(
						'message' => 'Invalid content type. Webhook will not be processed.',
					),
					200
				);
			}

			$this->log(
				'Webhook received',
				array(
					'payload' => $input,
				)
			);

			$payload = json_decode( $input, true );

			$raw_reference_id = isset( $payload['reference_id'] ) ? $payload['reference_id'] : null;
			$parsed           = self::parse_reference_id( $raw_reference_id );

			if ( null === $parsed ) {
				$this->log(
					'Webhook validation failed: reference_id could not be parsed',
					array(
						'reference_id' => $raw_reference_id,
					)
				);
				wp_send_json_error(
					array(
						'message' => __( 'Pedido não encontrado.', 'pagbank-for-woocommerce' ),
					),
					400
				);
			}

			$order = self::resolve_order_from_reference( $parsed );

			if ( ! $order ) {
				$this->log(
					'Webhook validation failed: order not found',
					array(
						'reference_kind' => $parsed['kind'],
					)
				);
				wp_send_json_error(
					array(
						'message' => __( 'Pedido não encontrado.', 'pagbank-for-woocommerce' ),
					),
					400
				);
			}

			$order_id = $order->get_id();

			if ( ! in_array( $order->get_payment_method(), PaymentGateways::get_gateway_ids(), true ) ) {
				$this->log(
					'Webhook validation failed: invalid payment method',
					array(
						'order_id'       => $order_id,
						'payment_method' => $order->get_payment_method(),
					)
				);
				wp_send_json_error(
					array(
						'message' => __( 'Pedido inválido', 'pagbank-for-woocommerce' ),
					),
					400
				);
			}

			$webhook_charge_id = isset( $payload['charges'][0]['id'] ) ? (string) $payload['charges'][0]['id'] : '';
			$stored_charge_id  = (string) $order->get_meta( '_pagbank_charge_id' );

			if ( '' === $webhook_charge_id ) {
				$this->log(
					'Webhook validation failed: missing charge id in payload',
					array(
						'order_id' => $order_id,
					)
				);
				wp_send_json_error(
					array(
						'message' => __( 'Cobrança não encontrada no webhook.', 'pagbank-for-woocommerce' ),
					),
					400
				);
			}

			if ( '' !== $stored_charge_id && $stored_charge_id !== $webhook_charge_id ) {
				$this->log(
					'Webhook validation failed: charge id mismatch between webhook and stored order',
					array(
						'order_id'          => $order_id,
						'webhook_charge_id' => $webhook_charge_id,
						'stored_charge_id'  => $stored_charge_id,
					)
				);
				wp_send_json_error(
					array(
						'message' => __( 'Cobrança divergente.', 'pagbank-for-woocommerce' ),
					),
					400
				);
			}

			$charge_id   = '' !== $stored_charge_id ? $stored_charge_id : $webhook_charge_id;
			$environment = $order->get_meta( '_pagbank_environment' );
			if ( ! is_string( $environment ) || '' === $environment ) {
				$environment = 'production';
			}

			$api    = new Api( $environment );
			$charge = $api->get_charge( $charge_id );

			if ( is_wp_error( $charge ) ) {
				$this->log(
					'Webhook verification failed: could not fetch charge from PagBank API',
					array(
						'order_id'   => $order_id,
						'charge_id'  => $charge_id,
						'error_code' => $charge->get_error_code(),
					)
				);
				// Fail-closed: 5xx tells PagBank to retry the webhook later.
				wp_send_json_error(
					array(
						'message' => __( 'Falha ao verificar a cobrança na API do PagBank. Tente novamente mais tarde.', 'pagbank-for-woocommerce' ),
					),
					503
				);
			}

			$canonical_status    = isset( $charge['status'] ) ? (string) $charge['status'] : '';
			$canonical_charge_id = isset( $charge['id'] ) ? (string) $charge['id'] : $charge_id;

			$order->add_order_note(
				sprintf(
					/* translators: 1: charge status from API, 2: charge ID. */
					__( 'Webhook PagBank recebido. Status verificado via API: %1$s. Cobrança: %2$s.', 'pagbank-for-woocommerce' ),
					$canonical_status,
					$canonical_charge_id
				)
			);

			switch ( self::map_charge_status( $canonical_status ) ) {
				case 'on-hold':
					$order->update_status( 'on-hold', __( 'O PagBank está analisando a transação.', 'pagbank-for-woocommerce' ) );

					// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Default order status.
					do_action( 'pagbank_order_on-hold', $order );
					break;
				case 'failed':
					$order->update_status( 'failed', __( 'O pagamento foi recusado.', 'pagbank-for-woocommerce' ) );

					do_action( 'pagbank_order_failed', $order );
					break;
				case 'completed':
					$order->payment_complete( $canonical_charge_id );
					$order->update_meta_data( '_pagbank_charge_id', $canonical_charge_id );
					$order->save_meta_data();

					do_action( 'pagbank_order_completed', $order );
					break;
				case 'cancelled':
					$order->update_status( 'cancelled', __( 'O pagamento foi cancelado.', 'pagbank-for-woocommerce' ) );

					do_action( 'pagbank_order_cancelled', $order );
					break;
				default:
					// Unknown status — note already added; no state change.
					break;
			}

			$this->log(
				'Webhook processed successfully',
				array(
					'order_id' => $order_id,
					'status'   => $canonical_status,
				)
			);

			wp_send_json_success(
				array(
					'message' => 'Webhook processed successfully',
				),
				200
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => 'Error while processing the webhook.',
				),
				400
			);
		}

		wp_die();
	}
}
