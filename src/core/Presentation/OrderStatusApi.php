<?php
/**
 * Order Status REST API endpoint.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class OrderStatusApi.
 */
class OrderStatusApi {

	/**
	 * Instance.
	 */
	private static ?OrderStatusApi $instance = null;

	/**
	 * Get instance.
	 */
	public static function get_instance(): OrderStatusApi {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * OrderStatusApi constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'pagbank/v1',
			'/order/(?P<order_id>\d+)/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_order_status' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'order_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'key'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check permission for the endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		$order_id  = $request->get_param( 'order_id' );
		$order_key = $request->get_param( 'key' );

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Pedido não encontrado.', 'pagbank-for-woocommerce' ), array( 'status' => 404 ) );
		}

		// Verify order key for security.
		if ( $order->get_order_key() !== $order_key ) {
			return new WP_Error( 'invalid_key', __( 'Chave de pedido inválida.', 'pagbank-for-woocommerce' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Get order status.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_order_status( WP_REST_Request $request ) {
		$order_id = $request->get_param( 'order_id' );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Pedido não encontrado.', 'pagbank-for-woocommerce' ), array( 'status' => 404 ) );
		}

		$status         = $order->get_status();
		$payment_method = $order->get_payment_method();

		// Check if payment method is PagBank.
		if ( ! in_array( $payment_method, array( 'pagbank_credit_card', 'pagbank_debit_card', 'pagbank_boleto', 'pagbank_pix', 'pagbank_pay_with_pagbank' ), true ) ) {
			return new WP_Error( 'invalid_payment_method', __( 'Método de pagamento inválido.', 'pagbank-for-woocommerce' ), array( 'status' => 400 ) );
		}

		$response_data = array(
			'order_id'       => $order_id,
			'status'         => $status,
			'payment_method' => $payment_method,
			'is_paid'        => $order->is_paid(),
		);

		// Add Pix-specific data if applicable.
		if ( 'pagbank_pix' === $payment_method ) {
			$pix_expiration_date = $order->get_meta( '_pagbank_pix_expiration_date' );

			$response_data['pix_expiration_date'] = $pix_expiration_date;
			$response_data['is_expired']          = ! empty( $pix_expiration_date ) && strtotime( $pix_expiration_date ) < time();
		}

		// Add Pay with PagBank-specific data if applicable.
		if ( 'pagbank_pay_with_pagbank' === $payment_method ) {
			$qrcode_expiration_date = $order->get_meta( '_pagbank_qrcode_expiration_date' );

			$response_data['qrcode_expiration_date'] = $qrcode_expiration_date;
			$response_data['is_expired']             = ! empty( $qrcode_expiration_date ) && strtotime( $qrcode_expiration_date ) < time();
		}

		return new WP_REST_Response( $response_data, 200 );
	}
}
