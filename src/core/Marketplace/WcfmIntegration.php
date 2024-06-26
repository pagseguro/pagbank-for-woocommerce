<?php
/**
 * WCFM Integration
 *
 * @package PagBank_WooCommerce\Marketplace
 */

namespace PagBank_WooCommerce\Marketplace;

use PagBank_WooCommerce\Gateways\BoletoPaymentGateway;
use PagBank_WooCommerce\Gateways\CreditCardPaymentGateway;
use PagBank_WooCommerce\Gateways\PixPaymentGateway;
use WC_Order;
use WC_Product;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCFM Integration
 */
class WcfmIntegration {

	/**
	 * Instance.
	 *
	 * @var WcfmIntegration
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'wcfm_vendor_end_settings_payment', array( $this, 'payments_settings' ), 100 );
		add_filter( 'woocommerce_is_purchasable', array( $this, 'disable_disconnected_users_product' ), 10, 2 );
		add_filter( 'pagbank_credit_card_payment_data', array( $this, 'credit_card_payment_data' ), 10, 3 );
		add_filter( 'pagbank_pix_payment_data', array( $this, 'pix_payment_data' ), 10, 3 );
		add_filter( 'pagbank_boleto_payment_data', array( $this, 'boleto_payment_data' ), 10, 3 );
		add_action( 'wcfm_vendor_settings_update', array( $this, 'save_vendor_settings' ), 10, 2 );
		add_action( 'pagbank_order_completed', array( $this, 'process_withdraw' ), 10, 1 );
		add_filter( 'pagbank_should_process_order_refund', array( $this, 'should_process_order_refund' ), 10, 2 );
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
	 * Display the vendor settings in WCFM dashboard.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return void
	 */
	public function payments_settings( $user_id ) {
		wc_get_template(
			'wcfm-payment-settings.php',
			array(
				'user_id'    => $user_id,
				'account_id' => get_user_meta( $user_id, 'pagbank_account_id', true ),
			),
			'woocommerce/pagbank/',
			PAGBANK_WOOCOMMERCE_TEMPLATES_PATH
		);
	}

	/**
	 * Save the vendor settings in WCFM dashboard.
	 *
	 * @param int   $user_id    The user ID.
	 * @param array $form       The form data.
	 *
	 * @return void
	 */
	public function save_vendor_settings( $user_id, $form ) {
		if ( isset( $form['payment']['pagbank']['account_id'] ) ) {
			update_user_meta( $user_id, 'pagbank_account_id', sanitize_text_field( $form['payment']['pagbank']['account_id'] ) );
		}
	}

	/**
	 * Disable product purchase if the vendor is not connected to PagBank.
	 *
	 * @param bool       $is_purchasable Whether the product is purchasable.
	 * @param WC_Product $product     The product object.
	 * @return bool
	 */
	public function disable_disconnected_users_product( bool $is_purchasable, WC_Product $product ) {
		if ( ! $is_purchasable ) {
			return $is_purchasable;
		}

		$vendor_id = get_post_meta( '_wcfm_product_author', $product->get_id(), true );

		if ( ! $vendor_id ) {
			return $is_purchasable;
		}

		$pagbank_account_id = get_user_meta( $vendor_id, 'pagbank_account_id', true );

		if ( ! $pagbank_account_id ) {
			return false;
		}

		return $is_purchasable;
	}

	/**
	 * Get the split data for the order.
	 *
	 * @param WC_Order                                                          $order The order object.
	 * @param (CreditCardPaymentGateway|BoletoPaymentGateway|PixPaymentGateway) $gateway The gateway class.
	 * @return mixed
	 */
	private function get_splits_payment_data( WC_Order $order, $gateway ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
		global $WCFMmp;

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
		if ( ! $WCFMmp ) {
			return;
		}

		$receivers        = array();
		$total_commission = 0;

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
		$vendor_wise_gross_sales = $WCFMmp->wcfmmp_commission->wcfmmp_split_pay_vendor_wise_gross_sales( $order );

		if ( count( $vendor_wise_gross_sales ) === 0 ) {
			return;
		}

		foreach ( $vendor_wise_gross_sales as $vendor_id => $gross_sales ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
			$vendor_comission = $WCFMmp->wcfmmp_commission->wcfmmp_calculate_vendor_order_commission( $vendor_id, $order->get_id(), $order );

			if ( $vendor_comission['commission_amount'] >= 0 ) {
				$receivers[] = array(
					'account' => array(
						'id' => get_user_meta( $vendor_id, 'pagbank_account_id', true ),
					),
					'amount'  => array(
						'value' => (int) ( $vendor_comission['commission_amount'] * 100 ),
					),
				);

				$total_commission += (int) ( $vendor_comission['commission_amount'] * 100 );
			}
		}

		if ( $total_commission === 0 ) {
			return;
		}

		$connect_data = $gateway->connect->get_data();
		$account_id   = $connect_data['account_id'];

		$receivers[] = array(
			'account' => array(
				'id' => $account_id,
			),
			'amount'  => array(
				'value' => $order->get_total() * 100 - $total_commission,
			),
		);

		return array(
			'method'    => 'FIXED',
			'receivers' => $receivers,
		);
	}

	/**
	 * Add split data to the credit card payment data.
	 *
	 * @param mixed                    $data  The order data.
	 * @param WC_Order                 $order The order object.
	 * @param CreditCardPaymentGateway $gateway The credit card gateway class.
	 * @return mixed
	 */
	public function credit_card_payment_data( $data, WC_Order $order, CreditCardPaymentGateway $gateway ) {
		$splits = $this->get_splits_payment_data( $order, $gateway );

		if ( $splits ) {
			$data['charges'][0]['splits'] = $splits;
		}

		return $data;
	}

	/**
	 * Add split data to the pix payment data.
	 *
	 * @param mixed             $data  The order data.
	 * @param WC_Order          $order   The order object.
	 * @param PixPaymentGateway $gateway The Pix gateway class.
	 * @return mixed
	 */
	public function pix_payment_data( $data, WC_Order $order, PixPaymentGateway $gateway ) {
		$splits = $this->get_splits_payment_data( $order, $gateway );

		if ( $splits ) {
			$data['qr_codes'][0]['splits'] = $splits;
		}

		return $data;
	}

	/**
	 * Add split data to the boleto payment data.
	 *
	 * @param mixed                $data    The order data.
	 * @param WC_Order             $order   The order object.
	 * @param BoletoPaymentGateway $gateway The Boleto gateway class.
	 * @return mixed
	 */
	public function boleto_payment_data( $data, WC_Order $order, BoletoPaymentGateway $gateway ) {
		$splits = $this->get_splits_payment_data( $order, $gateway );

		if ( $splits ) {
			$data['charges'][0]['splits'] = $splits;
		}

		return $data;
	}

	/**
	 * Process the withdrawal of the vendor commission.
	 *
	 * @param WC_Order $order The order that was completed.
	 * @return void
	 */
	public function process_withdraw( WC_Order $order ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
		global $wpdb, $WCFMmp;

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
		$vendor_wise_gross_sales = $WCFMmp->wcfmmp_commission->wcfmmp_split_pay_vendor_wise_gross_sales( $order );

		foreach ( $vendor_wise_gross_sales as $vendor_id => $gross_sales ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
			$vendor_comission  = $WCFMmp->wcfmmp_commission->wcfmmp_calculate_vendor_order_commission( $vendor_id, $order->get_id(), $order );
			$commission_amount = $vendor_comission['commission_amount'];

			// Create vendor withdrawal Instance.
			$commission_id_list = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM `{$wpdb->prefix}wcfm_marketplace_orders` WHERE order_id = %d AND vendor_id = %d", $order->get_id(), $vendor_id ) );

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
			$withdrawal_id = $WCFMmp->wcfmmp_withdraw->wcfmmp_withdrawal_processed(
				$vendor_id,
				$order->get_id(),
				implode( ',', $commission_id_list ),
				'pagbank',
				$gross_sales,
				$commission_amount,
				0,
				'pending',
				'by_pagbank',
				1
			);

			// Withdrawal Processing.
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
			$WCFMmp->wcfmmp_withdraw->wcfmmp_withdraw_status_update_by_withdrawal( $withdrawal_id, 'completed', __( 'PagBank', 'pagbank-for-woocommerce' ) );

			// Withdrawal Meta.
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
			$WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'withdraw_amount', $commission_amount );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
			$WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'currency', $order->get_currency() );

			do_action( 'wcfmmp_withdrawal_request_approved', $withdrawal_id );
		}
	}

	/**
	 * Check if the order can be refunded.
	 *
	 * @param mixed    $should_process_order_refund The result of the previous checks.
	 * @param WC_Order $order                       The order to be refunded.
	 * @return mixed
	 */
	public function should_process_order_refund( $should_process_order_refund, WC_Order $order ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
		global $WCFMmp;

		if ( is_wp_error( $should_process_order_refund ) ) {
			return $should_process_order_refund;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
		if ( ! $WCFMmp ) {
			return $should_process_order_refund;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
		$vendor_wise_gross_sales = $WCFMmp->wcfmmp_commission->wcfmmp_split_pay_vendor_wise_gross_sales( $order );
		$total_commission        = 0;

		foreach ( $vendor_wise_gross_sales as $vendor_id => $gross_sales ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- ignore for $WCFMmp
			$vendor_comission = $WCFMmp->wcfmmp_commission->wcfmmp_calculate_vendor_order_commission( $vendor_id, $order->get_id(), $order );

			if ( $vendor_comission['commission_amount'] >= 0 ) {
				$total_commission += (int) ( $vendor_comission['commission_amount'] * 100 );
			}
		}

		if ( $total_commission > 0 ) {
			return new WP_Error( 'error', __( 'Você não pode reembolsar um pedido que possui comissões de vendedores.', 'pagbank-for-woocommerce' ) );
		}

		return $should_process_order_refund;
	}
}
