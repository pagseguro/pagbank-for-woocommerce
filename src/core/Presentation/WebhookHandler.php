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
	 * Supports the new format (plain wc_order_id as string) and the legacy
	 * format (JSON-encoded { id, password } used before this refactor).
	 * Returns null when no valid order id could be extracted.
	 *
	 * @param string|null $raw The raw reference_id from the webhook payload.
	 */
	public static function parse_reference_id( ?string $raw ): ?int {
		if ( null === $raw || '' === $raw ) {
			return null;
		}

		$decoded = json_decode( $raw, true );

		if ( is_array( $decoded ) ) {
			if ( ! isset( $decoded['id'] ) ) {
				return null;
			}

			$candidate = $decoded['id'];
		} else {
			$candidate = $raw;
		}

		if ( ! is_numeric( $candidate ) ) {
			return null;
		}

		$id = (int) $candidate;

		return $id > 0 ? $id : null;
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

			$order_id = self::parse_reference_id( isset( $payload['reference_id'] ) ? $payload['reference_id'] : null );

			if ( null === $order_id ) {
				$this->log(
					'Webhook validation failed: order_id could not be parsed from reference_id',
					array(
						'reference_id' => isset( $payload['reference_id'] ) ? $payload['reference_id'] : null,
					)
				);
				wp_send_json_error(
					array(
						'message' => __( 'Pedido não encontrado.', 'pagbank-for-woocommerce' ),
					),
					400
				);
			}

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				$this->log(
					'Webhook validation failed: order not found',
					array(
						'order_id' => $order_id,
					)
				);
				wp_send_json_error(
					array(
						'message' => __( 'Pedido não encontrado.', 'pagbank-for-woocommerce' ),
					),
					400
				);
			}

			if ( ! in_array( $order->get_payment_method(), array( 'pagbank_credit_card', 'pagbank_boleto', 'pagbank_pix' ), true ) ) {
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
