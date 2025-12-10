<?php
/**
 * Registers additional checkout fields for WooCommerce Blocks.
 *
 * These fields are required by PagBank API (CPF/CNPJ, number, neighborhood).
 *
 * @package PagBank_WooCommerce\Presentation\Blocks
 */

namespace PagBank_WooCommerce\Presentation\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PagBank_WooCommerce\Presentation\Helpers;
use WP_Error;

/**
 * Class CheckoutBlocksFields.
 */
class CheckoutBlocksFields {

	/**
	 * Instance.
	 *
	 * @var CheckoutBlocksFields
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_init', array( $this, 'register_additional_checkout_fields' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Get instance.
	 *
	 * @return CheckoutBlocksFields
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Enqueue scripts and styles for checkout blocks fields.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_register_style(
			'pagbank-checkout-blocks-fields',
			plugins_url( 'dist/styles/blocks/checkout-fields.css', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array(),
			PAGBANK_WOOCOMMERCE_VERSION,
			'all'
		);

		wp_enqueue_style( 'pagbank-checkout-blocks-fields' );
	}

	/**
	 * Register additional checkout fields for Blocks.
	 */
	public function register_additional_checkout_fields() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		// CPF/CNPJ field - shown only for Brazil.
		woocommerce_register_additional_checkout_field(
			array(
				'id'                => 'pagbank/tax-id',
				'index'             => 2,
				'label'             => __( 'CPF/CNPJ', 'pagbank-for-woocommerce' ),
				'location'          => 'address',
				'type'              => 'text',
				'required'          => array(
					'type'       => 'object',
					'properties' => array(
						'customer' => array(
							'properties' => array(
								'address' => array(
									'properties' => array(
										'country' => array(
											'const' => 'BR',
										),
									),
								),
							),
						),
					),
				),
				'hidden'            => array(
					'type'       => 'object',
					'properties' => array(
						'customer' => array(
							'properties' => array(
								'address' => array(
									'properties' => array(
										'country' => array(
											'not' => array(
												'const' => 'BR',
											),
										),
									),
								),
							),
						),
					),
				),
				'sanitize_callback' => function ( $field_value ) {
					return preg_replace( '/[^0-9]/', '', $field_value );
				},
				'validate_callback' => function ( $field_value ) {
					$digits = preg_replace( '/[^0-9]/', '', $field_value );

					if ( strlen( $digits ) === 11 ) {
						if ( ! Helpers::is_valid_cpf( $digits ) ) {
							return new WP_Error( 'invalid_cpf', __( 'CPF inválido. Verifique os dígitos informados.', 'pagbank-for-woocommerce' ) );
						}
					} elseif ( strlen( $digits ) === 14 ) {
						if ( ! Helpers::is_valid_cnpj( $digits ) ) {
							return new WP_Error( 'invalid_cnpj', __( 'CNPJ inválido. Verifique os dígitos informados.', 'pagbank-for-woocommerce' ) );
						}
					} else {
						return new WP_Error( 'invalid_tax_id', __( 'CPF/CNPJ inválido. Informe 11 dígitos para CPF ou 14 para CNPJ.', 'pagbank-for-woocommerce' ) );
					}
				},
			)
		);

		// Billing number.
		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'pagbank/address-number',
				'index'    => 41,
				'label'    => __( 'Número', 'pagbank-for-woocommerce' ),
				'location' => 'address',
				'type'     => 'text',
				'required' => true,
			)
		);

		// Billing neighborhood.
		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'pagbank/neighborhood',
				'index'    => 42,
				'label'    => __( 'Bairro', 'pagbank-for-woocommerce' ),
				'location' => 'address',
				'type'     => 'text',
				'required' => true,
			)
		);
	}
}
