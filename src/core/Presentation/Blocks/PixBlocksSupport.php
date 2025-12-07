<?php
/**
 * Pix Blocks Support for WooCommerce Checkout Blocks.
 *
 * @package PagBank_WooCommerce\Presentation\Blocks
 */

namespace PagBank_WooCommerce\Presentation\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use PagBank_WooCommerce\Gateways\PixPaymentGateway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PixBlocksSupport.
 */
final class PixBlocksSupport extends AbstractPaymentMethodType {

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'pagbank_pix';

	/**
	 * Gateway instance.
	 *
	 * @var PixPaymentGateway
	 */
	private $gateway;

	/**
	 * Initializes the payment method.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_pagbank_pix_settings', array() );

		$gateways      = WC()->payment_gateways->payment_gateways();
		$this->gateway = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : null;
	}

	/**
	 * Returns if this payment method should be active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		if ( ! $this->gateway ) {
			return false;
		}

		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$asset_path = plugin_dir_path( PAGBANK_WOOCOMMERCE_FILE_PATH ) . 'dist/public/checkout-pix.js';

		if ( ! file_exists( $asset_path ) ) {
			return array();
		}

		wp_register_script(
			'pagbank-pix-blocks',
			plugins_url( 'dist/public/checkout-pix.js', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array( 'react', 'wc-blocks-registry', 'wc-settings', 'wp-html-entities' ),
			PAGBANK_WOOCOMMERCE_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'pagbank-pix-blocks', 'pagbank-for-woocommerce' );
		}

		return array( 'pagbank-pix-blocks' );
	}

	/**
	 * Returns an array of script handles to enqueue for the admin.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles_for_admin() {
		return $this->get_payment_method_script_handles();
	}

	/**
	 * Returns an array of data to be exposed to the payment method script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title', __( 'Pix', 'pagbank-for-woocommerce' ) ),
			'description' => $this->get_setting( 'description', __( 'O código Pix será gerado assim que você finalizar o pedido.', 'pagbank-for-woocommerce' ) ),
			'supports'    => $this->get_supported_features(),
		);
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return array
	 */
	public function get_supported_features() {
		$gateway = $this->gateway;

		if ( ! $gateway ) {
			return array();
		}

		return array_filter( $gateway->supports, array( $gateway, 'supports' ) );
	}
}
