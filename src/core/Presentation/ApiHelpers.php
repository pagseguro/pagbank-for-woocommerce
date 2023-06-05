<?php
/**
 * Api helper functions.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

use Carbon\Carbon;
use Exception;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use WC_Order;
use WC_Payment_Tokens;

/**
 * Get order tax id.
 *
 * @param WC_Order $order Order.
 *
 * @return string
 */
function get_order_tax_id_api_data( WC_Order $order ) {
	$billing_cpf  = $order->get_meta( '_billing_cpf' );
	$billing_cnpj = $order->get_meta( '_billing_cnpj' );
	$tax_id       = preg_replace( '/[^0-9]/', '', $billing_cpf ? $billing_cpf : $billing_cnpj );

	return $tax_id;
}

/**
 * Get phone number.
 *
 * @param string $raw_number Raw number.
 *
 * @return array
 */
function get_phone_number_api_data( $raw_number ) {
	$phone_util   = PhoneNumberUtil::getInstance();
	$phone_number = $phone_util->parse( $raw_number, 'BR' );

	return array(
		'country' => (string) $phone_number->getCountryCode(),
		'area'    => substr( $phone_number->getNationalNumber(), 0, 2 ),
		'number'  => substr( $phone_number->getNationalNumber(), 2 ),
		'type'    => $phone_util->getNumberType( $phone_number ) === PhoneNumberType::MOBILE ? 'MOBILE' : 'HOME',
	);
}

/**
 * Get order items.
 *
 * @param WC_Order $order Order.
 *
 * @return array
 */
function get_order_items_api_data( WC_Order $order ) {
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
					'unit_amount' => format_money_cents( $item_total ),
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
 *
 * @return array
 */
function get_order_customer_api_data( WC_Order $order ) {
	return array(
		'name'   => $order->get_formatted_billing_full_name(),
		'email'  => $order->get_billing_email(),
		'tax_id' => get_order_tax_id_api_data( $order ),
		'phones' => array(
			get_phone_number_api_data( $order->get_billing_phone() ),
		),
	);
}

/**
 * Get order shipping address.
 *
 * @param WC_Order $order Order.
 * @param array    $address Address.
 *
 * @return array
 */
function get_order_shipping_address_api_data( WC_Order $order, array $address = array() ) {
	$defaults = array(
		'street'      => substr( $order->get_shipping_address_1(), 0, 160 ),
		'number'      => substr( $order->get_meta( '_shipping_number' ), 0, 20 ),
		'locality'    => substr( $order->get_meta( '_shipping_neighborhood' ), 0, 60 ),
		'city'        => substr( $order->get_shipping_city(), 0, 90 ),
		'region_code' => substr( $order->get_shipping_state(), 0, 2 ),
		'country'     => 'BRA',
		'postal_code' => preg_replace( '/[^0-9]/', '', $order->get_shipping_postcode() ),
	);

	if ( $order->get_shipping_address_2() ) {
		$defaults['complement'] = substr( $order->get_shipping_address_2(), 0, 40 );
	}

	return wp_parse_args( $address, $defaults );
}

/**
 * Get order billing address.
 *
 * @param WC_Order $order Order.
 * @param array    $address Address.
 *
 * @return array
 */
function get_order_billing_address_api_data( WC_Order $order, array $address = array() ) {
	$defaults = array(
		'street'      => substr( $order->get_billing_address_1(), 0, 160 ),
		'number'      => substr( $order->get_meta( '_billing_number' ), 0, 20 ),
		'locality'    => substr( $order->get_meta( '_billing_neighborhood' ), 0, 60 ),
		'city'        => substr( $order->get_billing_city(), 0, 90 ),
		'region'      => substr( $order->get_billing_state(), 0, 2 ),
		'region_code' => substr( $order->get_billing_state(), 0, 2 ),
		'country'     => 'BRA',
		'postal_code' => preg_replace( '/[^0-9]/', '', $order->get_billing_postcode() ),
	);

	if ( $order->get_shipping_address_2() ) {
		$defaults['complement'] = substr( $order->get_billing_address_2(), 0, 40 );
	}

	return wp_parse_args( $address, $defaults );
}

/**
 * Get order metadata.
 *
 * @param WC_Order $order Order.
 * @param array    $metadata Metadata.
 *
 * @return array
 */
function get_order_metadata_api_data( WC_Order $order, array $metadata = array() ) {
	$defaults = array(
		'order_id' => $order->get_id(),
	);

	return wp_parse_args( $metadata, $defaults );
}

/**
 * Get pix payment api data.
 *
 * @param WC_Order $order Order.
 * @param int      $expiration_in_minutes Expiration in minutes.
 *
 * @return array
 */
function get_pix_payment_api_data( WC_Order $order, int $expiration_in_minutes ) {
	$data = array(
		'reference_id'      => $order->get_id(),
		'items'             => get_order_items_api_data( $order ),
		'customer'          => get_order_customer_api_data( $order ),
		'shipping'          => array(
			'address' => get_order_shipping_address_api_data( $order ),
		),
		'qr_codes'          => array(
			array(
				'amount'          => array(
					'value' => $order->get_total() * 100,
				),
				'expiration_date' => Carbon::now()->addMinutes( $expiration_in_minutes )->toAtomString(),
			),
		),
		'notification_urls' => array(
			WebhookHandler::get_webhook_url(),
		),
		'metadata'          => get_order_metadata_api_data( $order ),
	);

	return $data;
}

/**
 * Get order amount.
 *
 * @param WC_Order $order Order.
 *
 * @return array
 */
function get_order_amount_api_data( WC_Order $order ) {
	return array(
		'value'    => format_money_cents( $order->get_total() ),
		'currency' => $order->get_currency(),
	);
}

/**
 * Get Boleto payment api data.
 *
 * @param WC_Order $order Order.
 * @param int      $expiration_in_days Expiration in days.
 *
 * @return array
 */
function get_boleto_payment_api_data( WC_Order $order, int $expiration_in_days ) {
	$data = array(
		'reference_id'      => $order->get_id(),
		'items'             => get_order_items_api_data( $order ),
		'customer'          => get_order_customer_api_data( $order ),
		'shipping'          => array(
			'address' => get_order_shipping_address_api_data( $order ),
		),
		'amount'            => get_order_amount_api_data( $order ),
		'payment_method'    => array(
			'type'   => 'BOLETO',
			'boleto' => array(
				'due_date'          => Carbon::now()->addDays( $expiration_in_days )->toDateString(),
				'instruction_lines' => array(
					// translators: %s: blog name.
					'line_1' => sprintf( __( 'Pagamento para %s', 'pagbank-woocommerce' ), get_bloginfo( 'name' ) ),
					'line_2' => __( 'Via PagBank', 'pagbank-woocommerce' ),
				),
				'holder'            => array(
					'name'    => $order->get_formatted_billing_full_name(),
					'tax_id'  => get_order_tax_id_api_data( $order ),
					'email'   => $order->get_billing_email(),
					'address' => get_order_billing_address_api_data( $order ),
				),
			),
		),
		'notification_urls' => array(
			WebhookHandler::get_webhook_url(),
		),
		'metadata'          => get_order_metadata_api_data( $order ),
	);

	return $data;
}

/**
 * Get Credit Card payment data.
 *
 * @param WC_Order $order Order.
 * @param string   $payment_token Payment token.
 * @param string   $encrypted_card Encrypted card.
 * @param bool     $save_card Save card.
 *
 * @return array
 * @throws Exception Throws exception when card is not valid.
 */
function get_credit_card_payment_data( WC_Order $order, string $payment_token = null, string $encrypted_card = null, bool $save_card = false ) {
	$data = array(
		'reference_id'      => $order->get_id(),
		'items'             => get_order_items_api_data( $order ),
		'customer'          => get_order_customer_api_data( $order ),
		'shipping'          => array(
			'address' => get_order_shipping_address_api_data( $order ),
		),
		'amount'            => get_order_amount_api_data( $order ),
		'payment_method'    => array(
			'type'         => 'CREDIT_CARD',
			'installments' => 1,
			'capture'      => true,
			'holder'       => array(
				'name'   => $order->get_formatted_billing_full_name(),
				'tax_id' => get_order_tax_id_api_data( $order ),
			),
		),
		'notification_urls' => array(
			WebhookHandler::get_webhook_url(),
		),
		'metadata'          => get_order_metadata_api_data( $order ),
	);

	$is_new_credit_card = null === $payment_token || 'new' === $payment_token;

	if ( $is_new_credit_card ) {
		$is_missing_new_credit_card = null === $encrypted_card || empty( $encrypted_card );

		if ( $is_missing_new_credit_card ) {
			throw new Exception( __( 'Invalid credit card encryption. This should not happen, contact support.', 'pagbank-woocommerce' ) );
		}

		$data['payment_method']['card'] = array(
			'encrypted' => $encrypted_card,
		);

		if ( $save_card ) {
			$data['payment_method']['card']['store'] = true;
		}
	} else {
		$token = WC_Payment_Tokens::get( $payment_token );

		if ( null === $token ) {
			throw new Exception( __( 'Payment token not found.', 'pagbank-woocommerce' ) );
		}

		$card_id                        = $token->get_token();
		$data['payment_method']['card'] = array(
			'id' => $card_id,
		);
	}

	return $data;
}
