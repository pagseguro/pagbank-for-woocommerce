<?php
/**
 * Registers native checkout fields for the classic (shortcode) checkout.
 *
 * These fields are required by the PagBank API (CPF/CNPJ, address number,
 * neighborhood and cellphone). They are only inserted when no supported
 * third-party plugin already provides them:
 *
 * - Brazilian Market on WooCommerce (woocommerce-extra-checkout-fields-for-brazil).
 * - Calculadora de Frete e Campos Checkout para o Brasil (woo-better-shipping-calculator-for-brazil).
 *
 * The document fields follow the interop contract shared with those plugins:
 * a `billing_persontype` selector ('1' = individual/CPF, '2' = legal
 * person/CNPJ) toggling between the `billing_cpf` and `billing_cnpj` fields,
 * with the core `billing_company` field relabeled as "Razão Social" and
 * required for legal persons. WooCommerce persists them automatically as
 * `_billing_persontype`/`_billing_cpf`/`_billing_cnpj` order meta and the
 * same keys without the leading underscore as customer meta.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use WC_Customer;
use WP_Error;

/**
 * Class LegacyCheckoutFields.
 */
class LegacyCheckoutFields {

	/**
	 * User meta key storing the timestamp of the last Brazilian Market
	 * deprecation notice dismissal. The notice is snoozed for 24 hours and
	 * then shown again while the plugin remains active.
	 */
	private const BRAZILIAN_MARKET_NOTICE_DISMISSED_META = 'pagbank_brazilian_market_notice_dismissed';

	/**
	 * AJAX action (and nonce action) used to persist the notice dismissal.
	 */
	private const BRAZILIAN_MARKET_NOTICE_DISMISS_ACTION = 'pagbank_dismiss_brazilian_market_notice';

	/**
	 * Instance.
	 */
	private static ?LegacyCheckoutFields $instance = null;

	/**
	 * Whether this plugin inserted the document fields in the current request.
	 */
	private bool $registered_document = false;

	/**
	 * Whether this plugin inserted the address fields in the current request.
	 */
	private bool $registered_address = false;

	/**
	 * Whether this plugin inserted the cellphone field in the current request.
	 */
	private bool $registered_cellphone = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_billing_fields', array( $this, 'add_billing_fields' ), 100 );
		add_filter( 'woocommerce_shipping_fields', array( $this, 'add_shipping_fields' ), 100 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout_fields' ), 10, 2 );
		add_filter( 'woocommerce_process_checkout_field_billing_cpf', array( $this, 'normalize_cpf_value' ) );
		add_filter( 'woocommerce_process_checkout_field_billing_cnpj', array( $this, 'normalize_cnpj_value' ) );
		add_filter( 'woocommerce_process_checkout_field_billing_cellphone', array( $this, 'normalize_cellphone_value' ) );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'clear_unselected_document_fields' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_brazilian_market_deprecation_notice' ) );
		add_action( 'wp_ajax_' . self::BRAZILIAN_MARKET_NOTICE_DISMISS_ACTION, array( $this, 'dismiss_brazilian_market_deprecation_notice' ) );
	}

	/**
	 * Get instance.
	 */
	public static function get_instance(): LegacyCheckoutFields {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Check if the Brazilian Market on WooCommerce plugin is active.
	 *
	 * Never use `Extra_Checkout_Fields_For_Brazil_Front_End` for this check:
	 * the LinkNacional plugin ships a stub of that class.
	 */
	public static function is_brazilian_market_active(): bool {
		return class_exists( 'Extra_Checkout_Fields_For_Brazil' );
	}

	/**
	 * Check if the LinkNacional checkout fields plugin is active.
	 */
	public static function is_linknacional_active(): bool {
		return defined( 'WC_BETTER_SHIPPING_CALCULATOR_FOR_BRAZIL_VERSION' );
	}

	/**
	 * Whether the Brazilian Market settings enable the CPF/CNPJ fields.
	 *
	 * The `person_type` option means: 0 = none, 1 = both, 2 = CPF only, 3 = CNPJ only.
	 *
	 * @param mixed $settings The `wcbcf_settings` option value.
	 */
	public static function brazilian_market_provides_document_fields( $settings ): bool {
		return is_array( $settings ) && 0 !== (int) ( $settings['person_type'] ?? 0 );
	}

	/**
	 * Whether the Brazilian Market settings cover the cellphone role.
	 *
	 * The `cell_phone` option means: '1' = separate optional cellphone field,
	 * '2' = separate required cellphone field, '-1' = the phone field is
	 * relabeled as cellphone (no separate field, but the role is covered),
	 * '0'/unset = disabled.
	 *
	 * @param mixed $settings The `wcbcf_settings` option value.
	 */
	public static function brazilian_market_provides_cellphone_field( $settings ): bool {
		if ( ! is_array( $settings ) ) {
			return false;
		}

		$value = (string) ( $settings['cell_phone'] ?? '0' );

		return in_array( $value, array( '1', '2', '-1' ), true );
	}

	/**
	 * Whether the LinkNacional person type option enables the CPF/CNPJ fields.
	 *
	 * @param string $option_value The `woo_better_calc_person_type_select` option value (none|physical|legal|both).
	 */
	public static function linknacional_document_fields_enabled( string $option_value ): bool {
		return 'none' !== $option_value;
	}

	/**
	 * Check if the LinkNacional plugin is active with the CPF/CNPJ fields enabled.
	 *
	 * Also used to skip the Blocks additional checkout fields registration,
	 * since the LinkNacional plugin registers its own Store API fields.
	 */
	public static function linknacional_provides_document_fields(): bool {
		return self::is_linknacional_active() && self::linknacional_document_fields_enabled( (string) get_option( 'woo_better_calc_person_type_select', 'none' ) );
	}

	/**
	 * Check if an active third-party plugin already provides the CPF/CNPJ fields.
	 */
	public static function external_provides_document_fields(): bool {
		if ( self::is_brazilian_market_active() && self::brazilian_market_provides_document_fields( get_option( 'wcbcf_settings' ) ) ) {
			return true;
		}

		return self::linknacional_provides_document_fields();
	}

	/**
	 * Check if an active third-party plugin already provides the address number/neighborhood fields.
	 *
	 * Both supported plugins always add these fields while active, regardless
	 * of their person type settings.
	 */
	public static function external_provides_address_fields(): bool {
		return self::is_brazilian_market_active() || self::is_linknacional_active();
	}

	/**
	 * Check if an active third-party plugin already provides the cellphone field.
	 *
	 * Only the Brazilian Market plugin provides a cellphone field (behind its
	 * `cell_phone` setting); the LinkNacional plugin does not.
	 */
	public static function external_provides_cellphone_field(): bool {
		return self::is_brazilian_market_active() && self::brazilian_market_provides_cellphone_field( get_option( 'wcbcf_settings' ) );
	}

	/**
	 * Warn administrators that the Brazilian Market on WooCommerce plugin
	 * should be deactivated.
	 *
	 * The plugin is no longer maintained and compatibility with it will be
	 * dropped: this plugin already provides every Brazilian checkout field
	 * (CPF/CNPJ — including the new alphanumeric CNPJ format —, address
	 * number, neighborhood and cellphone).
	 */
	public function maybe_render_brazilian_market_deprecation_notice(): void {
		if ( ! self::is_brazilian_market_active() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$dismissed_at = (int) get_user_meta( get_current_user_id(), self::BRAZILIAN_MARKET_NOTICE_DISMISSED_META, true );

		if ( $dismissed_at && time() - $dismissed_at < DAY_IN_SECONDS ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible pagbank-brazilian-market-notice"><p><strong>%1$s</strong> %2$s %3$s <a href="%4$s">%5$s</a></p></div>',
			esc_html__( 'PagBank para WooCommerce:', 'pagbank-for-woocommerce' ),
			esc_html__( 'o plugin "Brazilian Market on WooCommerce" está ativo, mas não é mais necessário e a compatibilidade com ele será descontinuada.', 'pagbank-for-woocommerce' ),
			esc_html__( 'O PagBank já fornece os campos de CPF/CNPJ (incluindo o novo CNPJ alfanumérico), número, bairro e celular no checkout. Recomendamos desativá-lo.', 'pagbank-for-woocommerce' ),
			esc_url( admin_url( 'plugins.php' ) ),
			esc_html__( 'Gerenciar plugins', 'pagbank-for-woocommerce' )
		);

		// WordPress core adds the dismiss button to `.is-dismissible` notices;
		// record the dismissal timestamp per user to snooze the notice for 24h.
		printf(
			'<script>jQuery( function ( $ ) { $( document ).on( "click", ".pagbank-brazilian-market-notice .notice-dismiss", function () {'
			. ' $.post( window.ajaxurl, { action: "%1$s", _ajax_nonce: "%2$s" } );'
			. ' } ); } );</script>',
			esc_js( self::BRAZILIAN_MARKET_NOTICE_DISMISS_ACTION ),
			esc_js( wp_create_nonce( self::BRAZILIAN_MARKET_NOTICE_DISMISS_ACTION ) )
		);
	}

	/**
	 * Record the Brazilian Market deprecation notice dismissal for the current
	 * user. The stored timestamp snoozes the notice for 24 hours.
	 */
	public function dismiss_brazilian_market_deprecation_notice(): void {
		check_ajax_referer( self::BRAZILIAN_MARKET_NOTICE_DISMISS_ACTION );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( null, 403 );
		}

		update_user_meta( get_current_user_id(), self::BRAZILIAN_MARKET_NOTICE_DISMISSED_META, (string) time() );

		wp_send_json_success();
	}

	/**
	 * Insert the native billing fields into a WooCommerce billing fields array.
	 *
	 * Pure transform (no WordPress state) so it can be unit tested. Fields
	 * already present in the array are never overwritten; the core
	 * `billing_company` field is only adjusted when the person type selector
	 * was actually inserted by this plugin.
	 *
	 * @param array $fields   The billing fields array.
	 * @param array $provides Which field groups are already provided externally: {document: bool, address: bool, cellphone: bool}.
	 * @param bool  $required Whether the fields should be required (billing country is Brazil).
	 */
	public static function insert_billing_fields( array $fields, array $provides, bool $required ): array {
		$new_fields = array();

		if ( empty( $provides['document'] ) ) {
			$new_fields['billing_persontype'] = array(
				'label'    => __( 'Tipo de pessoa', 'pagbank-for-woocommerce' ),
				'type'     => 'select',
				'class'    => array( 'form-row-wide', 'person-type-field' ),
				'required' => $required,
				'priority' => 41,
				'default'  => '1',
				'options'  => array(
					'1' => __( 'Pessoa física', 'pagbank-for-woocommerce' ),
					'2' => __( 'Pessoa jurídica', 'pagbank-for-woocommerce' ),
				),
			);

			// CPF/CNPJ requiredness depends on the selected person type, so it
			// is enforced by validate_checkout_fields() instead of the core
			// required flag (which would demand both fields at once).
			$new_fields['billing_cpf'] = array(
				'label'        => __( 'CPF', 'pagbank-for-woocommerce' ),
				'type'         => 'tel',
				'class'        => array( 'form-row-wide', 'person-type-field' ),
				'required'     => false,
				'priority'     => 42,
				'maxlength'    => 14,
				'autocomplete' => 'off',
			);

			// Text (not tel) because the new alphanumeric CNPJ may contain letters.
			$new_fields['billing_cnpj'] = array(
				'label'        => __( 'CNPJ', 'pagbank-for-woocommerce' ),
				'type'         => 'text',
				'class'        => array( 'form-row-wide', 'person-type-field' ),
				'required'     => false,
				'priority'     => 43,
				'maxlength'    => 18,
				'autocomplete' => 'off',
			);
		}

		if ( empty( $provides['address'] ) ) {
			$new_fields['billing_number'] = array(
				'label'    => __( 'Número', 'pagbank-for-woocommerce' ),
				'type'     => 'text',
				'class'    => array( 'form-row-first', 'address-field' ),
				'required' => $required,
				'priority' => 55,
			);

			$new_fields['billing_neighborhood'] = array(
				'label'    => __( 'Bairro', 'pagbank-for-woocommerce' ),
				'type'     => 'text',
				'class'    => array( 'form-row-last', 'address-field' ),
				'required' => $required,
				'priority' => 56,
			);
		}

		if ( empty( $provides['cellphone'] ) ) {
			$new_fields['billing_cellphone'] = array(
				'label'    => __( 'Celular', 'pagbank-for-woocommerce' ),
				'type'     => 'tel',
				'class'    => array( 'form-row-wide' ),
				'required' => $required,
				'priority' => 99,
			);
		}

		$inserted = array();

		foreach ( $new_fields as $key => $field ) {
			if ( ! isset( $fields[ $key ] ) ) {
				$fields[ $key ]   = $field;
				$inserted[ $key ] = true;
			}
		}

		// Legal persons must provide the company legal name (Razão Social):
		// move the core company field next to the CNPJ field and let the
		// person type selector toggle its visibility. The field is created
		// when missing (e.g. the store hides the company field in the
		// WooCommerce settings). Requiredness for legal persons is enforced
		// by validate_checkout_fields().
		if ( isset( $inserted['billing_persontype'] ) ) {
			if ( isset( $fields['billing_company'] ) ) {
				$fields['billing_company']['label']    = __( 'Razão Social', 'pagbank-for-woocommerce' );
				$fields['billing_company']['class']    = array( 'form-row-wide', 'person-type-field' );
				$fields['billing_company']['priority'] = 44;
				$fields['billing_company']['required'] = false;
			} else {
				$fields['billing_company'] = array(
					'label'        => __( 'Razão Social', 'pagbank-for-woocommerce' ),
					'type'         => 'text',
					'class'        => array( 'form-row-wide', 'person-type-field' ),
					'required'     => false,
					'priority'     => 44,
					'autocomplete' => 'organization',
				);
			}
		}

		return $fields;
	}

	/**
	 * Insert the native shipping fields into a WooCommerce shipping fields array.
	 *
	 * Pure transform (no WordPress state) so it can be unit tested.
	 *
	 * @param array $fields            The shipping fields array.
	 * @param bool  $external_provides Whether an external plugin already provides the address fields.
	 * @param bool  $required          Whether the fields should be required (country is Brazil).
	 */
	public static function insert_shipping_fields( array $fields, bool $external_provides, bool $required ): array {
		if ( $external_provides ) {
			return $fields;
		}

		$new_fields = array(
			'shipping_number'       => array(
				'label'    => __( 'Número', 'pagbank-for-woocommerce' ),
				'type'     => 'text',
				'class'    => array( 'form-row-first', 'address-field' ),
				'required' => $required,
				'priority' => 55,
			),
			'shipping_neighborhood' => array(
				'label'    => __( 'Bairro', 'pagbank-for-woocommerce' ),
				'type'     => 'text',
				'class'    => array( 'form-row-last', 'address-field' ),
				'required' => $required,
				'priority' => 56,
			),
		);

		foreach ( $new_fields as $key => $field ) {
			if ( ! isset( $fields[ $key ] ) ) {
				$fields[ $key ] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Filter callback for `woocommerce_billing_fields`.
	 *
	 * @param array $fields The billing fields.
	 */
	public function add_billing_fields( array $fields ): array {
		$provides = array(
			'document'  => self::external_provides_document_fields(),
			'address'   => self::external_provides_address_fields(),
			'cellphone' => self::external_provides_cellphone_field(),
		);

		$result = self::insert_billing_fields( $fields, $provides, self::customer_country_is_br() );

		$this->registered_document  = isset( $result['billing_persontype'] ) && ! isset( $fields['billing_persontype'] );
		$this->registered_address   = isset( $result['billing_number'] ) && ! isset( $fields['billing_number'] );
		$this->registered_cellphone = isset( $result['billing_cellphone'] ) && ! isset( $fields['billing_cellphone'] );

		return $result;
	}

	/**
	 * Filter callback for `woocommerce_shipping_fields`.
	 *
	 * @param array $fields The shipping fields.
	 */
	public function add_shipping_fields( array $fields ): array {
		return self::insert_shipping_fields( $fields, self::external_provides_address_fields(), self::customer_country_is_br() );
	}

	/**
	 * Validate the native fields during the classic checkout submission.
	 *
	 * This is the authoritative validation: the `required` flag set during
	 * field registration reflects the customer country at render time, while
	 * this check uses the posted billing country.
	 *
	 * @param array    $data   The posted checkout data.
	 * @param WP_Error $errors The checkout validation errors.
	 */
	public function validate_checkout_fields( array $data, WP_Error $errors ): void {
		if ( 'BR' !== ( $data['billing_country'] ?? '' ) ) {
			return;
		}

		if ( $this->registered_document ) {
			$this->validate_document_fields( $data, $errors );
		}

		if ( $this->registered_address ) {
			$this->validate_address_fields( $data, $errors );
		}

		if ( $this->registered_cellphone ) {
			$this->validate_cellphone_field( $data, $errors );
		}
	}

	/**
	 * Drop the document values that do not belong to the selected person
	 * type before WooCommerce persists them.
	 *
	 * The checkout posts every field, including the ones hidden by the
	 * person type toggle — so a partially typed CNPJ would be saved on an
	 * individual order (and vice versa). Individuals keep only the CPF;
	 * legal persons keep the CNPJ and Razão Social, never the CPF.
	 *
	 * @param array $data The posted checkout data.
	 *
	 * @return array|mixed
	 */
	public function clear_unselected_document_fields( $data ) {
		if ( ! is_array( $data ) || ! $this->registered_document || ! isset( $data['billing_persontype'] ) ) {
			return $data;
		}

		return self::clear_document_posted_data( $data );
	}

	/**
	 * Clear the document fields of the unselected person type.
	 *
	 * Pure transform (no WordPress state) so it can be unit tested.
	 *
	 * @param array $data The posted checkout data.
	 */
	public static function clear_document_posted_data( array $data ): array {
		// '1' or anything else defaults to individual (the selector defaults
		// to '1'), mirroring the validation.
		if ( '2' === (string) ( $data['billing_persontype'] ?? '' ) ) {
			$data['billing_cpf'] = '';

			return $data;
		}

		$data['billing_cnpj'] = '';

		// The company field is the Razão Social of legal persons when the
		// document group is ours.
		if ( isset( $data['billing_company'] ) ) {
			$data['billing_company'] = '';
		}

		return $data;
	}

	/**
	 * Validate the person type, CPF/CNPJ and company (Razão Social) fields.
	 *
	 * @param array    $data   The posted checkout data.
	 * @param WP_Error $errors The checkout validation errors.
	 */
	private function validate_document_fields( array $data, WP_Error $errors ): void {
		$person_type = (string) ( $data['billing_persontype'] ?? '' );

		if ( '2' === $person_type ) {
			$cnpj = trim( (string) ( $data['billing_cnpj'] ?? '' ) );

			if ( '' === $cnpj ) {
				$errors->add( 'billing_cnpj_validation', self::required_field_message( __( 'CNPJ', 'pagbank-for-woocommerce' ) ) );
			} elseif ( ! Helpers::is_valid_cnpj( $cnpj ) ) {
				$errors->add( 'billing_cnpj_validation', __( 'CNPJ inválido. Verifique os dígitos informados.', 'pagbank-for-woocommerce' ) );
			}

			if ( '' === trim( (string) ( $data['billing_company'] ?? '' ) ) ) {
				$errors->add( 'billing_company_validation', self::required_field_message( __( 'Razão Social', 'pagbank-for-woocommerce' ) ) );
			}

			return;
		}

		// '1' or anything else defaults to individual (the selector defaults to '1').
		$cpf = trim( (string) ( $data['billing_cpf'] ?? '' ) );

		if ( '' === $cpf ) {
			$errors->add( 'billing_cpf_validation', self::required_field_message( __( 'CPF', 'pagbank-for-woocommerce' ) ) );
		} elseif ( ! Helpers::is_valid_cpf( $cpf ) ) {
			$errors->add( 'billing_cpf_validation', __( 'CPF inválido. Verifique os dígitos informados.', 'pagbank-for-woocommerce' ) );
		}
	}

	/**
	 * Validate the address number and neighborhood fields.
	 *
	 * @param array    $data   The posted checkout data.
	 * @param WP_Error $errors The checkout validation errors.
	 */
	private function validate_address_fields( array $data, WP_Error $errors ): void {
		$required_fields = array(
			'billing_number'       => __( 'Número', 'pagbank-for-woocommerce' ),
			'billing_neighborhood' => __( 'Bairro', 'pagbank-for-woocommerce' ),
		);

		foreach ( $required_fields as $key => $label ) {
			if ( '' === trim( (string) ( $data[ $key ] ?? '' ) ) && ! $errors->get_error_message( $key . '_required' ) ) {
				$errors->add( $key . '_validation', self::required_field_message( $label ) );
			}
		}
	}

	/**
	 * Validate the cellphone field.
	 *
	 * @param array    $data   The posted checkout data.
	 * @param WP_Error $errors The checkout validation errors.
	 */
	private function validate_cellphone_field( array $data, WP_Error $errors ): void {
		$cellphone = trim( (string) ( $data['billing_cellphone'] ?? '' ) );

		if ( '' === $cellphone ) {
			if ( ! $errors->get_error_message( 'billing_cellphone_required' ) ) {
				$errors->add( 'billing_cellphone_validation', self::required_field_message( __( 'Celular', 'pagbank-for-woocommerce' ) ) );
			}

			return;
		}

		if ( ! self::is_valid_cellphone( $cellphone ) ) {
			$errors->add( 'billing_cellphone_validation', __( 'Número de celular inválido.', 'pagbank-for-woocommerce' ) );
		}
	}

	/**
	 * Build the standard "required field" validation message.
	 *
	 * @param string $label The field label.
	 */
	private static function required_field_message( string $label ): string {
		// translators: %s: field label.
		return sprintf( __( '%s é um campo obrigatório.', 'pagbank-for-woocommerce' ), '<strong>' . $label . '</strong>' );
	}

	/**
	 * Validate a Brazilian cellphone number.
	 *
	 * Mirrors the validation used by the Blocks `pagbank/cellphone` field.
	 *
	 * @param string $cellphone The cellphone number.
	 */
	private static function is_valid_cellphone( string $cellphone ): bool {
		$phone_util = PhoneNumberUtil::getInstance();

		try {
			$phone_number = $phone_util->parse( $cellphone, 'BR' );

			return $phone_util->isValidNumber( $phone_number );
		} catch ( NumberParseException $e ) {
			return false;
		}
	}

	/**
	 * Normalize the posted CPF value to the formatted representation.
	 *
	 * Keeps the raw input when it does not validate, so validation still
	 * shows the value typed by the customer.
	 *
	 * @param mixed $value The posted value.
	 *
	 * @return mixed
	 */
	public function normalize_cpf_value( $value ) { // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint -- Native mixed hint requires PHP 8.0.
		if ( ! $this->registered_document || ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		return Helpers::is_valid_cpf( $value ) ? Helpers::format_cpf( $value ) : $value;
	}

	/**
	 * Normalize the posted CNPJ value to the formatted representation.
	 *
	 * Alphanumeric-CNPJ aware via the Helpers routing.
	 *
	 * @param mixed $value The posted value.
	 *
	 * @return mixed
	 */
	public function normalize_cnpj_value( $value ) { // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint -- Native mixed hint requires PHP 8.0.
		if ( ! $this->registered_document || ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		return Helpers::is_valid_cnpj( $value ) ? Helpers::format_cnpj( $value ) : $value;
	}

	/**
	 * Normalize the posted cellphone value to the international format.
	 *
	 * Mirrors the sanitization used by the Blocks `pagbank/cellphone` field.
	 *
	 * @param mixed $value The posted value.
	 *
	 * @return mixed
	 */
	public function normalize_cellphone_value( $value ) { // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint -- Native mixed hint requires PHP 8.0.
		if ( ! $this->registered_cellphone || ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		$phone_util = PhoneNumberUtil::getInstance();

		try {
			$phone_number = $phone_util->parse( $value, 'BR' );

			return $phone_util->format( $phone_number, PhoneNumberFormat::INTERNATIONAL );
		} catch ( NumberParseException $e ) {
			return $value;
		}
	}

	/**
	 * Enqueue the person type toggle and input masks on the classic checkout.
	 */
	public function enqueue_scripts(): void {
		// has_block() inspects the CURRENT page content (not the configured
		// checkout page like Helpers::is_checkout_block() does), so the script
		// still loads when the classic checkout shortcode is used on another
		// page while the main checkout page uses Blocks.
		$is_classic_checkout = is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'order-pay' ) && ! has_block( 'woocommerce/checkout' );
		$is_edit_address     = is_account_page() && is_wc_endpoint_url( 'edit-address' );

		if ( ! $is_classic_checkout && ! $is_edit_address ) {
			return;
		}

		$will_add_document  = ! self::external_provides_document_fields();
		$will_add_cellphone = ! self::external_provides_cellphone_field();

		if ( ! $will_add_document && ! $will_add_cellphone ) {
			return;
		}

		// Version with the bundle mtime so browsers pick up new builds even
		// when the plugin version has not changed (e.g. during development).
		$script_path    = plugin_dir_path( PAGBANK_WOOCOMMERCE_FILE_PATH ) . 'dist/public/legacy/checkout-fields.js';
		$script_version = PAGBANK_WOOCOMMERCE_VERSION . ( file_exists( $script_path ) ? '.' . filemtime( $script_path ) : '' );

		wp_enqueue_script(
			'pagbank-checkout-fields',
			plugins_url( 'dist/public/legacy/checkout-fields.js', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array( 'jquery' ),
			$script_version,
			true
		);

		wp_scripts()->add_data( 'pagbank-checkout-fields', 'pagbank_script', true );

		$config = array(
			'alphanumeric_cnpj' => (bool) Helpers::get_constant_value( 'PAGBANK_FEATURE_FLAG_ALPHANUMERIC_CNPJ_ENABLED', false ),
			'document'          => $will_add_document,
			'cellphone'         => $will_add_cellphone,
		);

		wp_add_inline_script(
			'pagbank-checkout-fields',
			'window.PagBankCheckoutFieldsConfig = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}

	/**
	 * Check if the current customer billing country is Brazil.
	 *
	 * Falls back to the store base location when the customer has no country set.
	 */
	private static function customer_country_is_br(): bool {
		$country = '';

		if ( function_exists( 'WC' ) && isset( WC()->customer ) && WC()->customer instanceof WC_Customer ) {
			$country = WC()->customer->get_billing_country();
		}

		if ( ! $country ) {
			$base_location = wc_get_base_location();
			$country       = $base_location['country'] ?? '';
		}

		return 'BR' === $country;
	}
}
