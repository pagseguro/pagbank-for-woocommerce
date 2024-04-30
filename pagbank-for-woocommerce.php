<?php
/**
 * Plugin Name: PagBank for WooCommerce
 * Plugin URI: https://github.com/pagseguro/pagbank-for-woocommerce
 * Description: Aceite pagamentos via cartão de crédito, boleto e Pix no checkout do WooCommerce através do PagBank.
 * Version: 1.1.1
 * Author: PagBank
 * Author URI: https://pagseguro.uol.com.br/
 * License: GPL-2.0
 * Requires PHP: 7.2
 * WC requires at least: 3.9
 * WC tested up to: 8.6
 * Text Domain: pagbank-for-woocommerce
 *
 * @package PagBank_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use PagBank_WooCommerce\Presentation\ConnectAjaxApi;
use PagBank_WooCommerce\Presentation\Hooks;
use PagBank_WooCommerce\Presentation\PaymentGateways;
use PagBank_WooCommerce\Presentation\PaymentGatewaysFields;
use PagBank_WooCommerce\Presentation\WebhookHandler;

use function PagBank_WooCommerce\Presentation\is_woocommerce_activated;

define( 'PAGBANK_WOOCOMMERCE_FILE_PATH', __FILE__ );
define( 'PAGBANK_WOOCOMMERCE_VERSION', '1.1.1' );
define( 'PAGBANK_WOOCOMMERCE_TEMPLATES_PATH', plugin_dir_path( PAGBANK_WOOCOMMERCE_FILE_PATH ) . 'src/templates/' );

add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', PAGBANK_WOOCOMMERCE_FILE_PATH, true );
		}
	}
);

( function () {
	$autoload_filepath = __DIR__ . '/vendor/autoload.php';

	if ( file_exists( $autoload_filepath ) ) {
		require_once $autoload_filepath;
	}

	if ( ! is_woocommerce_activated() ) {
		return;
	}

	PaymentGatewaysFields::get_instance();
	PaymentGateways::get_instance();
	Hooks::get_instance();
	ConnectAjaxApi::get_instance();
	WebhookHandler::get_instance();

} )();
