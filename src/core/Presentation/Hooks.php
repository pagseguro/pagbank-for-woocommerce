<?php
/**
 * Application hooks.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

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
		add_filter( 'woocommerce_payment_token_class', array( $this, 'filter_payment_token_class_name' ), 10, 2 );
		add_filter( 'woocommerce_payment_methods_types', array( $this, 'filter_payment_method_types' ), 10, 1 );
		add_filter( 'woocommerce_payment_methods_list_item', array( $this, 'filter_payment_methods_list_item' ), 10, 2 );
		add_filter( 'script_loader_tag', array( $this, 'add_type_attribute' ), 10, 2 );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'filter_woocommerce_get_order_item_totals' ), 10, 2 );
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
		$types['pagbank_cc'] = __( 'Credit card', 'pagbank-woocommerce' );

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
		$item['method']['brand'] = ( ! empty( $card_type ) ? ucfirst( $card_type ) : esc_html__( 'Credit card', 'pagbank-woocommerce' ) );
		$item['expires']         = $payment_token->get_expiry_month() . '/' . substr( $payment_token->get_expiry_year(), -2 );

		return $item;
	}

	/**
	 * Add type attribute to script tag.
	 *
	 * @param  string $tag    Script tag.
	 * @param  string $handle Script handle.
	 *
	 * @return string         Filtered script tag.
	 */
	public function add_type_attribute( $tag, $handle ) {
		$type = wp_scripts()->get_data( $handle, 'type' );

		if ( $type ) {
			$tag = str_replace( 'src', 'type="' . esc_attr( $type ) . '" src', $tag );
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
			$installment_value_formatted = format_money( $installment_value );

			// translators: %d is the number of installments.
			$total_rows['payment_method']['value'] = sprintf( __( 'Credit card (%1$dx of %2$s)', 'pagbank-woocommerce' ), $installments, $installment_value_formatted );
		}

		return $total_rows;
	}

}
