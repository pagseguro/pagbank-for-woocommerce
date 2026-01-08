<?php
/**
 * Debit Card Blocks Support for WooCommerce Checkout Blocks.
 *
 * @package PagBank_WooCommerce\Presentation\Blocks
 */

namespace PagBank_WooCommerce\Presentation\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use PagBank_WooCommerce\Gateways\DebitCardPaymentGateway;
use PagBank_WooCommerce\Presentation\Connect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DebitCardBlocksSupport.
 */
final class DebitCardBlocksSupport extends AbstractPaymentMethodType {

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'pagbank_debit_card';

	/**
	 * Gateway instance.
	 */
	private DebitCardPaymentGateway $gateway;

	/**
	 * Initializes the payment method.
	 */
	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_pagbank_debit_card_settings', array() );

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
		$asset_path = plugin_dir_path( PAGBANK_WOOCOMMERCE_FILE_PATH ) . 'dist/public/blocks/checkout-debit-card.js';

		if ( ! file_exists( $asset_path ) ) {
			return array();
		}

		// Register PagBank SDK for card encryption.
		wp_register_script(
			'pagbank-sdk',
			'https://assets.pagseguro.com.br/checkout-sdk-js/rc/dist/browser/pagseguro.min.js',
			array(),
			PAGBANK_WOOCOMMERCE_VERSION,
			true
		);

		wp_register_script(
			'pagbank-debit-card-blocks',
			plugins_url( 'dist/public/blocks/checkout-debit-card.js', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array( 'react', 'wc-blocks-registry', 'wc-settings', 'wp-html-entities', 'wp-element', 'pagbank-sdk' ),
			PAGBANK_WOOCOMMERCE_VERSION,
			true
		);

		wp_localize_script(
			'pagbank-debit-card-blocks',
			'pagbank_debit_card_data',
			array(
				'plugin_url' => plugins_url( '', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			)
		);

		// Register styles (reuse credit card styles).
		wp_register_style(
			'pagbank-debit-card-blocks',
			plugins_url( 'dist/styles/blocks/checkout-credit-card.css', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array(),
			PAGBANK_WOOCOMMERCE_VERSION,
			'all'
		);

		wp_enqueue_style( 'pagbank-debit-card-blocks' );

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'pagbank-debit-card-blocks', 'pagbank-for-woocommerce' );
		}

		return array( 'pagbank-debit-card-blocks' );
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
			'title'                => $this->get_setting( 'title', __( 'Cartão de débito', 'pagbank-for-woocommerce' ) ),
			'description'          => $this->get_setting( 'description', '' ),
			'icon'                 => $this->gateway ? $this->gateway->icon : '',
			'supports'             => $this->get_supported_features(),
			'card_public_key'      => isset( $connect_data['public_key'] ) ? $connect_data['public_key'] : null,
			// Installments disabled for debit cards.
			'installments_enabled' => false,
			'maximum_installments' => 1,
			// 3DS is always enabled and mandatory for debit cards.
			'threeds_enabled'      => true,
			'api_3ds_session_url'  => $this->gateway ? $this->gateway->get_api_3ds_session_url() : '',
			'threeds_nonce'        => wp_create_nonce( 'pagbank_get_3ds_session' ),
			'messages'             => array(
				'invalid_public_key'            => __( 'Chave pública inválida.', 'pagbank-for-woocommerce' ),
				'invalid_holder_name'           => __( 'Nome do titular do cartão inválido.', 'pagbank-for-woocommerce' ),
				'invalid_card_number'           => __( 'Número do cartão inválido.', 'pagbank-for-woocommerce' ),
				'invalid_card_expiry_date'      => __( 'Data de expiração do cartão inválida.', 'pagbank-for-woocommerce' ),
				'invalid_security_code'         => __( 'Código de segurança do cartão inválido.', 'pagbank-for-woocommerce' ),
				'invalid_encrypted_card'        => __( 'O cartão criptografado não foi encontrado.', 'pagbank-for-woocommerce' ),
				'invalid_card_bin'              => __( 'O bin do cartão não foi encontrado.', 'pagbank-for-woocommerce' ),
				// 3DS messages.
				'threeds_session_error'         => __( 'Erro ao criar sessão 3DS. Tente novamente.', 'pagbank-for-woocommerce' ),
				'threeds_auth_error'            => __( 'Falha na autenticação 3DS. Tente novamente ou use outro cartão.', 'pagbank-for-woocommerce' ),
				'threeds_change_payment_method' => __( 'Este cartão não pode ser autenticado. Use outro método de pagamento.', 'pagbank-for-woocommerce' ),
				'invalid_cellphone'             => __( 'O celular informado não é válido.', 'pagbank-for-woocommerce' ),
				'threeds_not_supported'         => __( 'O cartão não pode ser autenticado. Use outro método de pagamento.', 'pagbank-for-woocommerce' ),
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
