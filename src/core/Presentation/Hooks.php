<?php
/**
 * Application hooks.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Order;

/**
 * Class Hooks.
 */
class Hooks {

	/**
	 * Instance.
	 *
	 * @var Hooks
	 */
	private static $instance = null;

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
	 * Hooks constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'load_text_domain' ) );
		add_filter( 'woocommerce_payment_token_class', array( $this, 'filter_payment_token_class_name' ), 10, 2 );
		add_filter( 'woocommerce_payment_methods_types', array( $this, 'filter_payment_method_types' ), 10, 1 );
		add_filter( 'woocommerce_payment_methods_list_item', array( $this, 'filter_payment_methods_list_item' ), 10, 2 );
		add_filter( 'script_loader_tag', array( $this, 'add_type_attribute' ), 100, 3 );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'filter_woocommerce_get_order_item_totals' ), 10, 2 );
		add_filter(
			'plugin_action_links_' . plugin_basename( PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array(
				$this,
				'plugin_action_links',
			)
		);
		add_action( 'init', array( $this, 'filter_gateways_settings' ) );

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'check_for_plugin_dependencies' ) );
		}
	}

	/**
	 * Load text domain.
	 */
	public function load_text_domain() {
		load_plugin_textdomain( 'pagbank-for-woocommerce', false, dirname( plugin_basename( PAGBANK_WOOCOMMERCE_FILE_PATH ) ) . '/languages' );
	}

	/**
	 * Filter payment token class name.
	 *
	 * @param  string $class_name Class name.
	 * @param  string $type       Type.
	 *
	 * @return string             Filtered class name.
	 */
	public function filter_payment_token_class_name( $class_name, $type ) {
		if ( $type === 'PagBank_CC' ) {
			return 'PagBank_WooCommerce\Presentation\PaymentToken';
		}

		return $class_name;
	}

	/**
	 * Filter payment method types.
	 *
	 * @param  array $types Payment method types.
	 *
	 * @return array        Filtered payment method types.
	 */
	public function filter_payment_method_types( $types ) {
		$types['pagbank_cc'] = __( 'Cartão de crédito', 'pagbank-for-woocommerce' );

		return $types;
	}

	/**
	 * Controls the output for credit cards on the my account page.
	 *
	 * @param  array            $item         Individual list item from woocommerce_saved_payment_methods_list.
	 * @param  WC_Payment_Token $payment_token The payment token associated with this method entry.
	 * @return array                           Filtered item.
	 */
	public function filter_payment_methods_list_item( $item, $payment_token ) {
		if ( 'pagbank_cc' !== strtolower( $payment_token->get_type() ) ) {
			return $item;
		}

		$card_type               = $payment_token->get_card_type();
		$item['method']['last4'] = $payment_token->get_last4();
		$item['method']['brand'] = ( ! empty( $card_type ) ? ucfirst( $card_type ) : esc_html__( 'Cartão de crédito', 'pagbank-for-woocommerce' ) );
		$item['expires']         = $payment_token->get_expiry_month() . '/' . substr( $payment_token->get_expiry_year(), -2 );

		return $item;
	}

	/**
	 * Add type attribute to script tag.
	 *
	 * @param  string $tag    Script tag.
	 * @param  string $handle Script handle.
	 * @param  string $src    Script source.
	 *
	 * @return string         Filtered script tag.
	 */
	public function add_type_attribute( $tag, $handle, $src ) {
		$type = wp_scripts()->get_data( $handle, 'pagbank_script' );

		if ( $type === true ) {
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
			$tag = '<script src="' . esc_url( $src ) . '" type="module"></script>';
		}

		return $tag;
	}

	/**
	 * Filter woocommerce_get_order_item_totals.
	 *
	 * @param  array    $total_rows Total rows.
	 * @param  WC_Order $order      Order.
	 *
	 * @return array                 Filtered total rows.
	 */
	public function filter_woocommerce_get_order_item_totals( $total_rows, WC_Order $order ) {
		if ( $order->get_payment_method() === 'pagbank_credit_card' ) {
			$installments                = (int) $order->get_meta( '_pagbank_credit_card_installments' );
			$installment_value           = $order->get_total() / $installments;
			$installment_value_formatted = Helpers::format_money( $installment_value );

			// translators: %d is the number of installments.
			$total_rows['payment_method']['value'] = sprintf( __( 'Cartão de crédito (%1$dx de %2$s)', 'pagbank-for-woocommerce' ), $installments, $installment_value_formatted );
		}

		return $total_rows;
	}

	/**
	 * Action links.
	 *
	 * @param array $links Action links.
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&filter=pagbank' ) ) . '">' . __( 'Configurações', 'pagbank-for-woocommerce' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Filter allowed gateways.
	 */
	public function filter_gateways_settings() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && isset( $_GET['tab'] ) && $_GET['page'] === 'wc-settings' && $_GET['tab'] === 'checkout' && isset( $_GET['filter'] ) && $_GET['filter'] === 'pagbank' ) {
			add_filter( 'woocommerce_payment_gateways', array( $this, 'filter_allowed_gateways' ) );
		}
	}

	/**
	 * Filter gateways that will be displayed in a custom settings page.
	 *
	 * @param  array $load_gateways Gateways.
	 *
	 * @return array
	 */
	public function filter_allowed_gateways( $load_gateways ) {
		$allowed_gateways = array(
			'PagBank_WooCommerce\Gateways\CreditCardPaymentGateway',
			'PagBank_WooCommerce\Gateways\BoletoPaymentGateway',
			'PagBank_WooCommerce\Gateways\PixPaymentGateway',
		);

		foreach ( $load_gateways as $key => $gateway ) {
			if ( ! in_array( $gateway, $allowed_gateways, true ) ) {
				unset( $load_gateways[ $key ] );
			}
		}

		return $load_gateways;
	}

	/**
	 * Check for plugin dependencies.
	 */
	public function check_for_plugin_dependencies() {
		if ( ! class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) {
			include dirname( PAGBANK_WOOCOMMERCE_FILE_PATH ) . '/src/templates/admin-html-notice-missing-brazilian-market-on-woocommerce.php';
		}
	}
}
