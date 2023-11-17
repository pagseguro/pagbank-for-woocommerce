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
		'pagbank_pix',
		'pagbank_boleto',
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
		$methods[] = 'PagBank_WooCommerce\Gateways\BoletoPaymentGateway';
		$methods[] = 'PagBank_WooCommerce\Gateways\PixPaymentGateway';

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
		if ( ! isset( $_GET['section'] ) || ! in_array( $_GET['section'], self::$gateway_ids, true ) ) {
			return;
		}

		wp_register_script( 'pagbank-for-woocommerce-admin-settings', plugins_url( 'dist/admin/admin-settings.js', PAGBANK_WOOCOMMERCE_FILE_PATH ), array(), PAGBANK_WOOCOMMERCE_VERSION, true );
		wp_register_style(
			'pagbank-for-woocommerce-admin-settings',
			plugins_url( 'styles/admin-fields.css', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array(),
			PAGBANK_WOOCOMMERCE_VERSION,
			'all'
		);

		wp_scripts()->add_data( 'pagbank-for-woocommerce-admin-settings', 'pagbank_script', true );

		wp_enqueue_script( 'pagbank-for-woocommerce-admin-settings' );
		wp_enqueue_script( 'thickbox' );

		wp_enqueue_style( 'thickbox' );
		wp_enqueue_style( 'pagbank-for-woocommerce-admin-settings' );
	}
}
