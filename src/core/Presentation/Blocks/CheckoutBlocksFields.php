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

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields as WooCheckoutFields;
use Automattic\WooCommerce\Blocks\Package;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use PagBank_WooCommerce\Presentation\Helpers;
use PagBank_WooCommerce\Presentation\LegacyCheckoutFields;
use WC_Order;
use WP_Error;

/**
 * Class CheckoutBlocksFields.
 */
class CheckoutBlocksFields {

	/**
	 * Instance.
	 */
	private static ?CheckoutBlocksFields $instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_init', array( $this, 'register_additional_checkout_fields' ) );
		// Priority 1000: after any plugin that rewrites the BR locale (the
		// Brazilian Market plugin replaces it with a postcode-only entry).
		add_filter( 'woocommerce_get_country_locale', array( $this, 'pin_brazil_locale_priorities' ), 1000 );
		add_action( 'wp', array( $this, 'maybe_set_default_persontype' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'woocommerce_set_additional_field_value', array( $this, 'save_field_to_legacy_meta' ), 10, 4 );
	}

	/**
	 * Get instance.
	 */
	public static function get_instance(): CheckoutBlocksFields {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Enqueue scripts and styles for checkout blocks fields.
	 */
	public function enqueue_scripts(): void {
		// Version with the bundle mtime so browsers pick up new builds even
		// when the plugin version has not changed (e.g. during development).
		$style_path    = plugin_dir_path( PAGBANK_WOOCOMMERCE_FILE_PATH ) . 'dist/styles/blocks/checkout-fields.css';
		$style_version = PAGBANK_WOOCOMMERCE_VERSION . ( file_exists( $style_path ) ? '.' . filemtime( $style_path ) : '' );

		wp_register_style(
			'pagbank-checkout-blocks-fields',
			plugins_url( 'dist/styles/blocks/checkout-fields.css', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array(),
			$style_version,
			'all'
		);

		wp_enqueue_style( 'pagbank-checkout-blocks-fields' );

		// Input masks for the pagbank/* fields on the Blocks checkout. Skipped
		// when the fields themselves are not registered (LinkNacional gating).
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || LegacyCheckoutFields::linknacional_provides_document_fields() ) {
			return;
		}

		$script_path    = plugin_dir_path( PAGBANK_WOOCOMMERCE_FILE_PATH ) . 'dist/public/blocks/checkout-fields.js';
		$script_version = PAGBANK_WOOCOMMERCE_VERSION . ( file_exists( $script_path ) ? '.' . filemtime( $script_path ) : '' );

		wp_enqueue_script(
			'pagbank-blocks-checkout-fields',
			plugins_url( 'dist/public/blocks/checkout-fields.js', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array(),
			$script_version,
			true
		);

		wp_scripts()->add_data( 'pagbank-blocks-checkout-fields', 'pagbank_script', true );

		$config = array(
			'alphanumeric_cnpj' => (bool) Helpers::get_constant_value( 'PAGBANK_FEATURE_FLAG_ALPHANUMERIC_CNPJ_ENABLED', false ),
		);

		wp_add_inline_script(
			'pagbank-blocks-checkout-fields',
			'window.PagBankBlocksCheckoutFieldsConfig = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}

	/**
	 * Pin the BR locale priorities of the core address fields.
	 *
	 * The Blocks address form sorts fields by the defaultFields indexes
	 * (country 1, first_name 10, ..., address_1 40) overridden by the
	 * country locale (country 40, address_1 50, ...). Plugins can rewrite
	 * the BR locale — the Brazilian Market plugin replaces it with a
	 * postcode-only entry, which silently switches the whole scale and
	 * scrambles our field indexes. Filling the missing anchors keeps the
	 * scale stable in every scenario; existing overrides (e.g. the BM
	 * postcode at 45) are preserved.
	 *
	 * @param array|mixed $locale Country locales.
	 *
	 * @return array|mixed
	 */
	public function pin_brazil_locale_priorities( $locale ) {
		if ( ! is_array( $locale ) ) {
			return $locale;
		}

		$anchors = array(
			'first_name' => 10,
			'last_name'  => 20,
			'company'    => 30,
			'country'    => 40,
			'address_1'  => 50,
			'address_2'  => 60,
			'city'       => 70,
			'state'      => 80,
			'postcode'   => 90,
			'phone'      => 100,
		);

		foreach ( $anchors as $key => $priority ) {
			if ( ! isset( $locale['BR'][ $key ]['priority'] ) ) {
				$locale['BR'][ $key ]['priority'] = $priority;
			}
		}

		return $locale;
	}

	/**
	 * Register additional checkout fields for Blocks.
	 */
	public function register_additional_checkout_fields(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		// The LinkNacional plugin registers its own Store API checkout fields
		// (document, number, neighborhood); skip ours to avoid duplicates.
		// The Brazilian Market plugin has no Blocks support, so its presence
		// does not gate this registration.
		if ( LegacyCheckoutFields::linknacional_provides_document_fields() ) {
			return;
		}

		// Field indexes are relative to the BR locale scale pinned by
		// pin_brazil_locale_priorities() (first_name 10, last_name 20,
		// company 30, country 40, address_1 50, address_2 60, city 70,
		// state 80, postcode 90, phone 100), which overrides the raw
		// defaultFields indexes and is stable regardless of other plugins.
		// Person type selector - shown only for Brazil. Mirrors the classic
		// checkout experience: Pessoa física shows the CPF field; Pessoa
		// jurídica shows the CNPJ and Razão Social fields. Rendered right
		// after the country field, which drives its visibility.
		woocommerce_register_additional_checkout_field(
			array(
				'id'                         => 'pagbank/persontype',
				'index'                      => 41,
				'label'                      => __( 'Tipo de pessoa', 'pagbank-for-woocommerce' ),
				// The field is only visible when it is effectively required
				// (Brazil), so never show the "(optional)" suffix.
				'optionalLabel'              => __( 'Tipo de pessoa', 'pagbank-for-woocommerce' ),
				'location'                   => 'address',
				'type'                       => 'select',
				'show_in_order_confirmation' => false,
				'options'                    => array(
					array(
						'value' => '1',
						'label' => __( 'Pessoa física', 'pagbank-for-woocommerce' ),
					),
					array(
						'value' => '2',
						'label' => __( 'Pessoa jurídica', 'pagbank-for-woocommerce' ),
					),
				),
				'required'                   => self::rule_country_is_brazil(),
				'hidden'                     => self::rule_country_is_not_brazil(),
			)
		);

		// CPF - visible for Brazil while the person type is not legal person
		// (an empty selection defaults to Pessoa física, like the classic
		// checkout). Hidden fields are never required, so requiring for
		// Brazil is enough.
		woocommerce_register_additional_checkout_field(
			array(
				'id'                         => 'pagbank/cpf',
				'index'                      => 42,
				'label'                      => __( 'CPF', 'pagbank-for-woocommerce' ),
				'optionalLabel'              => __( 'CPF', 'pagbank-for-woocommerce' ),
				'location'                   => 'address',
				'type'                       => 'text',
				'show_in_order_confirmation' => false,
				'required'                   => self::rule_country_is_brazil(),
				'hidden'                     => array(
					'anyOf' => array(
						self::rule_country_is_not_brazil(),
						self::rule_persontype_is_legal_person(),
					),
				),
				'sanitize_callback'          => function ( $field_value ) {
					$value = (string) $field_value;

					return Helpers::is_valid_cpf( $value ) ? Helpers::format_cpf( $value ) : $field_value;
				},
				'validate_callback'          => function ( $field_value ) {
					$value = (string) $field_value;

					if ( '' !== $value && ! Helpers::is_valid_cpf( $value ) ) {
						return new WP_Error( 'invalid_cpf', __( 'CPF inválido. Verifique os dígitos informados.', 'pagbank-for-woocommerce' ) );
					}
				},
			)
		);

		// CNPJ - visible only for Brazil legal persons. Accepts the new
		// alphanumeric format when the feature flag is enabled.
		woocommerce_register_additional_checkout_field(
			array(
				'id'                         => 'pagbank/cnpj',
				'index'                      => 43,
				'label'                      => __( 'CNPJ', 'pagbank-for-woocommerce' ),
				'optionalLabel'              => __( 'CNPJ', 'pagbank-for-woocommerce' ),
				'location'                   => 'address',
				'type'                       => 'text',
				'show_in_order_confirmation' => false,
				'required'                   => self::rule_country_is_brazil(),
				'hidden'                     => array(
					'anyOf' => array(
						self::rule_country_is_not_brazil(),
						self::rule_persontype_is_not_legal_person(),
					),
				),
				'sanitize_callback'          => function ( $field_value ) {
					$value = (string) $field_value;

					return Helpers::is_valid_cnpj( $value ) ? Helpers::format_cnpj( $value ) : $field_value;
				},
				'validate_callback'          => function ( $field_value ) {
					$value = (string) $field_value;

					if ( '' !== $value && ! Helpers::is_valid_cnpj( $value ) ) {
						return new WP_Error( 'invalid_cnpj', __( 'CNPJ inválido. Verifique os dígitos informados.', 'pagbank-for-woocommerce' ) );
					}
				},
			)
		);

		// Razão Social - only when the store hides the core company field
		// (otherwise the Blocks checkout already offers it). Visible only for
		// Brazil legal persons.
		if ( 'hidden' === get_option( 'woocommerce_checkout_company_field', 'optional' ) ) {
			woocommerce_register_additional_checkout_field(
				array(
					'id'                         => 'pagbank/company',
					'index'                      => 44,
					'label'                      => __( 'Razão Social', 'pagbank-for-woocommerce' ),
					'optionalLabel'              => __( 'Razão Social', 'pagbank-for-woocommerce' ),
					'location'                   => 'address',
					'type'                       => 'text',
					'show_in_order_confirmation' => false,
					'required'                   => self::rule_country_is_brazil(),
					'hidden'                     => array(
						'anyOf' => array(
							self::rule_country_is_not_brazil(),
							self::rule_persontype_is_not_legal_person(),
						),
					),
				)
			);
		}

		// Billing number - between address_1 (50) and address_2 (60).
		woocommerce_register_additional_checkout_field(
			array(
				'id'                         => 'pagbank/address-number',
				'index'                      => 51,
				'label'                      => __( 'Número', 'pagbank-for-woocommerce' ),
				'location'                   => 'address',
				'type'                       => 'text',
				'show_in_order_confirmation' => false,
				'required'                   => true,
			)
		);

		// Billing neighborhood.
		woocommerce_register_additional_checkout_field(
			array(
				'id'                         => 'pagbank/neighborhood',
				'index'                      => 52,
				'label'                      => __( 'Bairro', 'pagbank-for-woocommerce' ),
				'location'                   => 'address',
				'type'                       => 'text',
				'show_in_order_confirmation' => false,
				'required'                   => true,
			)
		);

		// Cellphone - required, so it comes before the optional core phone (100).
		woocommerce_register_additional_checkout_field(
			array(
				'id'                         => 'pagbank/cellphone',
				'index'                      => 99,
				'label'                      => __( 'Celular', 'pagbank-for-woocommerce' ),
				'location'                   => 'address',
				'type'                       => 'text',
				'show_in_order_confirmation' => false,
				'required'                   => true,
				'sanitize_callback'          => function ( $field_value ) {
					$phone_util = PhoneNumberUtil::getInstance();

					try {
						$phone_number = $phone_util->parse( $field_value, 'BR' );

						return $phone_util->format( $phone_number, PhoneNumberFormat::INTERNATIONAL );
					} catch ( NumberParseException $e ) {
						return $field_value;
					}
				},
				'validate_callback'          => function ( $field_value ) {
					$phone_util = PhoneNumberUtil::getInstance();

					try {
						$phone_number = $phone_util->parse( $field_value, 'BR' );

						if ( ! $phone_util->isValidNumber( $phone_number ) ) {
							return new WP_Error( 'invalid_cellphone', __( 'Número de celular inválido.', 'pagbank-for-woocommerce' ) );
						}
					} catch ( NumberParseException $e ) {
						return new WP_Error( 'invalid_cellphone', __( 'Número de celular inválido.', 'pagbank-for-woocommerce' ) );
					}
				},
			)
		);
	}

	/**
	 * Default the person type to Pessoa física on the checkout page.
	 *
	 * The additional checkout fields API has no default-value option: the
	 * select hydrates from the customer's stored value, which is empty for
	 * new customers. Persist '1' (individual) when empty so the selector
	 * starts as Pessoa física, matching the classic checkout behavior. Runs
	 * before the Blocks hydration reads the customer object.
	 */
	public function maybe_set_default_persontype(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! function_exists( 'WC' ) || empty( WC()->customer ) ) {
			return;
		}

		// The document fields are not registered when the LinkNacional plugin
		// provides its own (see register_additional_checkout_fields).
		if ( LegacyCheckoutFields::linknacional_provides_document_fields() ) {
			return;
		}

		if ( ! class_exists( Package::class ) ) {
			return;
		}

		try {
			$checkout_fields = Package::container()->get( WooCheckoutFields::class );

			foreach ( array( 'billing', 'shipping' ) as $group ) {
				$current = $checkout_fields->get_field_from_object( 'pagbank/persontype', WC()->customer, $group );

				if ( empty( $current ) ) {
					$checkout_fields->persist_field_for_customer( 'pagbank/persontype', '1', WC()->customer, $group );
				}
			}
		} catch ( \Throwable $e ) {
			return;
		}
	}

	/**
	 * Conditional rule: the address country is Brazil.
	 *
	 * Evaluated against the checkout document object (Opis JSON Schema); the
	 * address-location additional fields live inside `customer.address`
	 * alongside the core address properties.
	 */
	private static function rule_country_is_brazil(): array {
		return array(
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
		);
	}

	/**
	 * Conditional rule: the address country is not Brazil.
	 */
	private static function rule_country_is_not_brazil(): array {
		return array(
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
		);
	}

	/**
	 * Conditional rule: the person type is legal person (CNPJ).
	 *
	 * The `required` keyword guards against a missing person type (a JSON
	 * Schema `properties` check passes when the property is absent), so the
	 * CPF field stays visible before any selection is made.
	 */
	private static function rule_persontype_is_legal_person(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'customer' => array(
					'properties' => array(
						'address' => array(
							'properties' => array(
								'pagbank/persontype' => array(
									'const' => '2',
								),
							),
							'required'   => array( 'pagbank/persontype' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Conditional rule: the person type is not legal person.
	 *
	 * A missing or empty selection counts as not legal person, so the CNPJ
	 * and Razão Social fields stay hidden by default (Pessoa física).
	 */
	private static function rule_persontype_is_not_legal_person(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'customer' => array(
					'properties' => array(
						'address' => array(
							'properties' => array(
								'pagbank/persontype' => array(
									'not' => array(
										'const' => '2',
									),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Save field values to legacy meta keys for compatibility with existing code.
	 *
	 * This method maps the new checkout block fields to legacy meta keys used by
	 * the classic checkout and other parts of the plugin.
	 *
	 * @param string $key       Field key.
	 * @param mixed  $value     Field value.
	 * @param string $group     Field group (billing, shipping, other).
	 * @param object $wc_object WooCommerce object (WC_Order or WC_Customer).
	 */
	public function save_field_to_legacy_meta( string $key, $value, string $group, object $wc_object ): void {
		if ( ! ( $wc_object instanceof WC_Order ) ) {
			return;
		}

		$prefix = 'billing' === $group ? '_billing_' : '_shipping_';

		switch ( $key ) {
			case 'pagbank/persontype':
				if ( in_array( (string) $value, array( '1', '2' ), true ) ) {
					$wc_object->update_meta_data( $prefix . 'persontype', (string) $value );
				}
				break;

			case 'pagbank/cpf':
				$wc_object->update_meta_data( $prefix . 'cpf', (string) $value );
				break;

			case 'pagbank/cnpj':
				$wc_object->update_meta_data( $prefix . 'cnpj', (string) $value );
				break;

			case 'pagbank/company':
				// Razão Social maps to the core company address field.
				if ( 'billing' === $group ) {
					$wc_object->set_billing_company( (string) $value );
				} else {
					$wc_object->set_shipping_company( (string) $value );
				}
				break;

			case 'pagbank/address-number':
				$wc_object->update_meta_data( $prefix . 'number', $value );
				break;

			case 'pagbank/neighborhood':
				$wc_object->update_meta_data( $prefix . 'neighborhood', $value );
				break;

			case 'pagbank/cellphone':
				$wc_object->update_meta_data( $prefix . 'cellphone', $value );
				break;

			default:
				return;
		}

		$wc_object->save_meta_data();
	}
}
