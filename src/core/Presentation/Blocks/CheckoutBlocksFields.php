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
use WC_Order;
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
		add_action( 'woocommerce_set_additional_field_value', array( $this, 'save_field_to_legacy_meta' ), 10, 4 );
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
	 *
	 * @return void
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
				'show_in_order_confirmation' => false,
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
					$filtered_value = preg_replace( '/[^0-9]/', '', $field_value );

					return Helpers::format_cpf_or_cnpj( $filtered_value );
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
				'show_in_order_confirmation' => false,
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
				'show_in_order_confirmation' => false,
				'required' => true,
			)
		);
	}

	/**
	 * Save field values to legacy meta keys for compatibility with existing code.
	 *
	 * This method maps the new checkout block fields to legacy meta keys used by
	 * the classic checkout and other parts of the plugin.
	 *
	 * @param string                        $key       Field key.
	 * @param mixed                         $value     Field value.
	 * @param string                        $group     Field group (billing, shipping, other).
	 * @param \WC_Order|\WC_Customer|object $wc_object WooCommerce object.
	 *
	 * @return void
	 */
	public function save_field_to_legacy_meta( $key, $value, $group, $wc_object ) {
		if ( ! ( $wc_object instanceof WC_Order ) ) {
			return;
		}

		$prefix = 'billing' === $group ? '_billing_' : '_shipping_';

		switch ( $key ) {
			case 'pagbank/tax-id':
				$digits = Helpers::filter_only_numbers( $value );

				if ( strlen( $digits ) === 11 ) {
					// CPF.
					$wc_object->update_meta_data( $prefix . 'persontype', '1' );
					$wc_object->update_meta_data( $prefix . 'cpf', Helpers::format_cpf( $digits ) );
					$wc_object->update_meta_data( $prefix . 'cnpj', '' );
				} elseif ( strlen( $digits ) === 14 ) {
					// CNPJ.
					$wc_object->update_meta_data( $prefix . 'persontype', '2' );
					$wc_object->update_meta_data( $prefix . 'cpf', '' );
					$wc_object->update_meta_data( $prefix . 'cnpj', Helpers::format_cnpj( $digits ) );
				}
				break;

			case 'pagbank/address-number':
				$wc_object->update_meta_data( $prefix . 'number', $value );
				break;

			case 'pagbank/neighborhood':
				$wc_object->update_meta_data( $prefix . 'neighborhood', $value );
				break;

			default:
				return;
		}

		$wc_object->save_meta_data();
	}
}
