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
	 * Initialize webhook handler.
	 */
	public static function init(): void {
		add_action( 'woocommerce_api_pagbank_woocommerce_handler', array( self::class, 'handle' ) );
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
		$headers = getallheaders();
		$payload = file_get_contents( 'php://input' );
		$token   = 'random_token';

		if ( ! isset( $headers['x-authenticity-token'] ) ) {
			return wp_send_json(
				array(
					'result'  => 'error',
					'message' => 'missing x-authenticity-token header',
				)
			);
		}

		$signature = hash( 'sha256', $token . '-' . $payload );

		if ( $headers['x-authenticity-token'] !== $signature ) {
			return wp_send_json(
				array(
					'result'  => 'error',
					'message' => 'invalid x-authenticity-token header',
				)
			);
		}

		$body = json_decode( $payload, true );

		if ( ! isset( $body['metadata']['order_id'] ) ) {
			return wp_send_json(
				array(
					'result'  => 'error',
					'message' => 'missing order_id in metadata',
				)
			);
		}

		$order_id = $body['metadata']['order_id'];
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return wp_send_json(
				array(
					'result'  => 'error',
					'message' => 'Order not found',
				)
			);
		}

		// TODO: check correct webhook status and change the order.

		wp_send_json(
			array(
				'result'  => 'success',
				'message' => 'Webhook processed successfully',
			),
			200
		);

		wp_die();
	}

}
