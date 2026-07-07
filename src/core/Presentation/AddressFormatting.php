<?php
/**
 * Brazilian address formatting for order summaries.
 *
 * Folds the address number, neighborhood and cellphone into the WooCommerce
 * formatted address (BR locale) so order summaries read naturally ("Rua X,
 * 163" / "Centro") instead of listing them as separate labeled rows. On the order
 * confirmation and view-order pages the raw additional-field rows (person
 * type, CPF, CNPJ, número, bairro, celular) are removed and replaced by a
 * compact document summary below the billing address: the CPF for
 * individuals, or the Razão Social with the CNPJ underneath for legal
 * persons.
 *
 * The BR address format and the `{number}`/`{neighborhood}` placeholders
 * follow the same convention used by the Brazilian Market on WooCommerce
 * plugin, so both integrations produce identical output when they overlap.
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
 * Class AddressFormatting.
 */
class AddressFormatting {

	/**
	 * Additional checkout fields hidden from the order confirmation and
	 * view-order pages (their data is rendered inside the formatted address
	 * and the document summary instead).
	 */
	private const CONFIRMATION_HIDDEN_FIELD_IDS = array(
		'pagbank/persontype',
		'pagbank/cpf',
		'pagbank/cnpj',
		'pagbank/company',
		'pagbank/address-number',
		'pagbank/neighborhood',
		'pagbank/cellphone',
	);

	/**
	 * Instance.
	 */
	private static ?AddressFormatting $instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Priority 20: after Brazilian Market (10), so the checkout-page
		// cleanup below also covers the format registered by that plugin.
		add_filter( 'woocommerce_localisation_address_formats', array( $this, 'add_brazilian_address_format' ), 20 );
		add_filter( 'woocommerce_formatted_address_replacements', array( $this, 'add_address_replacements' ), 10, 2 );
		add_filter( 'woocommerce_order_formatted_billing_address', array( $this, 'add_order_billing_address_fields' ), 10, 2 );
		add_filter( 'woocommerce_order_formatted_shipping_address', array( $this, 'add_order_shipping_address_fields' ), 10, 2 );
		add_filter( 'woocommerce_my_account_my_address_formatted_address', array( $this, 'add_customer_address_fields' ), 10, 3 );
		add_action( 'wp', array( $this, 'maybe_hide_order_confirmation_field_rows' ) );
		add_action( 'woocommerce_order_details_after_customer_address', array( $this, 'render_document_summary' ), 10, 2 );
		add_filter( 'render_block_woocommerce/order-confirmation-billing-address', array( $this, 'append_document_summary_to_block' ), 10, 2 );
	}

	/**
	 * Get instance.
	 */
	public static function get_instance(): AddressFormatting {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add the number and neighborhood placeholders to the BR address format.
	 *
	 * Skipped when another plugin (e.g. Brazilian Market) already customized
	 * the format with a number placeholder.
	 *
	 * On the checkout page the formats are serialized for the Blocks
	 * client-side address card, whose formatter only knows the core
	 * placeholders and would print "{number}" literally — so there the
	 * custom placeholders are stripped instead (including ones added by
	 * other plugins).
	 *
	 * @param array $formats Country address formats.
	 */
	public function add_brazilian_address_format( $formats ): array {
		$formats = is_array( $formats ) ? $formats : array();

		if ( $this->is_checkout_form_page() ) {
			if ( isset( $formats['BR'] ) ) {
				$formats['BR'] = self::strip_custom_address_placeholders( (string) $formats['BR'] );
			}

			return $formats;
		}

		if ( isset( $formats['BR'] ) && false !== strpos( $formats['BR'], '{number}' ) ) {
			return $formats;
		}

		$formats['BR'] = "{name}\n{address_1}, {number}\n{address_2}\n{neighborhood}\n{city}\n{state}\n{postcode}\n{country}\n{cellphone}";

		return $formats;
	}

	/**
	 * Remove the custom BR placeholders from an address format string.
	 *
	 * Pure transform (no WordPress state) so it can be unit tested.
	 *
	 * @param string $format Address format.
	 */
	public static function strip_custom_address_placeholders( string $format ): string {
		$format = str_replace( array( ', {number}', ' {number}', '{number}' ), '', $format );
		$format = str_replace( array( "{neighborhood}\n", ' {neighborhood}', '{neighborhood}' ), '', $format );
		$format = str_replace( array( "\n{cellphone}", ' {cellphone}', '{cellphone}' ), '', $format );

		return $format;
	}

	/**
	 * Whether the current request renders the checkout form page.
	 *
	 * The order-received and order-pay endpoints live under the checkout
	 * page but render addresses server-side, where the custom placeholders
	 * must be kept.
	 */
	private function is_checkout_form_page(): bool {
		return function_exists( 'is_checkout' )
			&& is_checkout()
			&& ! is_wc_endpoint_url( 'order-received' )
			&& ! is_wc_endpoint_url( 'order-pay' );
	}

	/**
	 * Provide replacement values for the custom BR address placeholders.
	 *
	 * @param array $replacements Placeholder replacements.
	 * @param array $args         Address arguments.
	 */
	public function add_address_replacements( $replacements, $args ): array {
		$args = wp_parse_args(
			(array) $args,
			array(
				'number'       => '',
				'neighborhood' => '',
				'cellphone'    => '',
			)
		);

		$replacements['{number}']       = (string) $args['number'];
		$replacements['{neighborhood}'] = (string) $args['neighborhood'];
		$replacements['{cellphone}']    = (string) $args['cellphone'];

		return $replacements;
	}

	/**
	 * Expose the billing number and neighborhood to the formatted address.
	 *
	 * @param array|null $address Address data.
	 * @param WC_Order   $order   The order object.
	 *
	 * @return array|mixed
	 */
	public function add_order_billing_address_fields( $address, $order ) {
		if ( ! is_array( $address ) || ! $order instanceof WC_Order ) {
			return $address;
		}

		$address['number']       = (string) $order->get_meta( '_billing_number' );
		$address['neighborhood'] = (string) $order->get_meta( '_billing_neighborhood' );
		$address['cellphone']    = (string) $order->get_meta( '_billing_cellphone' );

		return $address;
	}

	/**
	 * Expose the shipping number and neighborhood to the formatted address.
	 *
	 * @param array|null $address Address data.
	 * @param WC_Order   $order   The order object.
	 *
	 * @return array|mixed
	 */
	public function add_order_shipping_address_fields( $address, $order ) {
		if ( ! is_array( $address ) || ! $order instanceof WC_Order ) {
			return $address;
		}

		$address['number']       = (string) $order->get_meta( '_shipping_number' );
		$address['neighborhood'] = (string) $order->get_meta( '_shipping_neighborhood' );
		$address['cellphone']    = (string) $order->get_meta( '_shipping_cellphone' );

		return $address;
	}

	/**
	 * Expose the customer number and neighborhood to the My Account address cards.
	 *
	 * @param array  $address      Address data.
	 * @param int    $customer_id  Customer ID.
	 * @param string $address_type Address type (billing|shipping).
	 *
	 * @return array|mixed
	 */
	public function add_customer_address_fields( $address, $customer_id, $address_type ) {
		if ( ! is_array( $address ) ) {
			return $address;
		}

		$address['number']       = (string) get_user_meta( $customer_id, $address_type . '_number', true );
		$address['neighborhood'] = (string) get_user_meta( $customer_id, $address_type . '_neighborhood', true );
		$address['cellphone']    = 'billing' === $address_type ? (string) get_user_meta( $customer_id, 'billing_cellphone', true ) : '';

		return $address;
	}

	/**
	 * Remove the raw additional-field rows from the order confirmation pages.
	 *
	 * WooCommerce renders every address-location additional field as a
	 * label/value row below the address on the order confirmation and
	 * view-order pages, ignoring `show_in_order_confirmation`. The data is
	 * already displayed inside the formatted address (number/neighborhood)
	 * and the document summary, so deregister the fields for these requests.
	 * Store API, checkout, My Account and admin requests are not affected.
	 */
	public function maybe_hide_order_confirmation_field_rows(): void {
		if ( ! function_exists( 'is_order_received_page' ) || ( ! is_order_received_page() && ! is_wc_endpoint_url( 'view-order' ) ) ) {
			return;
		}

		if ( ! class_exists( Package::class ) ) {
			return;
		}

		try {
			$checkout_fields = Package::container()->get( WooCheckoutFields::class );
		} catch ( \Throwable $e ) {
			return;
		}

		foreach ( self::CONFIRMATION_HIDDEN_FIELD_IDS as $field_id ) {
			$checkout_fields->deregister_checkout_field( $field_id );
		}
	}

	/**
	 * Build the document summary lines for an order.
	 *
	 * Individuals: the CPF. Legal persons: the Razão Social with the CNPJ
	 * underneath, mirroring the address summary style. The cellphone is not
	 * included: it is rendered inline at the end of the formatted address.
	 *
	 * @param WC_Order $order The order object.
	 *
	 * @return string[]
	 */
	public function get_document_summary_lines( WC_Order $order ): array {
		$persontype = (string) $order->get_meta( '_billing_persontype' );
		$cpf        = (string) $order->get_meta( '_billing_cpf' );
		$cnpj       = (string) $order->get_meta( '_billing_cnpj' );
		$lines      = array();

		$is_legal_person = '2' === $persontype || ( '' === $persontype && '' !== $cnpj );

		if ( $is_legal_person ) {
			if ( '' !== $order->get_billing_company() ) {
				$lines[] = $order->get_billing_company();
			}

			if ( '' !== $cnpj ) {
				/* translators: %s: CNPJ number. */
				$lines[] = sprintf( __( 'CNPJ: %s', 'pagbank-for-woocommerce' ), $cnpj );
			}
		} elseif ( '' !== $cpf ) {
			/* translators: %s: CPF number. */
			$lines[] = sprintf( __( 'CPF: %s', 'pagbank-for-woocommerce' ), $cpf );
		}

		return $lines;
	}

	/**
	 * Build the document summary markup for an order.
	 *
	 * @param WC_Order $order The order object.
	 */
	private function get_document_summary_html( WC_Order $order ): string {
		$lines = $this->get_document_summary_lines( $order );

		if ( array() === $lines ) {
			return '';
		}

		return '<p class="pagbank-order-document-summary">' . implode( '<br>', array_map( 'esc_html', $lines ) ) . '</p>';
	}

	/**
	 * Render the document summary below the billing address (classic templates).
	 *
	 * @param string   $address_type Address type (billing|shipping).
	 * @param WC_Order $order        The order object.
	 */
	public function render_document_summary( $address_type, $order ): void {
		if ( 'billing' !== $address_type || ! $order instanceof WC_Order ) {
			return;
		}

		echo wp_kses_post( $this->get_document_summary_html( $order ) );
	}

	/**
	 * Append the document summary to the Blocks order confirmation billing address.
	 *
	 * The Blocks confirmation renders addresses through its own block types
	 * (no template hook), so filter the rendered block output instead.
	 *
	 * @param string $block_content Rendered block content.
	 * @param array  $block         Parsed block data.
	 *
	 * @return string|mixed
	 */
	public function append_document_summary_to_block( $block_content, $block ) {
		if ( ! is_string( $block_content ) || '' === trim( (string) $block_content ) ) {
			return $block_content;
		}

		$order_id = absint( get_query_var( 'order-received' ) );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order ) {
			return $block_content;
		}

		$summary = $this->get_document_summary_html( $order );

		if ( '' === $summary ) {
			return $block_content;
		}

		// Keep the summary inside the block wrapper so it inherits the
		// address block styles.
		$closing_div = strrpos( $block_content, '</div>' );

		if ( false === $closing_div ) {
			return $block_content . $summary;
		}

		return substr_replace( $block_content, $summary, $closing_div, 0 );
	}
}
