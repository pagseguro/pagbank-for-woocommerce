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

if( ! defined( 'ABSPATH' ) ) {
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
		add_action( 'wcfm_vendor_end_settings_payment', [$this, 'payments_settings'], 100);
		add_filter( 'woocommerce_is_purchasable', [$this, 'disable_disconnected_users_product'], 10, 2 );
		add_filter( 'pagbank_credit_card_payment_data', [$this, 'credit_card_payment_data'], 10, 3 );
		add_filter( 'pagbank_pix_payment_data', [$this, 'pix_payment_data'], 10, 3 );
		add_filter( 'pagbank_boleto_payment_data', [$this, 'boleto_payment_data'], 10, 3 );
		add_action( 'wcfm_vendor_settings_update', [$this, 'save_vendor_settings'], 10, 2 );
		add_action( 'pagbank_order_completed', [$this, 'process_withdraw'], 10, 1 );
		add_filter( 'pagbank_should_process_order_refund', [$this, 'should_process_order_refund'], 10, 2 );
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
	 * @param int $user_id
	 *
	 * @return void
	 */
	public function payments_settings($user_id) {
		wc_get_template(
			'wcfm-payment-settings.php',
			array(
				'user_id' => $user_id,
				'account_id' => get_user_meta($user_id, 'pagbank_account_id', true),
			),
			'woocommerce/pagbank/',
			PAGBANK_WOOCOMMERCE_TEMPLATES_PATH
		);
	}

	/**
	 * Save the vendor settings in WCFM dashboard.
	 *
	 * @param int $user_id
	 * @param array $form
	 *
	 * @return void
	 */
	public function save_vendor_settings( $user_id, $form ) {
		if(isset($form['payment']['pagbank']['account_id'])) {
			update_user_meta($user_id, 'pagbank_account_id', sanitize_text_field($form['payment']['pagbank']['account_id']));
		}
	}

	/**
	 * Disable product purchase if the vendor is not connected to PagBank.
	 *
	 * @param bool $is_purchasable
	 * @param WC_Product $product
	 * @return bool
	 */
	public function disable_disconnected_users_product( bool $is_purchasable, WC_Product $product ) {
		if( ! $is_purchasable ) {
			return $is_purchasable;
		}

		$vendor_id = get_post_meta( '_wcfm_product_author', $product->get_id(), true );

		if( ! $vendor_id ) {
			return $is_purchasable;
		}

		$pagbank_account_id = get_user_meta( $vendor_id, 'pagbank_account_id', true );

		if( ! $pagbank_account_id ) {
			return false;
		}

		return $is_purchasable;
	}

	/**
	 * Get the split data for the order.
	 *
	 * @param WC_Order $order
	 * @param (CreditCardPaymentGateway|BoletoPaymentGateway|PixPaymentGateway) $gateway
	 * @return mixed
	 */
	private function get_splits_payment_data( WC_Order $order, $gateway ) {
		global $WCFMmp;

		if( ! $WCFMmp ) {
			return;
		}

		$receivers = [];
		$total_commission = 0;

        $vendor_wise_gross_sales = $WCFMmp->wcfmmp_commission->wcfmmp_split_pay_vendor_wise_gross_sales($order);

        foreach ($vendor_wise_gross_sales as $vendor_id => $gross_sales) {
            $vendor_comission = $WCFMmp->wcfmmp_commission->wcfmmp_calculate_vendor_order_commission($vendor_id, $order->get_id(), $order);

            if ($vendor_comission['commission_amount'] >= 0) {
				$receivers[] = [
					'account' => [
						'id' => get_user_meta($vendor_id, 'pagbank_account_id', true),
					],
					'amount' => [
						'value' => (int) ($vendor_comission['commission_amount'] * 100),
					],
				];

				$total_commission += (int) ($vendor_comission['commission_amount'] * 100);
            }
        }

		if( $total_commission === 0 ) {
			return;
		}

		$connect_data = $gateway->connect->get_data();
		$account_id = $connect_data['account_id'];

		$receivers[] = [
			'account' => [
				'id' => $account_id,
			],
			'amount' => [
				'value' => $order->get_total() * 100 - $total_commission,
			],
		];

		return [
			'method' => 'FIXED',
			'receivers' => $receivers,
		];
	}

	/**
	 * Add split data to the credit card payment data.
	 *
	 * @param mixed $data
	 * @param WC_Order $order
	 * @param CreditCardPaymentGateway $gateway
	 * @return mixed
	 */
	public function credit_card_payment_data( $data, WC_Order $order, CreditCardPaymentGateway $gateway ) {
		$splits = $this->get_splits_payment_data($order, $gateway);

		if( $splits ) {
			// $data['charges'][0]['splits'] = $splits;
		}

		return $data;
	}

	/**
	 * Add split data to the pix payment data.
	 *
	 * @param mixed $data
	 * @param WC_Order $order
	 * @param CreditCardPaymentGateway $gateway
	 * @return mixed
	 */
	public function pix_payment_data( $data, WC_Order $order, PixPaymentGateway $gateway) {
		$splits = $this->get_splits_payment_data($order, $gateway);

		if( $splits ) {
			// $data['qr_codes'][0]['splits'] = $splits;
		}

		return $data;
	}

	/**
	 * Add split data to the boleto payment data.
	 *
	 * @param mixed $data
	 * @param WC_Order $order
	 * @param BoletoPaymentGateway $gateway
	 * @return mixed
	 */
	public function boleto_payment_data( $data, WC_Order $order, BoletoPaymentGateway $gateway) {
		$splits = $this->get_splits_payment_data($order, $gateway);

		if( $splits ) {
			// $data['charges'][0]['splits'] = $splits;
		}

		return $data;
	}

	/**
	 * Process the withdrawal of the vendor commission.
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	public function process_withdraw( WC_Order $order ) {
		global $wpdb, $WCFMmp, $WCFM;

		$vendor_wise_gross_sales = $WCFMmp->wcfmmp_commission->wcfmmp_split_pay_vendor_wise_gross_sales($order);

		foreach ($vendor_wise_gross_sales as $vendor_id => $gross_sales) {
			$vendor_comission = $WCFMmp->wcfmmp_commission->wcfmmp_calculate_vendor_order_commission($vendor_id, $order->get_id(), $order);
			$commission_amount = $vendor_comission['commission_amount'];

			// Create vendor withdrawal Instance
            $commission_id_list = $wpdb->get_col($wpdb->prepare("SELECT ID FROM `{$wpdb->prefix}wcfm_marketplace_orders` WHERE order_id = %d AND vendor_id = %d", $order->get_id(), $vendor_id));

            $withdrawal_id = $WCFMmp->wcfmmp_withdraw->wcfmmp_withdrawal_processed(
				$vendor_id,
				$order->get_id(),
				implode(',', $commission_id_list),
				'pagbank',
				$gross_sales,
				$commission_amount,
				0,
				'pending',
				'by_pagbank',
				1
			);

            // Withdrawal Processing
            $WCFMmp->wcfmmp_withdraw->wcfmmp_withdraw_status_update_by_withdrawal($withdrawal_id, 'completed', __('PagBank', 'pagbank-for-woocommerce'));

            // Withdrawal Meta
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta($withdrawal_id, 'withdraw_amount', $commission_amount);
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta($withdrawal_id, 'currency', $order->get_currency());

            do_action('wcfmmp_withdrawal_request_approved', $withdrawal_id);
		}
	}

	/**
	 * Check if the order can be refunded.
	 *
	 * @param mixed $should_process_order_refund
	 * @param WC_Order $order
	 * @return mixed
	 */
	public function should_process_order_refund( $should_process_order_refund, WC_Order $order ) {
		global $WCFMmp;

		if( is_wp_error( $should_process_order_refund ) ) {
			return $should_process_order_refund;
		}

		if( ! $WCFMmp ) {
			return $should_process_order_refund;
		}

		$vendor_wise_gross_sales = $WCFMmp->wcfmmp_commission->wcfmmp_split_pay_vendor_wise_gross_sales($order);
		$total_commission = 0;

        foreach ($vendor_wise_gross_sales as $vendor_id => $gross_sales) {
            $vendor_comission = $WCFMmp->wcfmmp_commission->wcfmmp_calculate_vendor_order_commission($vendor_id, $order->get_id(), $order);

            if ($vendor_comission['commission_amount'] >= 0) {
				$total_commission += (int) ($vendor_comission['commission_amount'] * 100);
            }
        }

		if( $total_commission > 0 ) {
			return new WP_Error( 'error', __('Você não pode reembolsar um pedido que possui comissões de vendedores.', 'pagbank-for-woocommerce') );
		}

		return $should_process_order_refund;
	}


}
