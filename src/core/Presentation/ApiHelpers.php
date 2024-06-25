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
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use PagBank_WooCommerce\Gateways\BoletoPaymentGateway;
use PagBank_WooCommerce\Gateways\CreditCardPaymentGateway;
use PagBank_WooCommerce\Gateways\PixPaymentGateway;
use WC_Order;
use WC_Payment_Tokens;
use WP_Error;

/**
 * Api helper class.
 *
 * @package PagBank_WooCommerce\Presentation
 */
class ApiHelpers {

	/**
	 * Get order tax id.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private static function get_order_tax_id_api_data( WC_Order $order ) {
		$billing_cpf  = $order->get_meta( '_billing_cpf' );
		$billing_cnpj = $order->get_meta( '_billing_cnpj' );
		$tax_id       = preg_replace( '/[^0-9]/', '', $billing_cpf ? $billing_cpf : $billing_cnpj );

		return $tax_id;
	}

	/**
	 * Get order items.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array
	 */
	private static function get_order_items_api_data( WC_Order $order ) {
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
	 *
	 * @return array
	 */
	private static function get_order_customer_api_data( WC_Order $order ) {
		return array(
			'name'   => $order->get_formatted_billing_full_name(),
			'email'  => $order->get_billing_email(),
			'tax_id' => self::get_order_tax_id_api_data( $order ),
		);
	}

	/**
	 * Get the value that is not empty.
	 *
	 * @param string $value1 Value 1.
	 * @param string $value2 Value 2.
	 *
	 * @return string
	 */
	private static function get_not_empty( string $value1, string $value2 ): string {
		return ! empty( $value1 ) ? $value1 : $value2;
	}

	/**
	 * Get order shipping address.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $address Address.
	 *
	 * @return array
	 */
	private static function get_order_shipping_address_api_data( WC_Order $order, array $address = array() ) {
		$defaults = array(
			'street'      => substr( self::get_not_empty( $order->get_shipping_address_1(), $order->get_billing_address_1() ), 0, 160 ),
			'number'      => substr( self::get_not_empty( $order->get_meta( '_shipping_number' ), $order->get_meta( '_billing_number' ) ), 0, 20 ),
			'locality'    => substr( self::get_not_empty( $order->get_meta( '_shipping_neighborhood' ), $order->get_meta( '_billing_neighborhood' ) ), 0, 60 ),
			'city'        => substr( self::get_not_empty( $order->get_shipping_city(), $order->get_billing_city() ), 0, 90 ),
			'region_code' => substr( self::get_not_empty( $order->get_shipping_state(), $order->get_billing_state() ), 0, 2 ),
			'country'     => 'BRA',
			'postal_code' => preg_replace( '/[^0-9]/', '', self::get_not_empty( $order->get_shipping_postcode(), $order->get_billing_postcode() ) ),
		);

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
	 *
	 * @return array
	 */
	private static function get_order_billing_address_api_data( WC_Order $order, array $address = array() ) {
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
	 *
	 * @return array
	 */
	private static function get_order_metadata_api_data( WC_Order $order, array $metadata = array() ) {
		$defaults = array(
			'order_id'  => $order->get_id(),
			'signature' => self::get_order_id_signed( $order->get_id() ),
			'password'  => wp_generate_password( 30 ),
		);

		return wp_parse_args( $metadata, $defaults );
	}

	/**
	 * Get pix payment api data.
	 *
	 * @param PixPaymentGateway $gateway Gateway.
	 * @param WC_Order          $order Order.
	 * @param int               $expiration_in_minutes Expiration in minutes.
	 *
	 * @return array
	 */
	public static function get_pix_payment_api_data( PixPaymentGateway $gateway, WC_Order $order, int $expiration_in_minutes ) {
		$password = wp_generate_password( 30 );

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
	 * Get order amount.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array
	 */
	private static function get_order_amount_api_data( WC_Order $order ) {
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
	 *
	 * @return array
	 */
	public static function get_boleto_payment_api_data( BoletoPaymentGateway $gateway, WC_Order $order, int $expiration_in_days ) {
		$password = wp_generate_password( 30 );

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
	 * Reference ID data.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $password Password.
	 *
	 * @return string
	 */
	private static function get_order_reference_id_data( WC_Order $order, string $password ) {
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
	 * @param string                   $payment_token Payment token.
	 * @param string                   $encrypted_card Encrypted card.
	 * @param string                   $card_holder Card holder.
	 * @param bool                     $save_card Save card.
	 * @param string                   $cvv CVV.
	 * @param bool                     $is_subscription Is subscription.
	 * @param int                      $installments Installments.
	 * @param array                    $transfer_of_interest_fee Transfer of interest fee.
	 *
	 * @return array
	 * @throws Exception Throws exception when card is not valid.
	 */
	public static function get_credit_card_payment_data( CreditCardPaymentGateway $gateway, WC_Order $order, string $payment_token = null, string $encrypted_card = null, string $card_holder = null, bool $save_card = false, string $cvv = null, bool $is_subscription = false, int $installments = 1, array $transfer_of_interest_fee = null ) {
		$password = wp_generate_password( 30 );

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
						'type'         => 'CREDIT_CARD',
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

		$is_new_credit_card = null === $payment_token || 'new' === $payment_token;

		if ( $is_new_credit_card ) {
			$is_missing_new_credit_card = null === $encrypted_card || empty( $encrypted_card );

			if ( $is_missing_new_credit_card ) {
				throw new Exception( esc_html__( 'O cartão de crédito criptografado é inválido. Por favor, contate o suporte.', 'pagbank-for-woocommerce' ) );
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

		return apply_filters( 'pagbank_credit_card_payment_data', $data, $order, $gateway );
	}

	/**
	 * Get Credit Card payment data when order has a empty value subscription.
	 *
	 * @param CreditCardPaymentGateway $gateway Gateway.
	 * @param WC_Order                 $order Order.
	 * @param string                   $payment_token Payment token.
	 * @param string                   $encrypted_card Encrypted card.
	 * @param string                   $card_holder Card holder.
	 * @param bool                     $save_card Save card.
	 * @param string                   $cvv CVV.
	 * @param bool                     $is_subscription Is subscription.
	 * @param int                      $installments Installments.
	 * @param array                    $transfer_of_interest_fee Transfer of interest fee.
	 *
	 * @return array
	 * @throws Exception Throws exception when card is not valid.
	 */
	public static function get_credit_card_payment_data_for_empty_value_subscription( CreditCardPaymentGateway $gateway, WC_Order $order, string $payment_token = null, string $encrypted_card = null, string $card_holder = null, bool $save_card = false, string $cvv = null, bool $is_subscription = false, int $installments = 1, array $transfer_of_interest_fee = null ) {
		$data = self::get_credit_card_payment_data( $gateway, $order, $payment_token, $encrypted_card, $card_holder, $save_card, $cvv, $is_subscription, $installments, $transfer_of_interest_fee );

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
	 * @param WC_Order     $renewal_order Renewal order.
	 * @param PaymentToken $payment_token Payment token.
	 * @param float        $amount Amount.
	 *
	 * @return array
	 */
	public static function get_credit_card_renewal_payment_data( WC_Order $renewal_order, PaymentToken $payment_token, float $amount ) {
		$password = wp_generate_password( 30 );

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
						'type'         => 'CREDIT_CARD',
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

		return apply_filters( 'pagbank_credit_card_payment_data', $data, $renewal_order );
	}

	/**
	 * Get installments plan without interest.
	 *
	 * @param int $value Value in cents.
	 * @param int $installments Installments.
	 * @param int $minimum_installment_value Minimum installment value in cents.
	 */
	public static function get_installments_plan_no_interest( int $value, int $installments = 1, int $minimum_installment_value = 500 ) {
		$installments_plan = array();
		$installment_value = $value / $installments;

		if ( $installment_value < $minimum_installment_value ) {
			$installments = floor( $value / $minimum_installment_value );
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
	private static function get_signature_pair() {
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
	private static function get_order_id_signed( string $order_id ) {
		$signature_pair = self::get_signature_pair();
		$signature      = sodium_crypto_sign_detached( $order_id, $signature_pair['sign_secret'] );

		return Helpers::encode_text( $signature );
	}

	/**
	 * Process order refund.
	 *
	 * @param Api      $api PagBank API.
	 * @param WC_Order $order Order.
	 * @param float    $amount Amount to refund.
	 *
	 * @return bool|WP_Error
	 */
	public static function process_order_refund( Api $api, WC_Order $order, $amount = null ) {
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
