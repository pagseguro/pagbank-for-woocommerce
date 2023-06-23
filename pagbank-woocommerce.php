<?php
/**
 * Plugin Name: PagBank WooCommerce
 * Plugin URI: https://github.com/pagseguro/pagbank-woocommerce
 * Description: Aceite pagamentos via cartão de crédito, boleto e Pix no checkout do WooCommerce através do PagBank.
 * Version: 1.0.0
 * Author: PagBank
 * Author URI: https://pagseguro.uol.com.br/
 * License: GPL-2.0
 * Requires PHP: 7.2
 * WC requires at least: 3.9
 * WC tested up to: 7.7
 * Text Domain: pagbank-woocommerce
 *
 * @package PagBank_WooCommerce
 */

use PagBank_WooCommerce\Presentation\ConnectAjaxApi;
use PagBank_WooCommerce\Presentation\Hooks;
use PagBank_WooCommerce\Presentation\PaymentGateways;
use PagBank_WooCommerce\Presentation\PaymentGatewaysFields;
use PagBank_WooCommerce\Presentation\WebhookHandler;

define( 'PAGBANK_WOOCOMMERCE_FILE_PATH', __FILE__ );
define( 'PAGBANK_WOOCOMMERCE_VERSION', '0.0.3' );
define( 'PAGBANK_WOOCOMMERCE_TEMPLATES_PATH', plugin_dir_path( PAGBANK_WOOCOMMERCE_FILE_PATH ) . 'src/templates/' );

( function () {
	$autoload_filepath = __DIR__ . '/vendor/autoload.php';

	if ( file_exists( $autoload_filepath ) ) {
		require_once $autoload_filepath;
	}

	PaymentGatewaysFields::get_instance();
	PaymentGateways::get_instance();
	Hooks::get_instance();
	ConnectAjaxApi::get_instance();
	WebhookHandler::get_instance();

} )();
