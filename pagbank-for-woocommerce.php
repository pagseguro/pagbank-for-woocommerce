<?php
/**
 * Plugin Name: PagBank for WooCommerce
 * Plugin URI: https://github.com/pagseguro/pagbank-for-woocommerce
 * Description: Aceite pagamentos via cartão de crédito, boleto e Pix no checkout do WooCommerce através do PagBank.
 * Version: 2.0.0
 * Author: PagBank
 * Author URI: https://pagseguro.uol.com.br/
 * License: GPL-2.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce, woocommerce-extra-checkout-fields-for-brazil
 * WC requires at least: 3.9
 * WC tested up to: 10.4
 * Text Domain: pagbank-for-woocommerce
 *
 * @package PagBank_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use PagBank_WooCommerce\Marketplace\WcfmIntegration;
use PagBank_WooCommerce\Presentation\Blocks\BlocksPaymentMethods;
use PagBank_WooCommerce\Presentation\Blocks\CheckoutBlocksFields;
use PagBank_WooCommerce\Presentation\ConnectAjaxApi;
use PagBank_WooCommerce\Presentation\Helpers;
use PagBank_WooCommerce\Presentation\Hooks;
use PagBank_WooCommerce\Presentation\OrderStatusApi;
use PagBank_WooCommerce\Presentation\PaymentGateways;
use PagBank_WooCommerce\Presentation\SettingsApi;
use PagBank_WooCommerce\Presentation\WebhookHandler;

define( 'PAGBANK_WOOCOMMERCE_FILE_PATH', __FILE__ );
define( 'PAGBANK_WOOCOMMERCE_VERSION', '2.0.0' );
define( 'PAGBANK_WOOCOMMERCE_TEMPLATES_PATH', plugin_dir_path( PAGBANK_WOOCOMMERCE_FILE_PATH ) . 'src/templates/' );

add_action(
	'before_woocommerce_init',
	function (): void {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', PAGBANK_WOOCOMMERCE_FILE_PATH, true );
		}
	}
);

( function (): void {
	$autoload_filepath = __DIR__ . '/vendor/autoload.php';

	if ( file_exists( $autoload_filepath ) ) {
		require_once $autoload_filepath;
	}

	if ( ! Helpers::is_woocommerce_activated() ) {
		return;
	}

	PaymentGateways::get_instance();
	Hooks::get_instance();
	ConnectAjaxApi::get_instance();
	WebhookHandler::get_instance();
	OrderStatusApi::get_instance();
	SettingsApi::get_instance();
	CheckoutBlocksFields::get_instance();
	BlocksPaymentMethods::get_instance();

	if ( Helpers::is_wcfm_activated() ) {
		WcfmIntegration::get_instance();
	}
} )();
