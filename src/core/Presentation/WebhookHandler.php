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

			$content_type = $headers['content-type'];

			if ( $content_type !== 'application/json' ) {
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
						'message' => 'Content type inválido. O webhook não será processado.',
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

			$reference = isset( $payload['reference_id'] ) ? json_decode( $payload['reference_id'], true ) : null;
			$order_id  = isset( $reference['id'] ) ? $reference['id'] : null;
			$signature = isset( $reference['password'] ) ? $reference['password'] : null;

			if ( empty( $order_id ) ) {
				$this->log(
					'Webhook validation failed: order_id is empty',
					array(
						'reference' => $reference,
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

			$password = $order->get_meta( '_pagbank_password' );
			$charge   = $payload['charges'][0];

			if ( ! $signature ) {
				$this->log(
					'Webhook validation failed: missing signature',
					array(
						'order_id' => $order_id,
					)
				);
				wp_send_json_error(
					array(
						'message' => __( 'Assinatura não encontrada.', 'pagbank-for-woocommerce' ),
					),
					400
				);
			}

			$is_valid_signature = $password === $signature;

			if ( ! $is_valid_signature ) {
				$this->log(
					'Webhook validation failed: invalid signature',
					array(
						'order_id'  => $order_id,
						'signature' => $signature,
					)
				);
				wp_send_json_error(
					array(
						'message' => __( 'Assinatura inválida.', 'pagbank-for-woocommerce' ),
					),
					400
				);
			}

			switch ( $charge['status'] ) {
				case 'IN_ANALYSIS':
				case 'WAITING':
					$order->update_status( 'on-hold', __( 'O PagBank está analisando a transação.', 'pagbank-for-woocommerce' ) );

					// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Default order status.
					do_action( 'pagbank_order_on-hold', $order );
					break;
				case 'DECLINED':
					$order->update_status( 'failed', __( 'O pagamento foi recusado.', 'pagbank-for-woocommerce' ) );

					do_action( 'pagbank_order_failed', $order );
					break;
				case 'PAID':
					$order->payment_complete( $charge['id'] );
					$order->update_meta_data( '_pagbank_charge_id', $charge['id'] );
					$order->save_meta_data();

					do_action( 'pagbank_order_completed', $order );
					break;
				case 'CANCELED':
					$order->update_status( 'cancelled', __( 'O pagamento foi cancelado.', 'pagbank-for-woocommerce' ) );

					do_action( 'pagbank_order_cancelled', $order );
					break;
			}

			$this->log(
				'Webhook processed successfully',
				array(
					'order_id' => $order_id,
					'status'   => $charge['status'],
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
					'message' => 'Erro ao processar o webhook.',
				),
				400
			);
		}

		wp_die();
	}
}
