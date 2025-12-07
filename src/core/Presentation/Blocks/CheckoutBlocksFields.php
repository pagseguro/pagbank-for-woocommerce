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
	 * Register additional checkout fields for Blocks.
	 */
	public function register_additional_checkout_fields() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		// Person type (Pessoa Física / Pessoa Jurídica).
		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'pagbank/person-type',
				'label'    => __( 'Tipo de pessoa', 'pagbank-for-woocommerce' ),
				'location' => 'address',
				'type'     => 'select',
				'required' => true,
				'options'  => array(
					array(
						'value' => 'cpf',
						'label' => __( 'Pessoa Física', 'pagbank-for-woocommerce' ),
					),
					array(
						'value' => 'cnpj',
						'label' => __( 'Pessoa Jurídica', 'pagbank-for-woocommerce' ),
					),
				),
			)
		);

		// CPF field - shown only for Pessoa Física (value = 'cpf').
		woocommerce_register_additional_checkout_field(
			array(
				'id'                => 'pagbank/cpf',
				'label'             => __( 'CPF', 'pagbank-for-woocommerce' ),
				'location'          => 'address',
				'type'              => 'text',
				'required'          => array(
					'type'       => 'object',
					'properties' => array(
						'customer' => array(
							'properties' => array(
								'billing_address' => array(
									'properties' => array(
										'pagbank/person-type' => array(
											'const' => 'cpf',
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
								'billing_address' => array(
									'properties' => array(
										'pagbank/person-type' => array(
											'not' => array(
												'const' => 'cpf',
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
					if ( ! Helpers::is_valid_cpf( $field_value ) ) {
						return new WP_Error( 'invalid_cpf', __( 'CPF inválido. Verifique os dígitos informados.', 'pagbank-for-woocommerce' ) );
					}
				},
			)
		);

		// CNPJ field - shown only for Pessoa Jurídica (value = 'cnpj').
		woocommerce_register_additional_checkout_field(
			array(
				'id'                => 'pagbank/cnpj',
				'label'             => __( 'CNPJ', 'pagbank-for-woocommerce' ),
				'location'          => 'address',
				'type'              => 'text',
				'required'          => array(
					'type'       => 'object',
					'properties' => array(
						'customer' => array(
							'properties' => array(
								'billing_address' => array(
									'properties' => array(
										'pagbank/person-type' => array(
											'const' => 'cnpj',
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
								'billing_address' => array(
									'properties' => array(
										'pagbank/person-type' => array(
											'not' => array(
												'const' => 'cnpj',
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
					if ( ! Helpers::is_valid_cnpj( $field_value ) ) {
						return new WP_Error( 'invalid_cnpj', __( 'CNPJ inválido. Verifique os dígitos informados.', 'pagbank-for-woocommerce' ) );
					}
				},
			)
		);

		// Billing number.
		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'pagbank/address-number',
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
				'label'    => __( 'Bairro', 'pagbank-for-woocommerce' ),
				'location' => 'address',
				'type'     => 'text',
				'required' => true,
			)
		);
	}
}
