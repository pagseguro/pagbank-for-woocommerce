<?php
/**
 * Application helper public static functions.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Helpers.
 */
class Helpers {

	/**
	 * Format money to cents.
	 *
	 * @param float $value Value.
	 *
	 * @return int
	 */
	public static function format_money_cents( $value ) {
		return (int) ( $value * 100 );
	}

	/**
	 * Format money from cents.
	 *
	 * @param int $value Value.
	 *
	 * @return float
	 */
	public static function format_money_from_cents( $value ) {
		return (float) ( $value / 100 );
	}

	/**
	 * Format money to string.
	 *
	 * @param float $value Value.
	 *
	 * @return string
	 */
	public static function format_money( float $value ): string {
		$currency_symbol    = get_woocommerce_currency_symbol();
		$decimal_separator  = wc_get_price_decimal_separator();
		$thousand_separator = wc_get_price_thousand_separator();
		$decimals           = wc_get_price_decimals();
		$price_format       = get_woocommerce_price_format();

		$price = sprintf( $price_format, $currency_symbol, number_format( $value, $decimals, $decimal_separator, $thousand_separator ) );

		return html_entity_decode( $price );
	}

	/**
	 * Base64 encode text.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	public static function encode_text( string $text ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $text );
	}

	/**
	 * Base64 decode text.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	public static function decode_text( string $text ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return base64_decode( $text );
	}

	/**
	 * Sanitize checkout field.
	 *
	 * @param string $html The HTML content.
	 *
	 * @return string Sanitized HTML content.
	 */
	public static function sanitize_checkout_field( string $html ) {
		return wp_kses(
			html_entity_decode( $html ?? '' ),
			array(
				'fieldset' => array(
					'id'    => array(),
					'class' => array(),
				),
				'span'     => array(
					'id' => array(),
				),
				'p'        => array(
					'class' => array(),
				),
				'input'    => array(
					'id'             => array(),
					'name'           => array(),
					'class'          => array(),
					'autocomplete'   => array(),
					'autocorrect'    => array(),
					'autocapitalize' => array(),
					'spellcheck'     => array(),
					'type'           => array(),
					'style'          => array(),
				),
				'label'    => array(
					'for' => array(),
				),
			)
		);
	}

	/**
	 * Check if WooCommerce is activated.
	 *
	 * @return bool If WooCommerce is activated.
	 */
	public static function is_woocommerce_activated() {
		return class_exists( 'WooCommerce' ) || in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
	}

	/**
	 * Check if WCFM is activated.
	 *
	 * @return bool If WCFM is activated.
	 */
	public static function is_wcfm_activated() {
		return class_exists( 'WCFM' ) || in_array( 'wc-frontend-manager/wc_frontend_manager.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
	}

}
