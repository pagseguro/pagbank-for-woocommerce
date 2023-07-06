<?php
/**
 * Application helper functions.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

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
