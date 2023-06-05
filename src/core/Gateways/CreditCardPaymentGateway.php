<?php
/**
 * Credit card payment gateway.
 *
 * @package PagBank_WooCommerce\Gateways
 */

namespace PagBank_WooCommerce\Gateways;

use Exception;
use PagBank_WooCommerce\Presentation\Api;
use PagBank_WooCommerce\Presentation\Connect;
use WC_Order;
use WC_Payment_Gateway_CC;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use WP_Error;

use function PagBank_WooCommerce\Presentation\format_money_cents;
use function PagBank_WooCommerce\Presentation\get_credit_card_payment_data;

/**
 * Class CreditCardPaymentGateway.
 */
class CreditCardPaymentGateway extends WC_Payment_Gateway_CC {

	/**
	 * Api instance.
	 *
	 * @var Api
	 */
	private $api;

	/**
	 * Api instance.
	 *
	 * @var Connect
	 */
	private $connect;

	/**
	 * Environment.
	 *
	 * @var string
	 */
	private $environment;

	/**
	 * Logs enabled.
	 *
	 * @var string yes|no.
	 */
	private $logs_enabled;

	/**
	 * CreditCardPaymentGateway constructor.
	 */
	public function __construct() {
		$this->id                 = 'pagbank_credit_card';
		$this->method_title       = __( 'PagBank Credit Card', 'pagbank-woocommerce' );
		$this->method_description = __( 'Take credit card payments through PagBank using transparent checkout with one-click buy option.', 'pagbank-woocommerce' );
		$this->has_fields         = true;
		$this->supports           = array(
			'products',
			'tokenization',
			'refunds',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->title        = $this->get_option( 'title' );
		$this->environment  = $this->get_option( 'environment' );
		$this->logs_enabled = $this->get_option( 'logs_enabled' );
		$this->connect      = new Connect( $this->environment );
		$this->api          = new Api( $this->environment, $this->logs_enabled === 'yes' ? $this->id : null );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_credit_card_form_fields', array( $this, 'credit_card_form_fields' ), 10, 2 );
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                   => array(
				'title'   => __( 'Enable/Disable', 'pagbank-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable credit card', 'pagbank-woocommerce' ),
				'default' => 'no',
			),
			'environment'               => array(
				'title'       => __( 'Environment', 'pagbank-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'This will set the environment.', 'pagbank-woocommerce' ),
				'default'     => 'sandbox',
				'options'     => array(
					'sandbox'    => __( 'Sandbox', 'pagbank-woocommerce' ),
					'production' => __( 'Production', 'pagbank-woocommerce' ),
				),
				'desc_tip'    => true,
			),
			'pagbank_connect'           => array(
				'title'       => __( 'Connnect', 'pagbank-woocommerce' ),
				'type'        => 'pagbank_connect',
				'description' => __( 'Connect to your PagBank account to enable the payment method.', 'pagbank-woocommerce' ),
			),
			'title'                     => array(
				'title'       => __( 'Title', 'pagbank-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'pagbank-woocommerce' ),
				'default'     => __( 'Credit card', 'pagbank-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'               => array(
				'title'       => __( 'Description', 'pagbank-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'pagbank-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'one_click_buy_enabled'     => array(
				'title'       => __( 'One-click buy', 'pagbank-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable one-click buy', 'pagbank-woocommerce' ),
				'description' => __( 'This allows users to save their credit cards.', 'pagbank-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'installments_enabled'      => array(
				'title'             => __( 'Installments', 'pagbank-woocommerce' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable installments', 'pagbank-woocommerce' ),
				'description'       => __( 'This will enable installments during the purchase.', 'pagbank-woocommerce' ),
				'default'           => 'yes',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-toggle' => implode(
						',',
						array(
							'#' . $this->get_field_key( 'maximum_installments' ),
							'#' . $this->get_field_key( 'minimum_installment_value' ),
						)
					),
				),
			),
			'maximum_installments'      => array(
				'title'       => __( 'Maximum installments', 'pagbank-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'This will set the maximum number of installments.', 'pagbank-woocommerce' ),
				'default'     => '12',
				'options'     => array(
					''   => __( 'Select an option', 'pagbank-woocommerce' ),
					'1'  => '1x',
					'2'  => '2x',
					'3'  => '3x',
					'4'  => '4x',
					'5'  => '5x',
					'6'  => '6x',
					'7'  => '7x',
					'8'  => '8x',
					'9'  => '9x',
					'10' => '10x',
					'11' => '11x',
					'12' => '12x',
				),
				'desc_tip'    => true,
			),
			'minimum_installment_value' => array(
				'title'       => __( 'Minimum installment value', 'pagbank-woocommerce' ),
				'type'        => 'currency',
				'description' => __( 'This will set the minimum installment value.', 'pagbank-woocommerce' ),
				'default'     => '5',
				'desc_tip'    => true,
			),
			'logs_enabled'              => array(
				'title'       => __( 'Debug logs', 'pagbank-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable debug logs', 'pagbank-woocommerce' ),
				'description' => __( 'This will enable logs to help debug the plugin in case of support.', 'pagbank-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Enqueue scripts in tokenization form.
	 *
	 * @return void
	 */
	public function tokenization_script() {
		wp_enqueue_script( 'pagbank-sdk', 'https://assets.pagseguro.com.br/checkout-sdk-js/rc/dist/browser/pagseguro.min.js', array(), '1.0.0', true );

		wp_enqueue_script(
			'pagbank-checkout-credit-card',
			plugins_url( 'dist/public/checkout-credit-card.js', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array(),
			PAGBANK_WOOCOMMERCE_VERSION,
			true
		);

		wp_localize_script(
			'pagbank-checkout-credit-card',
			'PagBankCheckoutCreditCardVariables',
			array(
				'publicKey' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAr+ZqgD892U9/HXsa7XqBZUayPquAfh9xx4iwUbTSUAvTlmiXFQNTp0Bvt/5vK2FhMj39qSv1zi2OuBjvW38q1E374nzx6NNBL5JosV0+SDINTlCG0cmigHuBOyWzYmjgca+mtQu4WczCaApNaSuVqgb8u7Bd9GCOL4YJotvV5+81frlSwQXralhwRzGhj/A57CGPgGKiuPT+AOGmykIGEZsSD9RKkyoKIoc0OS8CPIzdBOtTQCIwrLn2FxI83Clcg55W8gkFSOS6rWNbG5qFZWMll6yl02HtunalHmUlRUL66YeGXdMDC2PuRcmZbGO5a/2tbVppW6mfSWG3NPRpgwIDAQAB',
				'messages'  => array(
					'inputs_not_found'         => __( 'Inputs not found.', 'pagbank-woocommerce' ),
					'invalid_public_key'       => __( 'Invalid public key.', 'pagbank-woocommerce' ),
					'invalid_holder_name'      => __( 'Invalid holder name.', 'pagbank-woocommerce' ),
					'invalid_card_number'      => __( 'Invalid card number.', 'pagbank-woocommerce' ),
					'invalid_card_expiry_date' => __( 'Invalid card expiry date.', 'pagbank-woocommerce' ),
					'invalid_security_code'    => __( 'Invalid card cvc.', 'pagbank-woocommerce' ),
					'invalid_encrypted_card'   => __( 'Encrypted card input not found', 'pagbank-woocommerce' ),
				),
			)
		);

		parent::tokenization_script();
	}

	/**
	 * Handle add payment method form.
	 *
	 * TODO: implement method.
	 *
	 * @return array Result.
	 */
	public function add_payment_method() {
		return array(
			'result'   => 'failure',
			'redirect' => wc_get_endpoint_url( 'payment-methods' ),
		);
	}

	/**
	 * Add holder and encrypted card field to the credit card form.
	 *
	 * TODO: add a CPF field to include in charge.payment_method.card.holder.tax_id
	 *
	 * @param array  $default_fields Default fields.
	 * @param string $gateway_id     Gateway ID.
	 *
	 * @return array Fields filtered.
	 */
	public function credit_card_form_fields( $default_fields, $gateway_id ) {
		if ( $this->id === $gateway_id ) {
			$new_fields = array(
				'card-holder-field'    => '<p class="form-row form-row-wide">
					<label for="' . esc_attr( $this->id ) . '-card-holder">' . esc_html__( 'Card holder', 'pagbank-woocommerce' ) . '&nbsp;<span class="required">*</span></label>
					<input id="' . esc_attr( $this->id ) . '-card-holder" class="input-text wc-credit-card-form-card-holder" autocomplete="cc-name" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" ' . $this->field_name( 'card-holder' ) . ' />
				</p>',
				'encrypted-card-field' => '<input id="' . esc_attr( $this->id ) . '-encrypted-card" type="hidden" name="' . esc_attr( $this->id ) . '-encrypted-card" />',
			);

			return array_merge( $new_fields, $default_fields );
		}

		return $default_fields;
	}

	/**
	 * Validate fields.
	 *
	 * TODO: validate fields.
	 */
	public function validate_fields() {
		wc_add_notice( __( 'The card was not encrypted. Please contact support.', 'pagbank-woocommerce' ), 'error' );

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $_POST[ $this->id . '-encrypted-card' ] ) || empty( $_POST[ $this->id . '-encrypted-card' ] ) ) {
			wc_add_notice( __( 'The card was not encrypted. Please contact support.', 'pagbank-woocommerce' ), 'error' );
		}
	}

	/**
	 * Process order payment.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array
	 *
	 * @throws Exception When an error occurs.
	 */
	public function process_payment( $order_id ) {
		try {
			$order = wc_get_order( $order_id );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$payment_token = isset( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) ? wc_clean( wp_unslash( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) ) : null;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$encrypted_card = isset( $_POST['pagbank_credit_card-encrypted-card'] ) ? wc_clean( wp_unslash( $_POST['pagbank_credit_card-encrypted-card'] ) ) : null;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$save_card = isset( $_POST[ 'wc-' . $this->id . '-new-payment-method' ] ) && $_POST[ 'wc-' . $this->id . '-new-payment-method' ] === 'true';

			$data = get_credit_card_payment_data(
				$order,
				$payment_token,
				$encrypted_card,
				$save_card
			);

			$response = $this->api->create_charge( $data );

			if ( is_wp_error( $response ) ) {
				wc_add_notice( __( 'Houve um erro no pagamento.', 'pagbank-woocommerce' ), 'error' );
				return;
			}

			if ( $response['status'] === 'IN_ANALYSIS' ) {
				$order->update_status( 'on-hold', __( 'O PagBank está analisando o risco da transação.', 'pagbank-woocommerce' ) );
			} elseif ( $response['status'] === 'DECLINED' ) {
				wc_add_notice( __( 'O pagamento foi recusado pelo PagBank', 'pagbank-woocommerce' ), 'error' );
				return;
			} elseif ( $response['status'] !== 'PAID' ) {
				throw new Exception( __( 'Invalid order status from PagBank API.', 'pagbank-woocommerce' ) );
			}

			// Add order details.
			$this->save_order_meta_data( $order, $response );

			$order->payment_complete();

			// Save credit card.
			if ( isset( $response['payment_method']['card']['id'] ) ) {
				$token = new WC_Payment_Token_CC();

				$token->set_token( $response['payment_method']['card']['id'] );
				$token->set_card_type( $response['payment_method']['card']['brand'] );
				$token->set_last4( $response['payment_method']['card']['last_digits'] );
				$token->set_expiry_month( $response['payment_method']['card']['exp_month'] );
				$token->set_expiry_year( $response['payment_method']['card']['exp_year'] );
				$token->set_gateway_id( $this->id );
				$token->set_user_id( $order->get_user_id() );

				$token->save();
			}

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
		}
	}

	/**
	 * Save order meta data.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $response Response data.
	 *
	 * @return void
	 */
	private function save_order_meta_data( WC_Order $order, array $response ) {
		$order->update_meta_data( '_pagbank_order_id', $response['id'] );
		$order->update_meta_data( '_pagbank_credit_card_brand', $response['payment_method']['card']['brand'] );
		$order->update_meta_data( '_pagbank_credit_card_installments', $response['payment_method']['installments'] );
		$order->save_meta_data();
	}

	/**
	 * Process a refund.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $amount   Refund amount.
	 * @param string $reason   Refund reason.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$amount = floatval( $amount );

		if ( $amount <= 0 ) {
			return new WP_Error( 'error', __( 'O valor do reembolso não pode ser zero.', 'pagbank-woocommerce' ) );
		}

		$pagbank_order_id = get_post_meta( $order_id, '_pagbank_order_id', true );

		try {
			$refund = $this->api->refund( $pagbank_order_id, $amount );

			if ( is_wp_error( $refund ) ) {
				return $refund;
			}

			if ( $refund['status'] === 'CANCELED' ) {
				return true;
			}

			return new WP_Error( 'error', __( 'Houve um erro ao tentar realizar o reembolso.', 'pagbank-woocommerce' ) );
		} catch ( Exception $ex ) {
			return new WP_Error( 'error', __( 'Houve um erro ao tentar realizar o reembolso.', 'pagbank-woocommerce' ) );
		}
	}

	/**
	 * Check if gateway needs setup.
	 *
	 * TODO: implement method.
	 *
	 * @return bool
	 */
	public function needs_setup() {
		$is_connected = ! ! $this->connect->get_data();

		return ! $is_connected;
	}

	/**
	 * Check if gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$is_available = ( 'yes' === $this->enabled );

		if ( ! $is_available ) {
			return false;
		}

		if ( WC()->cart && 0 < $this->get_order_total() && 0 < $this->max_amount && $this->max_amount < $this->get_order_total() ) {
			return false;
		}

		$is_connected          = ! ! $this->connect->get_data();
		$is_brazilian_currency = get_woocommerce_currency() === 'BRL';

		if ( ! $is_connected || ! $is_brazilian_currency ) {
			return false;
		}

		return true;
	}

}
