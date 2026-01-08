<?php
/**
 * Checkout PagBank Blocks Support for WooCommerce Checkout Blocks.
 *
 * @package PagBank_WooCommerce\Presentation\Blocks
 */

namespace PagBank_WooCommerce\Presentation\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use PagBank_WooCommerce\Gateways\CheckoutPaymentGateway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CheckoutBlocksSupport.
 */
final class CheckoutBlocksSupport extends AbstractPaymentMethodType {

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'pagbank_checkout';

	/**
	 * Gateway instance.
	 */
	private CheckoutPaymentGateway $gateway;

	/**
	 * Initializes the payment method.
	 */
	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_pagbank_checkout_settings', array() );

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
		$asset_path = plugin_dir_path( PAGBANK_WOOCOMMERCE_FILE_PATH ) . 'dist/public/blocks/checkout-checkout.js';

		if ( ! file_exists( $asset_path ) ) {
			return array();
		}

		wp_register_script(
			'pagbank-checkout-blocks',
			plugins_url( 'dist/public/blocks/checkout-checkout.js', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array( 'react', 'wc-blocks-registry', 'wc-settings', 'wp-html-entities' ),
			PAGBANK_WOOCOMMERCE_VERSION,
			true
		);

		wp_localize_script(
			'pagbank-checkout-blocks',
			'pagbank_checkout_data',
			array(
				'plugin_url' => plugins_url( '', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'pagbank-checkout-blocks', 'pagbank-for-woocommerce' );
		}

		return array( 'pagbank-checkout-blocks' );
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
		return array(
			'title'       => $this->get_setting( 'title', __( 'Checkout PagBank', 'pagbank-for-woocommerce' ) ),
			'description' => $this->get_setting( 'description', __( 'Você será redirecionado para o PagBank para finalizar o pagamento.', 'pagbank-for-woocommerce' ) ),
			'icon'        => $this->gateway ? $this->gateway->icon : '',
			'supports'    => $this->get_supported_features(),
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
