<?php
/**
 * Admin order screen integration for the PagBank additional checkout fields.
 *
 * WooCommerce renders the Blocks additional fields on the order edit screen,
 * but only for Store API orders and only the ones that already have a value.
 * This class fills both gaps: for Blocks orders the missing document fields
 * (person type, CPF, CNPJ, Razão Social) are injected into the billing edit
 * form; for classic-checkout and admin-created orders the whole Brazilian
 * field set is injected using the meta box native convention (rows keyed
 * `cpf`, `number`, ... resolve and persist the interop `_billing_*` /
 * `_shipping_*` meta automatically), deferring per field group to plugins
 * that provide their own admin rows (e.g. Brazilian Market).
 *
 * The read-only address panels are also cleaned up: the number, neighborhood
 * and cellphone rows are hidden (all already appear inside the formatted
 * address via AddressFormatting) and the person type row is dropped (implied
 * by which document is shown). Billing and shipping get the same treatment —
 * the checkout duplicates the address fields into both groups.
 *
 * WooCommerce splices its additional-field rows into the meta box arrays,
 * which renumbers their keys — so rows are identified by their `id` entry
 * (e.g. `_wc_billing/pagbank/cpf`), not by array key.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields as WooCheckoutFields;
use Automattic\WooCommerce\Blocks\Package;
use WC_Order;

/**
 * Class AdminOrderFields.
 */
class AdminOrderFields {

	/**
	 * Document field ids, in the order they should appear on the edit form.
	 */
	private const DOCUMENT_FIELD_IDS = array(
		'pagbank/persontype',
		'pagbank/cpf',
		'pagbank/cnpj',
		'pagbank/company',
	);

	/**
	 * Interop row aliases: classic-checkout rows (ours or from the Brazilian
	 * Market plugin) are keyed by the interop meta suffix instead of the
	 * pagbank field id. The core `company` row is intentionally not aliased
	 * to `pagbank/company` — on classic orders the Razão Social is the core
	 * company field itself.
	 */
	private const INTEROP_ROW_ALIASES = array(
		'pagbank/persontype'     => 'persontype',
		'pagbank/cpf'            => 'cpf',
		'pagbank/cnpj'           => 'cnpj',
		'pagbank/address-number' => 'number',
		'pagbank/neighborhood'   => 'neighborhood',
		'pagbank/cellphone'      => 'cellphone',
	);

	/**
	 * Rows hidden from the read-only panels: number, neighborhood and
	 * cellphone already appear inside the formatted address, and the person
	 * type is implied by which document is shown. The document rows that
	 * remain reflect each group's own stored values.
	 */
	private const HIDDEN_VIEW_ROWS = array(
		'pagbank/persontype',
		'pagbank/address-number',
		'pagbank/neighborhood',
		'pagbank/cellphone',
	);

	/**
	 * Instance.
	 */
	private static ?AdminOrderFields $instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Priority 20: after the WooCommerce Blocks additional-fields
		// injection (10), so the pagbank rows already exist in $fields.
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'filter_admin_billing_fields' ), 20, 3 );
		add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'filter_admin_shipping_fields' ), 20, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Get instance.
	 */
	public static function get_instance(): AdminOrderFields {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Filter the billing fields on the admin order screen.
	 *
	 * View: hide the redundant rows. Edit: inject the missing document
	 * fields so the person type can be changed after checkout.
	 *
	 * @param array         $fields  The billing fields.
	 * @param WC_Order|bool $order   The order being displayed.
	 * @param string        $context The context (view|edit).
	 *
	 * @return array|mixed
	 */
	public function filter_admin_billing_fields( $fields, $order = null, $context = 'edit' ) {
		if ( ! is_array( $fields ) || ! $order instanceof WC_Order ) {
			return $fields;
		}

		$is_blocks_order = 'store-api' === $order->get_created_via();

		if ( 'view' === $context ) {
			$fields = self::mark_rows_hidden( $fields, self::HIDDEN_VIEW_ROWS );

			// Blocks orders already carry a document row; classic orders get
			// one built from the interop meta.
			if ( ! $is_blocks_order && ! LegacyCheckoutFields::external_provides_document_fields() ) {
				$fields = self::insert_field_after( $fields, 'state', 'pagbank_document_summary', self::build_document_view_row( $order ) );
			}

			return $fields;
		}

		if ( ! $is_blocks_order ) {
			return self::apply_edit_form_layout( $this->insert_legacy_billing_rows( $fields ), true );
		}

		$registered = $this->get_registered_address_fields();

		foreach ( self::DOCUMENT_FIELD_IDS as $position => $field_id ) {
			if ( null !== self::find_row_key( $fields, $field_id ) || ! isset( $registered[ $field_id ] ) ) {
				continue;
			}

			// Insert after the previous document field already on the form,
			// falling back to the state field (where WooCommerce anchors the
			// additional fields).
			$anchor = 'state';

			for ( $previous = $position - 1; $previous >= 0; $previous-- ) {
				$anchor_key = self::find_row_key( $fields, self::DOCUMENT_FIELD_IDS[ $previous ] );

				if ( null !== $anchor_key ) {
					$anchor = $anchor_key;
					break;
				}
			}

			$fields = self::insert_field_after(
				$fields,
				$anchor,
				$field_id,
				$this->format_field_for_meta_box( $registered[ $field_id ], WooCheckoutFields::get_group_key( 'billing' ) . $field_id )
			);
		}

		// The rows WooCommerce injects follow the checkout registration order
		// (CNPJ before Razão Social); match the legacy admin form instead,
		// with the Razão Social right above the CNPJ.
		$fields = self::move_row_after( $fields, 'pagbank/company', 'pagbank/cpf' );

		return self::apply_edit_form_layout( $fields, true );
	}

	/**
	 * Filter the shipping fields on the admin order screen.
	 *
	 * Same treatment as billing: the checkout duplicates the address fields
	 * into the shipping group, so the read-only panel shows the shipping
	 * group's own document values (with the redundant rows hidden) and the
	 * edit form mirrors the checkout field order.
	 *
	 * @param array         $fields  The shipping fields.
	 * @param WC_Order|bool $order   The order being displayed.
	 * @param string        $context The context (view|edit).
	 *
	 * @return array|mixed
	 */
	public function filter_admin_shipping_fields( $fields, $order = null, $context = 'edit' ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		if ( 'view' === $context ) {
			return self::mark_rows_hidden( $fields, self::HIDDEN_VIEW_ROWS );
		}

		if ( ! LegacyCheckoutFields::external_provides_address_fields() ) {
			$fields = self::insert_interop_rows(
				$fields,
				array(
					'number'       => array( 'label' => __( 'Número', 'pagbank-for-woocommerce' ) ),
					'neighborhood' => array( 'label' => __( 'Bairro', 'pagbank-for-woocommerce' ) ),
				)
			);
		}

		return self::apply_edit_form_layout( $fields, false );
	}

	/**
	 * Inject the Brazilian rows into the billing edit form of a
	 * classic-checkout or admin-created order.
	 *
	 * Rows follow the meta box native convention (key without `id`, `value`
	 * or `update_callback`), so WooCommerce itself resolves and persists the
	 * interop `_billing_*` meta. Each field group defers to third-party
	 * plugins that already provide it.
	 *
	 * @param array $fields The billing fields.
	 */
	private function insert_legacy_billing_rows( array $fields ): array {
		$rows              = array();
		$provides_document = LegacyCheckoutFields::external_provides_document_fields();

		if ( ! $provides_document ) {
			$rows['persontype'] = array(
				'label'   => __( 'Tipo de pessoa', 'pagbank-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					''  => __( 'Selecionar', 'pagbank-for-woocommerce' ),
					'1' => __( 'Pessoa física', 'pagbank-for-woocommerce' ),
					'2' => __( 'Pessoa jurídica', 'pagbank-for-woocommerce' ),
				),
			);
			$rows['cpf']        = array( 'label' => __( 'CPF', 'pagbank-for-woocommerce' ) );
			$rows['cnpj']       = array( 'label' => __( 'CNPJ', 'pagbank-for-woocommerce' ) );

			// On classic orders the Razão Social is the core company field.
			if ( isset( $fields['company'] ) && is_array( $fields['company'] ) ) {
				$fields['company']['label'] = __( 'Razão Social', 'pagbank-for-woocommerce' );
			}
		}

		if ( ! LegacyCheckoutFields::external_provides_address_fields() ) {
			$rows['number']       = array( 'label' => __( 'Número', 'pagbank-for-woocommerce' ) );
			$rows['neighborhood'] = array( 'label' => __( 'Bairro', 'pagbank-for-woocommerce' ) );
		}

		if ( ! LegacyCheckoutFields::external_provides_cellphone_field() ) {
			$rows['cellphone'] = array( 'label' => __( 'Celular', 'pagbank-for-woocommerce' ) );
		}

		$fields = self::insert_interop_rows( $fields, $rows );

		// The Razão Social belongs with the document block, right above the
		// CNPJ, not at the top of the form where the core company field sits.
		if ( ! $provides_document ) {
			$fields = self::move_row_after( $fields, 'company', 'pagbank/cpf' );
		}

		return $fields;
	}

	/**
	 * Insert interop-keyed rows after the state field, skipping the ones
	 * already present (e.g. added by another plugin), with the document rows
	 * rendered on full-width rows.
	 *
	 * Pure transform (no WordPress state) so it can be unit tested.
	 *
	 * @param array $fields The admin fields.
	 * @param array $rows   Field rows keyed by the interop meta suffix.
	 */
	public static function insert_interop_rows( array $fields, array $rows ): array {
		$anchor = 'state';

		foreach ( $rows as $key => $row ) {
			$existing_key = self::find_row_key( $fields, $key );

			if ( null !== $existing_key ) {
				$anchor = $existing_key;
				continue;
			}

			if ( in_array( $key, array( 'persontype', 'cpf', 'cnpj', 'cellphone' ), true ) ) {
				$row['wrapper_class'] = 'form-field-wide';
			}

			$fields = self::insert_field_after( $fields, $anchor, $key, $row );
			$anchor = $key;
		}

		return $fields;
	}

	/**
	 * Build the read-only document row of a classic-checkout order from the
	 * interop meta: the CNPJ for legal persons, the CPF otherwise.
	 *
	 * @param WC_Order $order The order.
	 */
	private static function build_document_view_row( WC_Order $order ): array {
		$persontype      = (string) $order->get_meta( '_billing_persontype' );
		$cnpj            = (string) $order->get_meta( '_billing_cnpj' );
		$cpf             = (string) $order->get_meta( '_billing_cpf' );
		$is_legal_person = '2' === $persontype || ( '' === $persontype && '' !== $cnpj );

		return array(
			'label' => $is_legal_person ? __( 'CNPJ', 'pagbank-for-woocommerce' ) : __( 'CPF', 'pagbank-for-woocommerce' ),
			'value' => $is_legal_person ? $cnpj : $cpf,
			'show'  => true,
		);
	}

	/**
	 * Resolve the identity of a meta box row.
	 *
	 * WooCommerce splices the additional-field rows in with renumbered keys,
	 * so the identity lives in the `id` entry (`_wc_billing/pagbank/cpf`);
	 * core rows keep their string key (`first_name`).
	 *
	 * @param int|string $key   The array key.
	 * @param array      $field The field definition.
	 */
	private static function row_id( $key, array $field ): string {
		return isset( $field['id'] ) ? (string) $field['id'] : (string) $key;
	}

	/**
	 * Whether a meta box row corresponds to the given checkout field id.
	 *
	 * @param int|string $key      The array key.
	 * @param array      $field    The field definition.
	 * @param string     $field_id The checkout field id (e.g. pagbank/cpf).
	 */
	private static function row_matches( $key, array $field, string $field_id ): bool {
		$row_id = self::row_id( $key, $field );

		// The raw field id or a group-prefixed variant such as
		// `_wc_billing/pagbank/cpf`.
		if ( $row_id === $field_id || substr( $row_id, -strlen( '/' . $field_id ) ) === '/' . $field_id ) {
			return true;
		}

		// Classic-checkout rows are keyed by the interop meta suffix
		// (`cpf`, `_billing_cpf`, ...), both in our rows and in the ones
		// added by the Brazilian Market plugin.
		$alias = self::INTEROP_ROW_ALIASES[ $field_id ] ?? ( in_array( $field_id, self::INTEROP_ROW_ALIASES, true ) ? $field_id : null );

		if ( null === $alias ) {
			return false;
		}

		return in_array( $row_id, array( $alias, '_billing_' . $alias, '_shipping_' . $alias ), true );
	}

	/**
	 * Find the array key of the row matching a checkout field id.
	 *
	 * Pure transform (no WordPress state) so it can be unit tested.
	 *
	 * @param array  $fields   The admin fields.
	 * @param string $field_id The checkout field id (e.g. pagbank/cpf).
	 *
	 * @return int|string|null
	 */
	public static function find_row_key( array $fields, string $field_id ) {
		foreach ( $fields as $key => $field ) {
			if ( is_array( $field ) && self::row_matches( $key, $field, $field_id ) ) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * Mark the rows matching the given checkout field ids as hidden on the
	 * read-only panel.
	 *
	 * Pure transform (no WordPress state) so it can be unit tested.
	 *
	 * @param array $fields    The admin fields.
	 * @param array $field_ids The checkout field ids to hide.
	 */
	public static function mark_rows_hidden( array $fields, array $field_ids ): array {
		foreach ( $field_ids as $field_id ) {
			$key = self::find_row_key( $fields, $field_id );

			if ( null !== $key ) {
				$fields[ $key ]['show'] = false;
			}
		}

		return $fields;
	}

	/**
	 * Arrange the edit form rows to mirror the checkout order.
	 *
	 * The admin meta box CSS floats every .form-field left with clear:left
	 * and pairs rows by floating a fixed set of fields right (last_name,
	 * address_2, postcode, state, phone) — so the number pairs with the
	 * complement (address_2, right float) while street, neighborhood, email
	 * and cellphone take full rows.
	 *
	 * Pure transform (no WordPress state) so it can be unit tested.
	 *
	 * @param array $fields     The admin fields.
	 * @param bool  $is_billing Whether these are the billing fields.
	 */
	public static function apply_edit_form_layout( array $fields, bool $is_billing ): array {
		$fields = self::move_row_after( $fields, 'pagbank/address-number', 'address_1' );
		$fields = self::move_row_after( $fields, 'address_2', 'pagbank/address-number' );
		$fields = self::move_row_after( $fields, 'pagbank/neighborhood', 'address_2' );
		$fields = self::move_row_after( $fields, 'state', 'city' );

		$fields = self::set_row_wrapper_class( $fields, 'address_1', 'form-field-wide' );
		$fields = self::set_row_wrapper_class( $fields, 'pagbank/address-number', '' );
		$fields = self::set_row_wrapper_class( $fields, 'pagbank/neighborhood', 'form-field-wide' );

		if ( $is_billing ) {
			$fields = self::move_row_after( $fields, 'pagbank/cellphone', 'email' );
			$fields = self::set_row_wrapper_class( $fields, 'email', 'form-field-wide' );
			$fields = self::set_row_wrapper_class( $fields, 'pagbank/cellphone', 'form-field-wide' );
			$fields = self::set_row_wrapper_class( $fields, 'phone', 'form-field-wide' );
		}

		return $fields;
	}

	/**
	 * Set the wrapper class of the row matching a checkout field id.
	 *
	 * An empty class renders the admin default half-width row;
	 * `form-field-wide` renders a full row.
	 *
	 * Pure transform (no WordPress state) so it can be unit tested.
	 *
	 * @param array  $fields        The admin fields.
	 * @param string $field_id      The checkout field id (e.g. pagbank/cpf).
	 * @param string $wrapper_class The wrapper class.
	 */
	public static function set_row_wrapper_class( array $fields, string $field_id, string $wrapper_class ): array {
		$key = self::find_row_key( $fields, $field_id );

		if ( null !== $key ) {
			$fields[ $key ]['wrapper_class'] = $wrapper_class;
		}

		return $fields;
	}

	/**
	 * Move the row matching a checkout field id to right after the row
	 * matching the anchor field id, keeping it in place when either row is
	 * absent.
	 *
	 * Pure transform (no WordPress state) so it can be unit tested.
	 *
	 * @param array  $fields          The admin fields.
	 * @param string $field_id        The checkout field id to move.
	 * @param string $anchor_field_id The checkout field id to move it after.
	 */
	public static function move_row_after( array $fields, string $field_id, string $anchor_field_id ): array {
		$key = self::find_row_key( $fields, $field_id );

		if ( null === $key ) {
			return $fields;
		}

		$row = $fields[ $key ];
		unset( $fields[ $key ] );

		$anchor = self::find_row_key( $fields, $anchor_field_id );

		if ( null === $anchor ) {
			$fields[ $key ] = $row;

			return $fields;
		}

		// Renumbered rows get their unique id as key; string keys are kept.
		return self::insert_field_after( $fields, $anchor, is_string( $key ) ? $key : self::row_id( $key, $row ), $row );
	}

	/**
	 * Insert a field right after the anchor key, appending when absent.
	 *
	 * Pure transform (no WordPress state) so it can be unit tested.
	 *
	 * @param array      $fields The admin fields.
	 * @param int|string $anchor The key to insert after.
	 * @param string     $key    The new field key.
	 * @param array      $field  The new field definition.
	 */
	public static function insert_field_after( array $fields, $anchor, string $key, array $field ): array {
		if ( ! isset( $fields[ $anchor ] ) ) {
			$fields[ $key ] = $field;

			return $fields;
		}

		$result = array();

		foreach ( $fields as $existing_key => $existing_field ) {
			$result[ $existing_key ] = $existing_field;

			if ( $existing_key === $anchor ) {
				$result[ $key ] = $field;
			}
		}

		return $result;
	}

	/**
	 * Convert a registered checkout field into the admin meta box shape.
	 *
	 * Mirrors the (protected) formatter used by WooCommerce for the rows it
	 * injects itself.
	 *
	 * @param array  $field        The registered field definition.
	 * @param string $prefixed_key The group-prefixed field key.
	 */
	private function format_field_for_meta_box( array $field, string $prefixed_key ): array {
		$formatted = array(
			'id'              => $prefixed_key,
			'label'           => isset( $field['label'] ) ? (string) $field['label'] : '',
			'value'           => '',
			'type'            => isset( $field['type'] ) ? (string) $field['type'] : 'text',
			'update_callback' => array( $this, 'update_additional_field' ),
			'show'            => true,
			'wrapper_class'   => 'form-field-wide',
		);

		if ( 'select' === $formatted['type'] && isset( $field['options'] ) && is_array( $field['options'] ) ) {
			$formatted['options'] = array_column( $field['options'], 'label', 'value' );
		}

		return $formatted;
	}

	/**
	 * Persist an injected additional field when the order is saved.
	 *
	 * Same behavior as the WooCommerce update callback for the rows it
	 * injects itself; persisting also fires the interop sync that keeps the
	 * legacy `_billing_*` meta up to date.
	 *
	 * @param string   $key   The group-prefixed field key.
	 * @param mixed    $value The submitted value.
	 * @param WC_Order $order The order being saved.
	 */
	public function update_additional_field( $key, $value, $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$parts = explode( '/', (string) $key, 2 );

		if ( 2 !== count( $parts ) ) {
			return;
		}

		try {
			$checkout_fields = Package::container()->get( WooCheckoutFields::class );
			$checkout_fields->persist_field_for_order( $parts[1], $value, $order, WooCheckoutFields::get_group_name( $parts[0] ), false );
		} catch ( \Throwable $e ) {
			return;
		}
	}

	/**
	 * Get the registered address-location additional fields.
	 */
	private function get_registered_address_fields(): array {
		if ( ! class_exists( Package::class ) ) {
			return array();
		}

		try {
			$checkout_fields = Package::container()->get( WooCheckoutFields::class );
		} catch ( \Throwable $e ) {
			return array();
		}

		$fields = $checkout_fields->get_fields_for_location( 'address' );

		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Enqueue the CPF/CNPJ input masks on the order edit screen.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function admin_enqueue_scripts( string $hook ): void {
		$screen_id = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || $screen->id !== $screen_id ) {
			return;
		}

		// Version with the bundle mtime so browsers pick up new builds even
		// when the plugin version has not changed (e.g. during development).
		$script_path    = plugin_dir_path( PAGBANK_WOOCOMMERCE_FILE_PATH ) . 'dist/admin/order-fields.js';
		$script_version = PAGBANK_WOOCOMMERCE_VERSION . ( file_exists( $script_path ) ? '.' . filemtime( $script_path ) : '' );

		wp_enqueue_script(
			'pagbank-admin-order-fields',
			plugins_url( 'dist/admin/order-fields.js', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array(),
			$script_version,
			true
		);

		wp_scripts()->add_data( 'pagbank-admin-order-fields', 'pagbank_script', true );

		$config = array(
			'alphanumeric_cnpj'      => (bool) Helpers::get_constant_value( 'PAGBANK_FEATURE_FLAG_ALPHANUMERIC_CNPJ_ENABLED', false ),
			// Interop ids (_billing_cpf, ...) are only masked when the
			// document group is ours; the Brazilian Market plugin ships its
			// own admin masks for its fields.
			'interop_document_masks' => ! LegacyCheckoutFields::external_provides_document_fields(),
		);

		wp_add_inline_script(
			'pagbank-admin-order-fields',
			'window.PagBankAdminOrderFieldsConfig = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}
}
