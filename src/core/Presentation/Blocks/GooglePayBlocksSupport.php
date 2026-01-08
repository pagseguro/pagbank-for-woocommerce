<?php
/**
 * Google Pay Blocks Support for WooCommerce Checkout Blocks.
 *
 * @package PagBank_WooCommerce\Presentation\Blocks
 */

namespace PagBank_WooCommerce\Presentation\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use PagBank_WooCommerce\Gateways\GooglePayPaymentGateway;
use PagBank_WooCommerce\Presentation\Connect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GooglePayBlocksSupport.
 */
final class GooglePayBlocksSupport extends AbstractPaymentMethodType {

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'pagbank_google_pay';

	/**
	 * Gateway instance.
	 */
	private GooglePayPaymentGateway $gateway;

	/**
	 * Initializes the payment method.
	 */
	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_pagbank_google_pay_settings', array() );

		$gateways      = WC()->payment_gateways->payment_gateways();
		$this->gateway = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : null;
	}

	/**
	 * Returns if this payment method should be active.
	 */
	public function is_active(): bool {
		if ( ! $this->gateway ) {
			return false;
		}

		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 */
	public function get_payment_method_script_handles(): array {
		$asset_path = plugin_dir_path( PAGBANK_WOOCOMMERCE_FILE_PATH ) . 'dist/public/blocks/checkout-google-pay.js';

		if ( ! file_exists( $asset_path ) ) {
			return array();
		}

		// Register Google Pay SDK (external script, versioning managed by Google).
		wp_register_script(
			'google-pay-sdk',
			'https://pay.google.com/gp/p/js/pay.js',
			array(),
			'1.0', // External SDK version placeholder.
			true
		);

		wp_register_script(
			'pagbank-google-pay-blocks',
			plugins_url( 'dist/public/blocks/checkout-google-pay.js', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array( 'react', 'wc-blocks-registry', 'wc-settings', 'wp-html-entities', 'wp-element', 'google-pay-sdk' ),
			PAGBANK_WOOCOMMERCE_VERSION,
			true
		);

		wp_localize_script(
			'pagbank-google-pay-blocks',
			'pagbank_google_pay_data',
			array(
				'plugin_url' => plugins_url( '', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'pagbank-google-pay-blocks', 'pagbank-for-woocommerce' );
		}

		return array( 'pagbank-google-pay-blocks' );
	}

	/**
	 * Returns an array of script handles to enqueue for the admin.
	 */
	public function get_payment_method_script_handles_for_admin(): array {
		return $this->get_payment_method_script_handles();
	}

	/**
	 * Returns an array of data to be exposed to the payment method script.
	 */
	public function get_payment_method_data(): array {
		$environment  = $this->get_setting( 'environment', 'sandbox' );
		$connect      = new Connect( $environment );
		$connect_data = $connect->get_data();

		return array(
			'title'               => $this->get_setting( 'title', __( 'Google Pay', 'pagbank-for-woocommerce' ) ),
			'description'         => $this->get_setting( 'description', '' ),
			'icon'                => $this->gateway ? $this->gateway->icon : '',
			'supports'            => $this->get_supported_features(),
			'environment'         => $environment === 'production' ? 'PRODUCTION' : 'TEST',
			'gateway_merchant_id' => isset( $connect_data['account_id'] ) ? $connect_data['account_id'] : null,
			'merchant_name'       => get_bloginfo( 'name' ),
			'messages'            => array(
				'google_pay_not_available' => __( 'O Google Pay não está disponível neste dispositivo ou navegador.', 'pagbank-for-woocommerce' ),
				'payment_error'            => __( 'Houve um erro ao processar o pagamento. Tente novamente.', 'pagbank-for-woocommerce' ),
				'token_error'              => __( 'Não foi possível obter o token de pagamento do Google Pay.', 'pagbank-for-woocommerce' ),
			),
		);
	}

	/**
	 * Returns an array of supported features.
	 */
	public function get_supported_features(): array {
		$gateway = $this->gateway;

		if ( ! $gateway ) {
			return array();
		}

		return array_filter( $gateway->supports, array( $gateway, 'supports' ) );
	}
}
