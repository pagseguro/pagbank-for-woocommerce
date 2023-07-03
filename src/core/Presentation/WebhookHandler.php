<?php
/**
 * Handle webhooks.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

use Exception;
use WC_Logger;

/**
 * Class WebhookHandler.
 */
class WebhookHandler {

	/**
	 * Instance.
	 *
	 * @var WebhookHandler
	 */
	private static $instance = null;

	/**
	 * Logger.
	 *
	 * @var WC_Logger
	 */
	private $logger;

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
	public function init_logs() {
		$this->logger = new WC_Logger();
	}

	/**
	 * Get instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get webhook url.
	 */
	public static function get_webhook_url(): string {
		$site_url = defined( 'PAGBANK_WEBHOOK_SITE_URL' ) && PAGBANK_WEBHOOK_SITE_URL ? PAGBANK_WEBHOOK_SITE_URL : site_url( '/' );

		return rtrim( $site_url, '/' ) . '/wc-api/pagbank_woocommerce_handler';
	}

	/**
	 * Log.
	 *
	 * @param string $message Message.
	 */
	private function log( string $message ): void {
		if ( ! $this->logger ) {
			return;
		}

		$this->logger->add( 'pagbank_webhook', $message );
	}

	/**
	 * Handle webhook.
	 */
	public function handle() {
		try {
			$input     = file_get_contents( 'php://input' );
			$payload   = json_decode( $input, true );
			$reference = isset( $payload['reference_id'] ) ? json_decode( $payload['reference_id'], true ) : null;
			$order_id  = $reference['id'];
			$signature = $reference['password'];

			$this->log( 'Webhook received: ' . $input );

			if ( empty( $order_id ) ) {
				$this->log( 'Webhook validation failed: order_id is empty' );
				return wp_send_json_error(
					array(
						'message' => __( 'Pedido não encontrado.', 'pagbank-woocommerce' ),
					),
					400
				);
			}

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				$this->log( 'Webhook validation failed: order not found' );
				return wp_send_json_error(
					array(
						'message' => __( 'Pedido não encontrado.', 'pagbank-woocommerce' ),
					),
					400
				);
			}

			if ( ! in_array( $order->get_payment_method(), array( 'pagbank_credit_card', 'pagbank_boleto', 'pagbank_pix' ) ) ) {
				$this->log( 'Webhook validation failed: invalid payment method for order id ' . $order_id );
				return wp_send_json_error(
					array(
						'message' => __( 'Pedido inválido', 'pagbank-woocommerce' ),
					),
					400
				);
			}

			$password = $order->get_meta( '_pagbank_password' );
			$charge   = $payload['charges'][0];

			if ( ! $signature ) {
				$this->log( 'Webhook validation failed: missing signature for order id ' . $order_id );
				return wp_send_json_error(
					array(
						'message' => __( 'Assinatura não encontrada.', 'pagbank-woocommerce' ),
					),
					400
				);
			}

			$is_valid_signature = $password === $signature;

			if ( ! $is_valid_signature ) {
				$this->log( 'Webhook validation failed: invalid signature for order id ' . $order_id . ' (' . $signature . ')' );
				return wp_send_json_error(
					array(
						'message' => __( 'Assinatura inválida.', 'pagbank-woocommerce' ),
					),
					400
				);
			}

			if ( $charge['status'] === 'IN_ANALYSIS' ) {
				$order->update_status( 'on-hold', __( 'O PagBank está analisando a transação.', 'pagbank-woocommerce' ) );
			} elseif ( $charge['status'] === 'DECLINED' ) {
				$order->update_status( 'failed', __( 'O pagamento foi recusado.', 'pagbank-woocommerce' ) );
			} elseif ( $charge['status'] === 'PAID' ) {
				$order->payment_complete( $charge['id'] );
			}

			$this->log( 'Webhook processed successfully' );

			wp_send_json_success(
				array(
					'message' => 'Webhook processed successfully',
				),
				200
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => 'Erro ao processar o webhook',
				),
				400
			);
		}

		wp_die();
	}

}
