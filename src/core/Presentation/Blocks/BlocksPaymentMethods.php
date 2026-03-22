<?php
/**
 * Registers PagBank payment methods for WooCommerce Checkout Blocks.
 *
 * @package PagBank_WooCommerce\Presentation\Blocks
 */

namespace PagBank_WooCommerce\Presentation\Blocks;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use PagBank_WooCommerce\Presentation\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BlocksPaymentMethods.
 */
class BlocksPaymentMethods {

	/**
	 * Instance.
	 */
	private static ?BlocksPaymentMethods $instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_payment_methods' ) );
	}

	/**
	 * Get instance.
	 */
	public static function get_instance(): BlocksPaymentMethods {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register payment methods for WooCommerce Blocks.
	 */
	public function register_payment_methods(): void {
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( PaymentMethodRegistry $payment_method_registry ): void {
				$payment_method_registry->register( new PixBlocksSupport() );
				$payment_method_registry->register( new BoletoBlocksSupport() );
				$payment_method_registry->register( new CreditCardBlocksSupport() );
				$payment_method_registry->register( new DebitCardBlocksSupport() );
				$payment_method_registry->register( new PayWithPagBankBlocksSupport() );

				if ( Helpers::get_constant_value( 'PAGBANK_FEATURE_FLAG_GOOGLE_PAY_ENABLED', false ) ) {
					$payment_method_registry->register( new GooglePayBlocksSupport() );
				}

				if ( Helpers::get_constant_value( 'PAGBANK_FEATURE_FLAG_APPLE_PAY_ENABLED', false ) ) {
					$payment_method_registry->register( new ApplePayBlocksSupport() );
				}

				$payment_method_registry->register( new CheckoutBlocksSupport() );
			}
		);
	}
}
