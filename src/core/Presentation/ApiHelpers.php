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
function get_order_tax_id( WC_Order $order ) {
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
function get_phone_number( $raw_number ) {
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
function get_order_items( WC_Order $order ) {
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
		'items'             => array(),
		'customer'          => array(
			'name'   => $order->get_formatted_billing_full_name(),
			'email'  => $order->get_billing_email(),
			'tax_id' => get_order_tax_id( $order ),
			'phones' => array(
				get_phone_number( $order->get_billing_phone() ),
			),
		),
		'shipping'          => array(
			'address' => array(
				'street'      => substr( $order->get_shipping_address_1(), 0, 160 ),
				'number'      => substr( $order->get_meta( '_shipping_number' ), 0, 20 ),
				'locality'    => substr( $order->get_meta( '_shipping_neighborhood' ), 0, 60 ),
				'city'        => substr( $order->get_shipping_city(), 0, 90 ),
				'region_code' => substr( $order->get_shipping_state(), 0, 2 ),
				'country'     => 'BRA',
				'postal_code' => preg_replace( '/[^0-9]/', '', $order->get_shipping_postcode() ),
			),
		),
		'items'             => get_order_items( $order ),
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
		'metadata'          => array(
			'order_id' => $order->get_id(),
		),
	);

	if ( $order->get_shipping_address_2() ) {
		$data['shipping']['complement'] = substr( $order->get_shipping_address_2(), 0, 40 );
	}

	return $data;
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
		'amount'            => array(
			'value'    => format_money_cents( $order->get_total() ),
			'currency' => $order->get_currency(),
		),
		'items'             => get_order_items( $order ),
		'payment_method'    => array(
			'type'   => 'BOLETO',
			'boleto' => array(
				'due_date'          => Carbon::now()->addDays( $expiration_in_days )->toDateString(),
				'instruction_lines' => array(
					'line_1' => 'Pagamento para ' . get_bloginfo( 'name' ),
					'line_2' => 'Via PagBank',
				),
				'holder'            => array(
					'name'    => $order->get_formatted_billing_full_name(),
					'tax_id'  => get_order_tax_id( $order ),
					'email'   => $order->get_billing_email(),
					'address' => array(
						'street'      => substr( $order->get_billing_address_1(), 0, 160 ),
						'number'      => substr( $order->get_meta( '_billing_number' ), 0, 20 ),
						'locality'    => substr( $order->get_meta( '_billing_neighborhood' ), 0, 60 ),
						'city'        => substr( $order->get_billing_city(), 0, 90 ),
						'region'      => substr( $order->get_billing_state(), 0, 2 ),
						'region_code' => substr( $order->get_billing_state(), 0, 2 ),
						'country'     => 'BRA',
						'postal_code' => preg_replace( '/[^0-9]/', '', $order->get_billing_postcode() ),
					),
				),
			),
		),
		'notification_urls' => array(
			WebhookHandler::get_webhook_url(),
		),
		'metadata'          => array(
			'order_id' => $order->get_id(),
		),
	);

	if ( $order->get_shipping_address_2() ) {
		$data['shipping']['complement'] = substr( $order->get_shipping_address_2(), 0, 40 );
	}

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
		'amount'            => array(
			'value'    => format_money_cents( $order->get_total() ),
			'currency' => $order->get_currency(),
		),
		'payment_method'    => array(
			'type'         => 'CREDIT_CARD',
			'installments' => 1,
			'capture'      => true,
			'holder'       => array(
				'name'   => $order->get_formatted_billing_full_name(),
				'tax_id' => get_order_tax_id( $order ),
			),
		),
		'notification_urls' => array(
			WebhookHandler::get_webhook_url(),
		),
		'metadata'          => array(
			'order_id' => $order->get_id(),
		),
	);

	if ( null === $payment_token || 'new' === $payment_token ) {
		if ( null === $encrypted_card ) {
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

		if ( ! $token ) {
			throw new Exception( __( 'Payment token not found.', 'pagbank-woocommerce' ) );
		}

		$card_id                        = $token->get_token();
		$data['payment_method']['card'] = array(
			'id' => $card_id,
		);
	}

	return $data;
}
