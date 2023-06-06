<?php
/**
 * Payment token.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

use WC_Payment_Token_CC;

/**
 * Payment token.
 */
class PaymentToken extends WC_Payment_Token_CC {

	/**
	 * Card bin.
	 *
	 * @param string $bin The card bin.
	 */
	public function set_bin( $bin ) {
		$this->set_prop( 'bin', $bin );
	}

	/**
	 * Card bin.
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string Card bin.
	 */
	public function get_bin( $context = 'view' ) {
		return $this->get_prop( 'bin', $context );
	}

	/**
	 * Card holder.
	 *
	 * @param string $holder The card holder.
	 */
	public function set_holder( $holder ) {
		$this->set_prop( 'holder', $holder );
	}

	/**
	 * Card holder.
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string Card holder.
	 */
	public function get_holder( $context = 'view' ) {
		return $this->get_prop( 'holder', $context );
	}
}
