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
