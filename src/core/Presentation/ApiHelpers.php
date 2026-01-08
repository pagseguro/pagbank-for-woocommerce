<?php
/**
 * Api helper public static functions.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Carbon\Carbon;
use Exception;
use PagBank_WooCommerce\Gateways\BoletoPaymentGateway;
use PagBank_WooCommerce\Gateways\CheckoutPaymentGateway;
use PagBank_WooCommerce\Gateways\CreditCardPaymentGateway;
use PagBank_WooCommerce\Gateways\DebitCardPaymentGateway;
use PagBank_WooCommerce\Gateways\GooglePayPaymentGateway;
use PagBank_WooCommerce\Gateways\ApplePayPaymentGateway;
use PagBank_WooCommerce\Gateways\PayWithPagBankGateway;
use PagBank_WooCommerce\Gateways\PixPaymentGateway;
use WC_Order;
use WC_Payment_Tokens;
use WP_Error;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;

/**
 * Api helper class.
 *
 * @package PagBank_WooCommerce\Presentation
 */
class ApiHelpers {

	/**
	 * Get order person type.
	 *
	 * @param WC_Order $order The order.
	 * @return array{type: string, tax_id: string}  The data.
	 */
	private static function get_order_person_type( WC_Order $order ): array {
		$is_api = wc()->is_store_api_request();

		if ( $is_api ) {
			$checkout_fields = Package::container()->get( CheckoutFields::class );
			$tax_id          = $checkout_fields->get_field_from_object( 'pagbank/tax-id', $order, 'billing' ) ?? $checkout_fields->get_field_from_object( 'pagbank/tax-id', $order, 'shipping' );
			$parsed_tax_id   = Helpers::parse_cpf_or_cnpj( $tax_id );

			return array(
				'type'   => $parsed_tax_id['type'],
				'tax_id' => $parsed_tax_id['value'],
			);
		}

		$person_type = $order->get_meta( '_billing_person_type' );
		$cpf         = Helpers::filter_only_numbers( $order->get_meta( '_billing_cpf' ) );
		$cnpj        = Helpers::filter_only_numbers( $order->get_meta( '_billing_cnpj' ) );

		switch ( $person_type ) {
			case '2':
			case 'cnpj':
				return array(
					'type'   => 'cnpj',
					'tax_id' => $cnpj,
				);
			case '1':
			case 'cpf':
				return array(
					'type'   => 'cpf',
					'tax_id' => $cpf,
				);
			default:
				return array(
					'type'   => $cnpj ? 'cnpj' : 'cpf',
					'tax_id' => $cnpj ? $cnpj : $cpf,
				);
		}
	}

	/**
	 * Get order tax id.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function get_order_tax_id_api_data( WC_Order $order ): string {
		$data = self::get_order_person_type( $order );

		return $data['tax_id'];
	}

	/**
	 * Get order items.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function get_order_items_api_data( WC_Order $order ): array {
		$items = array();

		if ( 0 < count( $order->get_items() ) ) {
			foreach ( $order->get_items() as $order_item ) {
				if ( $order_item['qty'] ) {
					$item_total = $order->get_item_subtotal( $order_item, false );

					if ( 0 >= (float) $item_total ) {
						continue;
					}

					$item_name = $order_item['name'];

					$items[] = array(
						'name'        => str_replace( '&ndash;', '-', $item_name ),
						'unit_amount' => Helpers::format_money_cents( $item_total ),
						'quantity'    => $order_item['qty'],
					);
				}
			}
		}

		return $items;
	}

	/**
	 * Get order customer.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function get_order_customer_api_data( WC_Order $order ): array {
		$person_type = self::get_order_person_type( $order );
		$is_cnpj     = $person_type['type'] === 'cnpj';

		$company_name = $is_cnpj ? $order->get_billing_company() : null;
		$name         = $company_name ? $company_name : $order->get_formatted_billing_full_name();

		return array(
			'name'   => $name,
			'email'  => $order->get_billing_email(),
			'tax_id' => self::get_order_tax_id_api_data( $order ),
		);
	}

	/**
	 * Get the value that is not empty.
	 *
	 * @param string $value1 Value 1.
	 * @param string $value2 Value 2.
	 */
	private static function get_not_empty( string $value1, string $value2 ): string {
		return ! empty( $value1 ) ? $value1 : $value2;
	}

	/**
	 * Get order shipping address.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $address Address.
	 */
	private static function get_order_shipping_address_api_data( WC_Order $order, array $address = array() ): array {
		$is_api = wc()->is_store_api_request();

		$defaults = array();

		if ( $is_api ) {
			$checkout_fields = Package::container()->get( CheckoutFields::class );

			// phpcs:disable Generic.Files.LineLength -- Complex nested function calls cannot be split.
			$defaults = array(
				'street'      => substr( self::get_not_empty( $order->get_shipping_address_1(), $order->get_billing_address_1() ), 0, 160 ),
				'number'      => substr( self::get_not_empty( $checkout_fields->get_field_from_object( 'pagbank/address-number', $order, 'shipping' ), $checkout_fields->get_field_from_object( 'pagbank/address-number', $order, 'billing' ) ), 0, 20 ),
				'locality'    => substr( self::get_not_empty( $checkout_fields->get_field_from_object( 'pagbank/neighborhood', $order, 'shipping' ), $checkout_fields->get_field_from_object( 'pagbank/neighborhood', $order, 'billing' ) ), 0, 60 ),
				// phpcs:enable Generic.Files.LineLength
				'city'        => substr( self::get_not_empty( $order->get_shipping_city(), $order->get_billing_city() ), 0, 90 ),
				'region_code' => substr( self::get_not_empty( $order->get_shipping_state(), $order->get_billing_state() ), 0, 2 ),
				'country'     => 'BRA',
				'postal_code' => preg_replace( '/[^0-9]/', '', self::get_not_empty( $order->get_shipping_postcode(), $order->get_billing_postcode() ) ),
			);
		} else {
			$defaults = array(
				'street'      => substr( self::get_not_empty( $order->get_shipping_address_1(), $order->get_billing_address_1() ), 0, 160 ),
				'number'      => substr( self::get_not_empty( $order->get_meta( '_shipping_number' ), $order->get_meta( '_billing_number' ) ), 0, 20 ),
				'locality'    => substr( self::get_not_empty( $order->get_meta( '_shipping_neighborhood' ), $order->get_meta( '_billing_neighborhood' ) ), 0, 60 ),
				'city'        => substr( self::get_not_empty( $order->get_shipping_city(), $order->get_billing_city() ), 0, 90 ),
				'region_code' => substr( self::get_not_empty( $order->get_shipping_state(), $order->get_billing_state() ), 0, 2 ),
				'country'     => 'BRA',
				'postal_code' => preg_replace( '/[^0-9]/', '', self::get_not_empty( $order->get_shipping_postcode(), $order->get_billing_postcode() ) ),
			);
		}

		if ( $order->get_shipping_address_2() ) {
			$defaults['complement'] = substr( self::get_not_empty( $order->get_shipping_address_2(), $order->get_billing_address_2() ), 0, 40 );
		}

		$data = wp_parse_args( $address, $defaults );

		if ( empty( $data['locality'] ) ) {
			$data['locality'] = 'N/A';
		}

		return $data;
	}

	/**
	 * Get order billing address.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $address Address.
	 */
	private static function get_order_billing_address_api_data( WC_Order $order, array $address = array() ): array {
		$is_api          = wc()->is_store_api_request();
		$checkout_fields = Package::container()->get( CheckoutFields::class );

		$defaults = array(
			'street'      => substr( $order->get_billing_address_1(), 0, 160 ),
			'number'      => substr( $is_api ? $checkout_fields->get_field_from_object( 'pagbank/address-number', $order, 'billing' ) : $order->get_meta( '_billing_number' ), 0, 20 ),
			'locality'    => substr( $is_api ? $checkout_fields->get_field_from_object( 'pagbank/neighborhood', $order, 'billing' ) : $order->get_meta( '_billing_neighborhood' ), 0, 60 ),
			'city'        => substr( $order->get_billing_city(), 0, 90 ),
			'region'      => substr( $order->get_billing_state(), 0, 2 ),
			'region_code' => substr( $order->get_billing_state(), 0, 2 ),
			'country'     => 'BRA',
			'postal_code' => preg_replace( '/[^0-9]/', '', $order->get_billing_postcode() ),
		);

		if ( $order->get_shipping_address_2() ) {
			$defaults['complement'] = substr( $order->get_billing_address_2(), 0, 40 );
		}

		$data = wp_parse_args( $address, $defaults );

		if ( empty( $data['locality'] ) ) {
			$data['locality'] = 'N/A';
		}

		return $data;
	}

	/**
	 * Get order metadata.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $metadata Metadata.
	 */
	private static function get_order_metadata_api_data( WC_Order $order, array $metadata = array() ): array {
		$defaults = array(
			'order_id'  => $order->get_id(),
			'signature' => self::get_order_id_signed( $order->get_id() ),
			'password'  => wp_generate_password( 30, false ),
		);

		return wp_parse_args( $metadata, $defaults );
	}

	/**
	 * Get pix payment api data.
	 *
	 * @param PixPaymentGateway $gateway Gateway.
	 * @param WC_Order          $order Order.
	 * @param int               $expiration_in_minutes Expiration in minutes.
	 */
	public static function get_pix_payment_api_data( PixPaymentGateway $gateway, WC_Order $order, int $expiration_in_minutes ): array {
		$password = wp_generate_password( 30, false );

		$data = array(
			'reference_id'      => self::get_order_reference_id_data( $order, $password ),
			'items'             => self::get_order_items_api_data( $order ),
			'customer'          => self::get_order_customer_api_data( $order ),
			'shipping'          => array(
				'address' => self::get_order_shipping_address_api_data( $order ),
			),
			'qr_codes'          => array(
				array(
					'amount'          => array(
						'value' => Helpers::format_money_cents( $order->get_total() ),
					),
					'expiration_date' => Carbon::now()->addMinutes( $expiration_in_minutes )->toAtomString(),
				),
			),
			'notification_urls' => array(
				WebhookHandler::get_webhook_url(),
			),
			'metadata'          => self::get_order_metadata_api_data( $order, array( 'password' => $password ) ),
		);

		return apply_filters( 'pagbank_pix_payment_data', $data, $order, $gateway );
	}

	/**
	 * Get Pay with PagBank payment api data.
	 *
	 * @param PayWithPagBankGateway $gateway Gateway.
	 * @param WC_Order              $order Order.
	 * @param bool                  $is_mobile Whether the request is from a mobile device.
	 * @param string|null           $redirect_url Redirect URL for deeplink (mobile only).
	 */
	public static function get_pay_with_pagbank_api_data( PayWithPagBankGateway $gateway, WC_Order $order, bool $is_mobile = false, ?string $redirect_url = null ): array {
		$password = wp_generate_password( 30, false );

		$data = array(
			'reference_id'      => self::get_order_reference_id_data( $order, $password ),
			'items'             => self::get_order_items_api_data( $order ),
			'customer'          => self::get_order_customer_api_data( $order ),
			'shipping'          => array(
				'address' => self::get_order_shipping_address_api_data( $order ),
			),
			'notification_urls' => array(
				WebhookHandler::get_webhook_url(),
			),
			'metadata'          => self::get_order_metadata_api_data( $order, array( 'password' => $password ) ),
		);

		$amount_value = Helpers::format_money_cents( $order->get_total() );

		if ( $is_mobile && $redirect_url ) {
			// Deeplink for mobile.
			$data['deep_links'] = array(
				array(
					'amount'       => array(
						'value' => $amount_value,
					),
					'redirect_url' => $redirect_url,
				),
			);
		} else {
			// QR Code for desktop.
			$data['qr_codes'] = array(
				array(
					'amount'       => array(
						'value' => $amount_value,
					),
					'arrangements' => array( 'PAGBANK' ),
				),
			);
		}

		return apply_filters( 'pagbank_pay_with_pagbank_payment_data', $data, $order, $gateway );
	}

	/**
	 * Get Checkout PagBank api data.
	 *
	 * @param CheckoutPaymentGateway $gateway Gateway.
	 * @param WC_Order               $order Order.
	 * @param int                    $expiration_in_minutes Expiration in minutes.
	 * @param string                 $return_url Return URL after payment.
	 */
	public static function get_checkout_api_data( CheckoutPaymentGateway $gateway, WC_Order $order, int $expiration_in_minutes, string $return_url ): array {
		$password = wp_generate_password( 30, false );

		$data = array(
			'reference_id'              => self::get_order_reference_id_data( $order, $password ),
			'items'                     => self::get_order_items_api_data( $order ),
			'customer'                  => self::get_order_customer_api_data( $order ),
			'expiration_date'           => Carbon::now()->addMinutes( $expiration_in_minutes )->toAtomString(),
			'redirect_url'              => $return_url,
			'return_url'                => $return_url,
			'notification_urls'         => array(
				WebhookHandler::get_webhook_url(),
			),
			'payment_notification_urls' => array(
				WebhookHandler::get_webhook_url(),
			),
			'metadata'                  => self::get_order_metadata_api_data( $order, array( 'password' => $password ) ),
		);

		// Add shipping data if order needs shipping.
		$shipping_data = self::get_checkout_shipping_api_data( $order );
		if ( $shipping_data ) {
			$data['shipping'] = $shipping_data;
		}

		return apply_filters( 'pagbank_checkout_data', $data, $order, $gateway );
	}

	/**
	 * Get shipping data for Checkout PagBank API.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array|null Shipping data or null if no shipping needed.
	 */
	private static function get_checkout_shipping_api_data( WC_Order $order ): ?array {
		// Check if order needs shipping.
		$needs_shipping = false;
		foreach ( $order->get_items() as $item ) {
			$product = wc_get_product( $item->get_id() );
			if ( $product && $product->needs_shipping() ) {
				$needs_shipping = true;
				break;
			}
		}

		// If no shipping needed (virtual products), return null.
		if ( ! $needs_shipping ) {
			return null;
		}

		$shipping_total = (float) $order->get_shipping_total();

		// Determine shipping type based on shipping total.
		if ( $shipping_total > 0 ) {
			// Fixed shipping with value.
			return array(
				'type'               => 'FIXED',
				'amount'             => Helpers::format_money_cents( $shipping_total ),
				'address_modifiable' => false,
				'address'            => self::get_order_shipping_address_api_data( $order ),
			);
		}

		// Free shipping.
		return array(
			'type'               => 'FREE',
			'address_modifiable' => false,
			'address'            => self::get_order_shipping_address_api_data( $order ),
		);
	}

	/**
	 * Get order amount.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function get_order_amount_api_data( WC_Order $order ): array {
		return array(
			'value'    => Helpers::format_money_cents( $order->get_total() ),
			'currency' => $order->get_currency(),
		);
	}

	/**
	 * Get Boleto payment api data.
	 *
	 * @param BoletoPaymentGateway $gateway Gateway.
	 * @param WC_Order             $order Order.
	 * @param int                  $expiration_in_days Expiration in days.
	 */
	public static function get_boleto_payment_api_data( BoletoPaymentGateway $gateway, WC_Order $order, int $expiration_in_days ): array {
		$password = wp_generate_password( 30, false );

		$data = array(
			'reference_id'      => self::get_order_reference_id_data( $order, $password ),
			'items'             => self::get_order_items_api_data( $order ),
			'customer'          => self::get_order_customer_api_data( $order ),
			'shipping'          => array(
				'address' => self::get_order_shipping_address_api_data( $order ),
			),
			'charges'           => array(
				array(
					'reference_id'   => self::get_order_reference_id_data( $order, $password ),
					// translators: %1$s: order id, %2$s: blog name.
					'description'    => sprintf( __( 'Pedido %1$s - %2$s', 'pagbank-for-woocommerce' ), $order->get_id(), get_bloginfo( 'name' ) ),
					'amount'         => self::get_order_amount_api_data( $order ),
					'payment_method' => array(
						'type'   => 'BOLETO',
						'boleto' => array(
							'due_date'          => Carbon::now()->addDays( $expiration_in_days )->toDateString(),
							'instruction_lines' => array(
								// translators: %s: blog name.
								'line_1' => sprintf( __( 'Pagamento para %s', 'pagbank-for-woocommerce' ), get_bloginfo( 'name' ) ),
								'line_2' => __( 'PagBank', 'pagbank-for-woocommerce' ),
							),
							'holder'            => array(
								'name'    => $order->get_formatted_billing_full_name(),
								'tax_id'  => self::get_order_tax_id_api_data( $order ),
								'email'   => $order->get_billing_email(),
								'address' => self::get_order_billing_address_api_data( $order ),
							),
						),
					),
				),
			),
			'notification_urls' => array(
				WebhookHandler::get_webhook_url(),
			),
			'metadata'          => self::get_order_metadata_api_data( $order, array( 'password' => $password ) ),
		);

		return apply_filters( 'pagbank_boleto_payment_data', $data, $order, $gateway );
	}

	/**
	 * Get Google Pay payment api data.
	 *
	 * @param GooglePayPaymentGateway $gateway Gateway.
	 * @param WC_Order                $order Order.
	 * @param string                  $google_pay_token Google Pay payment token (JSON string from Google Pay API).
	 */
	public static function get_google_pay_payment_api_data( GooglePayPaymentGateway $gateway, WC_Order $order, string $google_pay_token ): array {
		$password = wp_generate_password( 30, false );

		$data = array(
			'reference_id'      => self::get_order_reference_id_data( $order, $password ),
			'items'             => self::get_order_items_api_data( $order ),
			'customer'          => self::get_order_customer_api_data( $order ),
			'shipping'          => array(
				'address' => self::get_order_shipping_address_api_data( $order ),
			),
			'charges'           => array(
				array(
					'reference_id'   => self::get_order_reference_id_data( $order, $password ),
					// translators: %1$s: order id, %2$s: blog name.
					'description'    => sprintf( __( 'Pedido %1$s - %2$s', 'pagbank-for-woocommerce' ), $order->get_id(), get_bloginfo( 'name' ) ),
					'amount'         => self::get_order_amount_api_data( $order ),
					'payment_method' => array(
						'type'         => 'CREDIT_CARD',
						'installments' => 1,
						'capture'      => true,
						'card'         => array(
							'wallet' => array(
								'type' => 'GOOGLE_PAY',
								'key'  => $google_pay_token,
							),
						),
					),
				),
			),
			'notification_urls' => array(
				WebhookHandler::get_webhook_url(),
			),
			'metadata'          => self::get_order_metadata_api_data( $order, array( 'password' => $password ) ),
		);

		return apply_filters( 'pagbank_google_pay_payment_data', $data, $order, $gateway );
	}

	/**
	 * Get Apple Pay payment api data.
	 *
	 * @param ApplePayPaymentGateway $gateway Gateway.
	 * @param WC_Order               $order Order.
	 * @param string                 $apple_pay_token Apple Pay payment token (JSON string from Apple Pay API).
	 */
	public static function get_apple_pay_payment_api_data( ApplePayPaymentGateway $gateway, WC_Order $order, string $apple_pay_token ): array {
		$password = wp_generate_password( 30, false );

		$data = array(
			'reference_id'      => self::get_order_reference_id_data( $order, $password ),
			'items'             => self::get_order_items_api_data( $order ),
			'customer'          => self::get_order_customer_api_data( $order ),
			'shipping'          => array(
				'address' => self::get_order_shipping_address_api_data( $order ),
			),
			'charges'           => array(
				array(
					'reference_id'   => self::get_order_reference_id_data( $order, $password ),
					// translators: %1$s: order id, %2$s: blog name.
					'description'    => sprintf( __( 'Pedido %1$s - %2$s', 'pagbank-for-woocommerce' ), $order->get_id(), get_bloginfo( 'name' ) ),
					'amount'         => self::get_order_amount_api_data( $order ),
					'payment_method' => array(
						'type'         => 'CREDIT_CARD',
						'installments' => 1,
						'capture'      => true,
						'card'         => array(
							'wallet' => array(
								'type' => 'APPLE_PAY',
								'key'  => $apple_pay_token,
							),
						),
					),
				),
			),
			'notification_urls' => array(
				WebhookHandler::get_webhook_url(),
			),
			'metadata'          => self::get_order_metadata_api_data( $order, array( 'password' => $password ) ),
		);

		return apply_filters( 'pagbank_apple_pay_payment_data', $data, $order, $gateway );
	}

	/**
	 * Reference ID data.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $password Password.
	 */
	private static function get_order_reference_id_data( WC_Order $order, string $password ): string {
		return wp_json_encode(
			array(
				'id'       => $order->get_id(),
				'password' => $password,
			)
		);
	}

	/**
	 * Get Credit Card payment data.
	 *
	 * @param CreditCardPaymentGateway $gateway Gateway.
	 * @param WC_Order                 $order Order.
	 * @param string|null              $payment_token Payment token.
	 * @param string|null              $encrypted_card Encrypted card.
	 * @param string|null              $card_holder Card holder.
	 * @param bool                     $save_card Save card.
	 * @param string|null              $cvv CVV.
	 * @param bool                     $is_subscription Is subscription.
	 * @param int                      $installments Installments.
	 * @param array|null               $transfer_of_interest_fee Transfer of interest fee.
	 * @param string|null              $threeds_id 3DS authentication ID.
	 *
	 * @throws Exception Throws exception when card is not valid.
	 */
	public static function get_card_payment_data( // phpcs:ignore Generic.Files.LineLength
		CreditCardPaymentGateway $gateway,
		WC_Order $order,
		?string $payment_token = null,
		?string $encrypted_card = null,
		?string $card_holder = null,
		bool $save_card = false,
		?string $cvv = null,
		bool $is_subscription = false,
		int $installments = 1,
		?array $transfer_of_interest_fee = null,
		?string $threeds_id = null
	): array {
		$password = wp_generate_password( 30, false );

		$data = array(
			'reference_id'      => self::get_order_reference_id_data( $order, $password ),
			'items'             => self::get_order_items_api_data( $order ),
			'customer'          => self::get_order_customer_api_data( $order ),
			'shipping'          => array(
				'address' => self::get_order_shipping_address_api_data( $order ),
			),
			'charges'           => array(
				array(
					'reference_id'   => self::get_order_reference_id_data( $order, $password ),
					// translators: %1$s: order id, %2$s: blog name.
					'description'    => sprintf( esc_html__( 'Pedido %1$s - %2$s', 'pagbank-for-woocommerce' ), $order->get_id(), get_bloginfo( 'name' ) ),
					'amount'         => self::get_order_amount_api_data( $order ),
					'payment_method' => array(
						'type'         => $gateway->card_type,
						'installments' => $installments,
						'capture'      => true,
						'holder'       => array(
							'tax_id' => self::get_order_tax_id_api_data( $order ),
						),
					),
				),
			),
			'notification_urls' => array(
				WebhookHandler::get_webhook_url(),
			),
			'metadata'          => self::get_order_metadata_api_data( $order, array( 'password' => $password ) ),
		);

		if ( $card_holder ) {
			$data['charges'][0]['payment_method']['holder']['name'] = $card_holder;
		}

		if ( null !== $transfer_of_interest_fee ) {
			$data['charges'][0]['amount'] = $transfer_of_interest_fee;
		}

		$is_new_card = null === $payment_token || 'new' === $payment_token;

		if ( $is_new_card ) {
			$is_missing_new_card = null === $encrypted_card || empty( $encrypted_card );

			if ( $is_missing_new_card ) {
				throw new Exception( esc_html__( 'O cartão criptografado é inválido. Por favor, contate o suporte.', 'pagbank-for-woocommerce' ) );
			}

			$data['charges'][0]['payment_method']['card'] = array(
				'encrypted' => $encrypted_card,
			);

			if ( $save_card ) {
				$data['charges'][0]['payment_method']['card']['store'] = true;
			}
		} else {
			/**
			 * The payment token.
			 *
			 * @var PaymentToken
			 */
			$token = WC_Payment_Tokens::get( $payment_token );

			if ( null === $token || $token->get_user_id() !== get_current_user_id() ) {
				throw new Exception( esc_html__( 'O token de pagamento não foi encontrado.', 'pagbank-for-woocommerce' ) );
			}

			$card_id                                      = $token->get_token();
			$data['charges'][0]['payment_method']['card'] = array(
				'id' => $card_id,
			);
			$data['charges'][0]['payment_method']['holder']['name'] = $token->get_holder();
		}

		if ( $cvv ) {
			$data['charges'][0]['payment_method']['card']['cvv'] = $cvv;
		}

		if ( $is_subscription ) {
			$data['charges'][0]['recurring'] = array(
				'type' => 'INITIAL',
			);
		}

		if ( $threeds_id ) {
			$data['charges'][0]['payment_method']['authentication_method'] = array(
				'type' => 'THREEDS',
				'id'   => $threeds_id,
			);
		}

		return apply_filters( 'pagbank_card_payment_data', $data, $order, $gateway );
	}

	/**
	 * Get Credit Card payment data when order has a empty value subscription.
	 *
	 * @param CreditCardPaymentGateway $gateway Gateway.
	 * @param WC_Order                 $order Order.
	 * @param string|null              $payment_token Payment token.
	 * @param string|null              $encrypted_card Encrypted card.
	 * @param string|null              $card_holder Card holder.
	 * @param bool                     $save_card Save card.
	 * @param string|null              $cvv CVV.
	 * @param bool                     $is_subscription Is subscription.
	 * @param int                      $installments Installments.
	 * @param array|null               $transfer_of_interest_fee Transfer of interest fee.
	 * @param string|null              $threeds_id 3DS authentication ID.
	 *
	 * @throws Exception Throws exception when card is not valid.
	 */
	public static function get_card_payment_data_for_empty_value_subscription(
		CreditCardPaymentGateway $gateway,
		WC_Order $order,
		?string $payment_token = null,
		?string $encrypted_card = null,
		?string $card_holder = null,
		bool $save_card = false,
		?string $cvv = null,
		bool $is_subscription = false,
		int $installments = 1,
		?array $transfer_of_interest_fee = null,
		?string $threeds_id = null
	): array {
		$data = self::get_card_payment_data(
			$gateway,
			$order,
			$payment_token,
			$encrypted_card,
			$card_holder,
			$save_card,
			$cvv,
			$is_subscription,
			$installments,
			$transfer_of_interest_fee,
			$threeds_id
		);

		$data['items'] = array(
			array(
				'name'        => __( 'Assinatura', 'pagbank-for-woocommerce' ),
				'unit_amount' => Helpers::format_money_cents( 1 ),
				'quantity'    => 1,
			),
		);

		$data['charges'][0]['amount']['value'] = Helpers::format_money_cents( 1 );

		return $data;
	}

	/**
	 * Get Credit Card renewal payment data.
	 *
	 * @param CreditCardPaymentGateway $gateway Gateway.
	 * @param WC_Order                 $renewal_order Renewal order.
	 * @param PaymentToken             $payment_token Payment token.
	 * @param float                    $amount Amount.
	 */
	public static function get_card_renewal_payment_data( CreditCardPaymentGateway $gateway, WC_Order $renewal_order, PaymentToken $payment_token, float $amount ): array {
		$password = wp_generate_password( 30, false );

		$data = array(
			'reference_id'      => self::get_order_reference_id_data( $renewal_order, $password ),
			'items'             => self::get_order_items_api_data( $renewal_order ),
			'customer'          => self::get_order_customer_api_data( $renewal_order ),
			'shipping'          => array(
				'address' => self::get_order_shipping_address_api_data( $renewal_order ),
			),
			'charges'           => array(
				array(
					'reference_id'   => self::get_order_reference_id_data( $renewal_order, $password ),
					// translators: %1$s: order id, %2$s: blog name.
					'description'    => sprintf( __( 'Pedido %1$s - %2$s', 'pagbank-for-woocommerce' ), $renewal_order->get_id(), get_bloginfo( 'name' ) ),
					'amount'         => array(
						'value'    => Helpers::format_money_cents( $amount ),
						'currency' => $renewal_order->get_currency(),
					),
					'payment_method' => array(
						'type'         => $gateway->card_type,
						'installments' => 1,
						'capture'      => true,
						'card'         => array(
							'id' => $payment_token->get_token(),
						),
						'holder'       => array(
							'name'   => $payment_token->get_holder(),
							'tax_id' => self::get_order_tax_id_api_data( $renewal_order ),
						),
					),
					'recurring'      => array(
						'type' => 'SUBSEQUENT',
					),
				),
			),
			'notification_urls' => array(
				WebhookHandler::get_webhook_url(),
			),
			'metadata'          => self::get_order_metadata_api_data( $renewal_order, array( 'password' => $password ) ),
		);

		return apply_filters( 'pagbank_card_payment_data', $data, $renewal_order, $gateway );
	}

	/**
	 * Get installments plan without interest.
	 *
	 * @param int $value Value in cents.
	 * @param int $installments Installments.
	 * @param int $minimum_installment_value Minimum installment value in cents.
	 */
	public static function get_installments_plan_no_interest( int $value, int $installments = 1, int $minimum_installment_value = 500 ): array {
		$installments_plan = array();
		$installment_value = $value / $installments;

		if ( $installment_value < $minimum_installment_value ) {
			$installments = max( 1, floor( $value / $minimum_installment_value ) );
		}

		for ( $i = 1; $i <= $installments; $i++ ) {
			$i_value             = floor( $value / $i );
			$installments_plan[] = array(
				'installments'      => $i,
				'installment_value' => $i_value,
				'interest_free'     => true,
				// translators: 1: installments, 2: installment value.
				'title'             => sprintf( __( '%1$dx de %2$s sem juros', 'pagbank-for-woocommerce' ), $i, Helpers::format_money( $i_value / 100 ) ),
				'amount'            => $value,
			);
		}

		return $installments_plan;
	}

	/**
	 * Get a signature pair to validate webhooks.
	 */
	private static function get_signature_pair(): array {
		$stored_keypair = get_option( 'pagbank_stored_keypair' );

		if ( ! $stored_keypair ) {
			$sign_pair   = sodium_crypto_sign_keypair();
			$sign_secret = sodium_crypto_sign_secretkey( $sign_pair );
			$sign_public = sodium_crypto_sign_publickey( $sign_pair );

			update_option( 'pagbank_stored_keypair', Helpers::encode_text( $sign_pair ) );
		} else {
			$sign_pair   = Helpers::decode_text( $stored_keypair );
			$sign_secret = sodium_crypto_sign_secretkey( $sign_pair );
			$sign_public = sodium_crypto_sign_publickey( $sign_pair );
		}

		return array(
			'sign_pair'   => $sign_pair,
			'sign_secret' => $sign_secret,
			'sign_public' => $sign_public,
		);
	}

	/**
	 * Get a signed order id.
	 *
	 * @param string $order_id Order id.
	 */
	private static function get_order_id_signed( string $order_id ): string {
		$signature_pair = self::get_signature_pair();
		$signature      = sodium_crypto_sign_detached( $order_id, $signature_pair['sign_secret'] );

		return Helpers::encode_text( $signature );
	}

	/**
	 * Process order refund.
	 *
	 * @param Api        $api    PagBank API.
	 * @param WC_Order   $order  Order.
	 * @param float|null $amount Amount to refund.
	 * @param string     $reason Reason for refund (not used by PagBank API).
	 *
	 * @return bool|WP_Error
	 */
	public static function process_order_refund( Api $api, WC_Order $order, ?float $amount = null, string $reason = '' ) {
		$amount = floatval( $amount );

		if ( $amount <= 0 ) {
			return new WP_Error( 'error', __( 'O valor para reembolso deve ser maior que zero', 'pagbank-for-woocommerce' ) );
		}

		$pagbank_charge_id = $order->get_meta( '_pagbank_charge_id' );

		try {
			$refund = $api->refund( $pagbank_charge_id, $amount );

			if ( is_wp_error( $refund ) ) {
				return $refund;
			}

			if ( $refund['status'] === 'CANCELED' ) {
				return true;
			}

			return new WP_Error( 'error', __( 'Houve um erro ao tentar realizar o reembolso.', 'pagbank-for-woocommerce' ) );
		} catch ( Exception $ex ) {
			return new WP_Error( 'error', __( 'Houve um erro ao tentar realizar o reembolso.', 'pagbank-for-woocommerce' ) );
		}
	}
}
