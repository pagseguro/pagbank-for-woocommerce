<?php
/**
 * PHPStan bootstrap file.
 *
 * Defines constants and loads stubs needed for static analysis.
 *
 * @package PagBank_WooCommerce
 */

// Define plugin constants for PHPStan analysis.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/var/www/html/' );
}
define( 'PAGBANK_WOOCOMMERCE_FILE_PATH', __DIR__ . '/pagbank-for-woocommerce.php' );
define( 'PAGBANK_WOOCOMMERCE_VERSION', '2.0.0' );
define( 'PAGBANK_WOOCOMMERCE_TEMPLATES_PATH', __DIR__ . '/src/templates/' );
