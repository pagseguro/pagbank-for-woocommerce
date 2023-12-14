<?php
/**
 * Add support for PaymentToken.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Payment_Token;

/**
 * Payment token.
 */
class PaymentToken extends WC_Payment_Token {

	/**
	 * Token Type String.
	 *
	 * @var string
	 */
	protected $type = 'PagBank_CC';

	/**
	 * Stores Credit Card payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = array(
		'bin'                => '',
		'holder'             => '',
		'last4'              => '',
		'expiry_year'        => '',
		'expiry_month'       => '',
		'card_type'          => '',
		'connect_account_id' => '',
	);

	/**
	 * Get type to display to user.
	 *
	 * @since  2.6.0
	 * @param  string $deprecated Deprecated since WooCommerce 3.0.
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) {
		$display = sprintf(
			/* translators: 1: credit card type 2: last 4 digits 3: expiry month 4: expiry year */
			__( '%1$s com final %2$s (expira em %3$s/%4$s)', 'pagbank-for-woocommerce' ),
			wc_get_credit_card_type_label( $this->get_card_type() ),
			$this->get_last4(),
			$this->get_expiry_month(),
			substr( $this->get_expiry_year(), 2 )
		);
		return $display;
	}

	/**
	 * Hook prefix
	 *
	 * @since 3.0.0
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_pagbank_cc_get_';
	}

	/**
	 * Validate credit card payment tokens.
	 *
	 * These fields are required by all credit card payment tokens:
	 * expiry_month  - string Expiration date (MM) for the card
	 * expiry_year   - string Expiration date (YYYY) for the card
	 * last4         - string Last 4 digits of the card
	 * card_type     - string Card type (visa, mastercard, etc)
	 *
	 * @return boolean True if the passed data is valid
	 */
	public function validate() {
		if ( false === parent::validate() ) {
			return false;
		}

		if ( ! $this->get_last4( 'edit' ) ) {
			return false;
		}

		if ( ! $this->get_expiry_year( 'edit' ) ) {
			return false;
		}

		if ( ! $this->get_expiry_month( 'edit' ) ) {
			return false;
		}

		if ( ! $this->get_card_type( 'edit' ) ) {
			return false;
		}

		if ( ! $this->get_holder( 'edit' ) ) {
			return false;
		}

		if ( ! $this->get_bin( 'edit' ) || strlen( $this->get_bin( 'edit' ) ) !== 6 ) {
			return false;
		}

		if ( 4 !== strlen( $this->get_expiry_year( 'edit' ) ) ) {
			return false;
		}

		if ( 2 !== strlen( $this->get_expiry_month( 'edit' ) ) ) {
			return false;
		}

		return true;
	}

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

	/**
	 * Returns the card type (mastercard, visa, ...).
	 *
	 * @since  2.6.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Card type
	 */
	public function get_card_type( $context = 'view' ) {
		return $this->get_prop( 'card_type', $context );
	}

	/**
	 * Set the card type (mastercard, visa, ...).
	 *
	 * @param string $type Credit card type (mastercard, visa, ...).
	 */
	public function set_card_type( $type ) {
		$this->set_prop( 'card_type', $type );
	}

	/**
	 * Returns the card expiration year (YYYY).
	 *
	 * @since  2.6.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Expiration year
	 */
	public function get_expiry_year( $context = 'view' ) {
		return $this->get_prop( 'expiry_year', $context );
	}

	/**
	 * Set the expiration year for the card (YYYY format).
	 *
	 * @param string $year Credit card expiration year.
	 */
	public function set_expiry_year( $year ) {
		$this->set_prop( 'expiry_year', $year );
	}

	/**
	 * Returns the card expiration month (MM).
	 *
	 * @since  2.6.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Expiration month
	 */
	public function get_expiry_month( $context = 'view' ) {
		return $this->get_prop( 'expiry_month', $context );
	}

	/**
	 * Set the expiration month for the card (formats into MM format).
	 *
	 * @param string $month Credit card expiration month.
	 */
	public function set_expiry_month( $month ) {
		$this->set_prop( 'expiry_month', str_pad( $month, 2, '0', STR_PAD_LEFT ) );
	}

	/**
	 * Returns the last four digits.
	 *
	 * @since  2.6.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Last 4 digits
	 */
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 * @param string $last4 Credit card last four digits.
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}

	/**
	 * Returns the account connect id.
	 *
	 * @since  2.6.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Last 4 digits
	 */
	public function get_connect_account_id( $context = 'view' ) {
		return $this->get_prop( 'connect_account_id', $context );
	}

	/**
	 * Set the account connect id.
	 *
	 * @param string $connect_account_id Credit card account connect id.
	 */
	public function set_connect_account_id( $connect_account_id ) {
		$this->set_prop( 'connect_account_id', $connect_account_id );
	}
}
