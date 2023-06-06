<?php
/**
 * Main plugin file.
 *
 * @package PagBank_WooCommerce
 *
 * Plugin Name: PagBank WooCommerce
 * Description: Setup credit card payments with one-click buy, boleto and Pix on checkout.
 * Version: 0.0.2
 * Text Domain: pagbank-woocommerce
 * Domain Path: /languages
 */

use PagBank_WooCommerce\Presentation\ConnectAjaxApi;
use PagBank_WooCommerce\Presentation\Hooks;
use PagBank_WooCommerce\Presentation\PaymentGateways;
use PagBank_WooCommerce\Presentation\PaymentGatewaysFields;
use PagBank_WooCommerce\Presentation\WebhookHandler;

define( 'PAGBANK_WOOCOMMERCE_FILE_PATH', __FILE__ );
define( 'PAGBANK_WOOCOMMERCE_VERSION', '0.0.2' );
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
