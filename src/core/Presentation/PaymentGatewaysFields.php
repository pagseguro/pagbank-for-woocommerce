<?php
/**
 * Add support for custom fields to WooCommerce Settings API:
 * - currency
 * - pagbank_connect
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PaymentGatewaysFields.
 */
class PaymentGatewaysFields {

	/**
	 * Instance.
	 *
	 * @var PaymentGatewaysFields
	 */
	private static $instance = null;

	/**
	 * Init.
	 */
	public function __construct() {
		add_filter( 'woocommerce_generate_currency_html', array( $this, 'generate_currency_html' ), 10, 4 );
		add_filter( 'woocommerce_generate_pagbank_connect_html', array( $this, 'generate_pagbank_connect_html' ), 10, 4 );
	}

	/**
	 * Get instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Generate currency field HTML.
	 *
	 * @param string $field_html The markup of the field being generated (initiated as an empty string).
	 * @param string $key The key of the field.
	 * @param array  $data The attributes of the field as an associative array.
	 * @param object $wc_settings The current WC_Settings_API object.
	 *
	 * @return string
	 */
	public function generate_currency_html( $field_html, $key, $data, $wc_settings ) {
		if ( ! in_array( $wc_settings->id, PaymentGateways::$gateway_ids, true ) ) {
			return $field_html;
		}

		$data['type']              = 'text';
		$data['custom_attributes'] = wp_parse_args(
			array(
				'data-format-currency' => '',
			),
			$data['custom_attributes']
		);

		return $wc_settings->generate_text_html( $key, $data );
	}

	/**
	 * Generate PagBank field HTML.
	 *
	 * @param string $field_html The markup of the field being generated (initiated as an empty string).
	 * @param string $key The key of the field.
	 * @param array  $data The attributes of the field as an associative array.
	 * @param object $wc_settings The current WC_Settings_API object.
	 *
	 * @return string
	 */
	public function generate_pagbank_connect_html( $field_html, $key, $data, $wc_settings ) {
		if ( ! in_array( $wc_settings->id, PaymentGateways::$gateway_ids, true ) ) {
			return $field_html;
		}

		$field_key = $wc_settings->get_field_key( $key );
		$defaults  = array(
			'title'              => '',
			'disabled'           => false,
			'class'              => '',
			'css'                => '',
			'placeholder'        => '',
			'type'               => 'pagbank_connect',
			'desc_tip'           => false,
			'description'        => '',
			'environment_select' => 'environment',
			'custom_attributes'  => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();

		wc_get_template(
			'html-pagbank-connect.php',
			array(
				'gateway'     => $this,
				'field_html'  => $field_html,
				'field_key'   => $field_key,
				'data'        => $data,
				'wc_settings' => $wc_settings,
			),
			'woocommerce/pagbank/',
			PAGBANK_WOOCOMMERCE_TEMPLATES_PATH
		);

		return ob_get_clean();
	}
}
