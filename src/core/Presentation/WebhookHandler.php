<?php
/**
 * Handle webhooks.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

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
	 * Initialize webhook handler.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_pagbank_woocommerce_handler', array( $this, 'handle' ) );
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

		return $site_url . '/wc-api/pagbank_woocommerce_handler';
	}

	/**
	 * Handle webhook.
	 */
	public function handle() {
		$payload  = json_decode( file_get_contents( 'php://input' ), true );
		$order_id = $payload['reference_id'];

		if ( empty( $order_id ) ) {
			return wp_send_json_error(
				array(
					'message' => __( 'Pedido não encontrado.', 'pagbank-woocommerce' ),
				),
				400
			);
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return wp_send_json_error(
				array(
					'message' => __( 'Pedido não encontrado.', 'pagbank-woocommerce' ),
				),
				400
			);
		}

		$order_environment = $order->get_meta( '_pagbank_environment' );
		$charge            = $payload['charges'][0];

		if ( $order_environment !== 'sandbox' ) {
			$signature = $charge['metadata']['signature'];

			if ( ! $signature ) {
				return wp_send_json_error(
					array(
						'message' => __( 'Assinatura não encontrada.', 'pagbank-woocommerce' ),
					),
					400
				);
			}

			$is_valid_signature = validate_order_id_signature( $order->get_id(), $signature );

			if ( ! $is_valid_signature ) {
				return wp_send_json_error(
					array(
						'message' => __( 'Assinatura inválida.', 'pagbank-woocommerce' ),
					),
					400
				);
			}
		}

		if ( $charge['status'] === 'IN_ANALYSIS' ) {
			$order->update_status( 'on-hold', __( 'O PagBank está analisando a transação.', 'pagbank-woocommerce' ) );
		} elseif ( $charge['status'] === 'DECLINED' ) {
			$order->update_status( 'failed', __( 'O pagamento foi recusado.', 'pagbank-woocommerce' ) );
		} elseif ( $charge['status'] === 'PAID' ) {
			$order->payment_complete( $charge['id'] );
		}

		wp_send_json_success(
			array(
				'message' => 'Webhook processed successfully',
			),
			200
		);

		wp_die();
	}

}
