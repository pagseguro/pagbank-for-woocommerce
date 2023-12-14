<?php
/**
 * Application helper functions.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Format money to cents.
 *
 * @param float $value Value.
 *
 * @return int
 */
function format_money_cents( $value ) {
	return (int) ( $value * 100 );
}

/**
 * Format money from cents.
 *
 * @param int $value Value.
 *
 * @return float
 */
function format_money_from_cents( $value ) {
	return (float) ( $value / 100 );
}

/**
 * Format money to string.
 *
 * @param float $value Value.
 *
 * @return string
 */
function format_money( float $value ): string {
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
function encode_text( string $text ) {
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
function decode_text( string $text ) {
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	return base64_decode( $text );
}
