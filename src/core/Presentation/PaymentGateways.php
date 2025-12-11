<?php
/**
 * Adds support for payment gateways and common scripts.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PaymentGateways.
 */
class PaymentGateways {

	/**
	 * Instance.
	 *
	 * @var PaymentGateways
	 */
	private static $instance = null;

	/**
	 * Gateway ids.
	 *
	 * @var array
	 */
	public static $gateway_ids = array(
		'pagbank_credit_card',
		'pagbank_debit_card',
		'pagbank_pix',
		'pagbank_boleto',
		'pagbank_pay_with_pagbank',
		'pagbank_google_pay',
		'pagbank_apple_pay',
	);

	/**
	 * Init.
	 */
	public function __construct() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
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
	 * Add gateways.
	 *
	 * @param array $methods Payment gateways.
	 *
	 * @return array
	 */
	public function add_gateways( $methods ): array {
		$methods[] = 'PagBank_WooCommerce\Gateways\CreditCardPaymentGateway';
		$methods[] = 'PagBank_WooCommerce\Gateways\DebitCardPaymentGateway';
		$methods[] = 'PagBank_WooCommerce\Gateways\BoletoPaymentGateway';
		$methods[] = 'PagBank_WooCommerce\Gateways\PixPaymentGateway';
		$methods[] = 'PagBank_WooCommerce\Gateways\PayWithPagBankGateway';
		$methods[] = 'PagBank_WooCommerce\Gateways\GooglePayPaymentGateway';
		$methods[] = 'PagBank_WooCommerce\Gateways\ApplePayPaymentGateway';

		return $methods;
	}

	/**
	 * Enqueue scripts in admin.
	 *
	 * @param string $hook Hook.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		// Add PagBank branding to payment gateways list.
		if ( 'checkout' === $tab && empty( $section ) ) {
			$this->enqueue_gateway_list_styles();
			return;
		}

		if ( ! in_array( $section, self::$gateway_ids, true ) ) {
			return;
		}

		// Enqueue React gateway settings.
		$this->enqueue_gateway_settings_scripts( $section );
	}

	/**
	 * Enqueue styles for gateway list page.
	 *
	 * @return void
	 */
	private function enqueue_gateway_list_styles(): void {
		wp_enqueue_style(
			'pagbank-gateway-list',
			plugins_url( 'dist/styles/admin/gateway-list.css', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array(),
			PAGBANK_WOOCOMMERCE_VERSION
		);

		// Pass dynamic values via CSS custom properties.
		$icon_url   = plugins_url( 'dist/images/icons/pagbank.png', PAGBANK_WOOCOMMERCE_FILE_PATH );
		$badge_text = __( 'Oficial PagBank', 'pagbank-for-woocommerce' );

		wp_add_inline_style(
			'pagbank-gateway-list',
			':root { --pagbank-icon-url: url("' . esc_url( $icon_url ) . '"); --pagbank-badge-text: "' . esc_attr( $badge_text ) . '"; }'
		);
	}

	/**
	 * Enqueue React gateway settings scripts.
	 *
	 * @param string $gateway_id Gateway ID.
	 *
	 * @return void
	 */
	private function enqueue_gateway_settings_scripts( string $gateway_id ): void {
		$dependencies = array(
			'react',
			'react-dom',
			'wp-element',
			'wp-components',
			'wp-api-fetch',
			'wp-i18n',
		);

		wp_enqueue_script(
			'pagbank-gateway-settings',
			plugins_url( 'dist/admin/gateway-settings.js', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			$dependencies,
			PAGBANK_WOOCOMMERCE_VERSION,
			true
		);

		wp_enqueue_style(
			'pagbank-gateway-settings',
			plugins_url( 'dist/styles/admin/gateway-settings.css', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array( 'wp-components' ),
			PAGBANK_WOOCOMMERCE_VERSION
		);

		wp_localize_script(
			'pagbank-gateway-settings',
			'pagbankSettings',
			array(
				'gatewayId'           => $gateway_id,
				'restUrl'             => rest_url(),
				'nonce'               => wp_create_nonce( 'wp_rest' ),
				'oauthNonce'          => wp_create_nonce( 'pagbank_woocommerce_oauth' ),
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'isLocalhost'         => Helpers::is_localhost(),
				'connectApplications' => Connect::get_connect_applications(),
				'pluginUrl'           => plugins_url( '', PAGBANK_WOOCOMMERCE_FILE_PATH ),
				'settingsUrl'         => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' ),
			)
		);

		// Configure api-fetch to use the nonce.
		wp_add_inline_script(
			'wp-api-fetch',
			sprintf(
				'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( "%s" ) );',
				wp_create_nonce( 'wp_rest' )
			),
			'after'
		);
	}
}
