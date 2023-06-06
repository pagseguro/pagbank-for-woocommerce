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
use PagBank_WooCommerce\Presentation\PaymentToken;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Payment_Gateway_CC;
use WC_Payment_Tokens;
use WP_Error;

use function PagBank_WooCommerce\Presentation\format_money;
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
	 * Installments enabled.
	 *
	 * @var string yes|no.
	 */
	private $installments_enabled;

	/**
	 * Maximum installments.
	 *
	 * @var string yes|no.
	 */
	private $maximum_installments;

	/**
	 * Minimum installment value.
	 *
	 * @var string Minimum value.
	 */
	private $minimum_installment_value;

	/**
	 * Transfer of interest enabled.
	 *
	 * @var string yes|no.
	 */
	private $transfer_of_interest_enabled;

	/**
	 * Maximum installments interest free.
	 *
	 * @var string yes|no.
	 */
	private $maximum_installments_interest_free;

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

		$this->installments_enabled               = 'yes' === $this->get_option( 'installments_enabled' );
		$this->maximum_installments               = (int) $this->get_option( 'maximum_installments' );
		$this->minimum_installment_value          = $this->get_option( 'minimum_installment_value' );
		$this->transfer_of_interest_enabled       = 'yes' === $this->get_option( 'transfer_of_interest_enabled' );
		$this->maximum_installments_interest_free = (int) $this->get_option( 'maximum_installments_interest_free' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_credit_card_form_fields', array( $this, 'credit_card_form_fields' ), 10, 2 );
		add_action( 'woocommerce_api_' . $this->id . '_installments', array( $this, 'get_installments' ) );
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                            => array(
				'title'   => __( 'Enable/Disable', 'pagbank-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable credit card', 'pagbank-woocommerce' ),
				'default' => 'no',
			),
			'environment'                        => array(
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
			'pagbank_connect'                    => array(
				'title'       => __( 'Connnect', 'pagbank-woocommerce' ),
				'type'        => 'pagbank_connect',
				'description' => __( 'Connect to your PagBank account to enable the payment method.', 'pagbank-woocommerce' ),
			),
			'title'                              => array(
				'title'       => __( 'Title', 'pagbank-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'pagbank-woocommerce' ),
				'default'     => __( 'Credit card', 'pagbank-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'                        => array(
				'title'       => __( 'Description', 'pagbank-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'pagbank-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'one_click_buy_enabled'              => array(
				'title'       => __( 'One-click buy', 'pagbank-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable one-click buy', 'pagbank-woocommerce' ),
				'description' => __( 'This allows users to save their credit cards.', 'pagbank-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'installments_enabled'               => array(
				'title'             => __( 'Installments', 'pagbank-woocommerce' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable installments', 'pagbank-woocommerce' ),
				'description'       => __( 'This will enable installments during the purchase.', 'pagbank-woocommerce' ),
				'default'           => 'no',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-toggle' => implode(
						',',
						array(
							'#' . $this->get_field_key( 'maximum_installments' ),
							'#' . $this->get_field_key( 'minimum_installment_value' ),
							'#' . $this->get_field_key( 'transfer_of_interest_enabled' ),
						)
					),
				),
			),
			'maximum_installments'               => array(
				'title'       => __( 'Maximum installments', 'pagbank-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'This will set the maximum number of installments.', 'pagbank-woocommerce' ),
				'default'     => '12',
				'options'     => array(
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
			'minimum_installment_value'          => array(
				'title'       => __( 'Minimum installment value', 'pagbank-woocommerce' ),
				'type'        => 'currency',
				'description' => __( 'This will set the minimum installment value.', 'pagbank-woocommerce' ),
				'default'     => '5',
				'desc_tip'    => true,
			),
			'transfer_of_interest_enabled'       => array(
				'title'             => __( 'Transfer of interest', 'pagbank-woocommerce' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable transfer of interest', 'pagbank-woocommerce' ),
				'description'       => __( 'This will enable transfer of interest during the purchase.', 'pagbank-woocommerce' ),
				'default'           => 'no',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-toggle' => implode(
						',',
						array(
							'#' . $this->get_field_key( 'maximum_installments_interest_free' ),
						)
					),
				),
			),
			'maximum_installments_interest_free' => array(
				'title'       => __( 'Maximum installments interest free', 'pagbank-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'This will set the maximum number of installments interest free.', 'pagbank-woocommerce' ),
				'default'     => '12',
				'options'     => array(
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
			'logs_enabled'                       => array(
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

		wp_enqueue_style(
			'pagbank-checkout-credit-card',
			plugins_url( 'styles/checkout-credit-card.css', PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array(),
			PAGBANK_WOOCOMMERCE_VERSION,
			'all'
		);

		wp_scripts()->add_data( 'pagbank-checkout-credit-card', 'type', 'module' );

		wp_localize_script(
			'pagbank-checkout-credit-card',
			'PagBankCheckoutCreditCardVariables',
			array(
				// TODO: use correct public key.
				'publicKey' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAr+ZqgD892U9/HXsa7XqBZUayPquAfh9xx4iwUbTSUAvTlmiXFQNTp0Bvt/5vK2FhMj39qSv1zi2OuBjvW38q1E374nzx6NNBL5JosV0+SDINTlCG0cmigHuBOyWzYmjgca+mtQu4WczCaApNaSuVqgb8u7Bd9GCOL4YJotvV5+81frlSwQXralhwRzGhj/A57CGPgGKiuPT+AOGmykIGEZsSD9RKkyoKIoc0OS8CPIzdBOtTQCIwrLn2FxI83Clcg55W8gkFSOS6rWNbG5qFZWMll6yl02HtunalHmUlRUL66YeGXdMDC2PuRcmZbGO5a/2tbVppW6mfSWG3NPRpgwIDAQAB',
				'messages'  => array(
					'inputs_not_found'         => __( 'Inputs not found.', 'pagbank-woocommerce' ),
					'invalid_public_key'       => __( 'Invalid public key.', 'pagbank-woocommerce' ),
					'invalid_holder_name'      => __( 'Invalid holder name.', 'pagbank-woocommerce' ),
					'invalid_card_number'      => __( 'Invalid card number.', 'pagbank-woocommerce' ),
					'invalid_card_expiry_date' => __( 'Invalid card expiry date.', 'pagbank-woocommerce' ),
					'invalid_security_code'    => __( 'Invalid card cvc.', 'pagbank-woocommerce' ),
					'invalid_encrypted_card'   => __( 'Encrypted card input not found', 'pagbank-woocommerce' ),
					'invalid_card_bin'         => __( 'Invalid card bin.', 'pagbank-woocommerce' ),
				),
				'settings'  => array(
					'installments_enabled'               => $this->installments_enabled,
					'maximum_installments'               => $this->maximum_installments,
					'transfer_of_interest_enabled'       => $this->transfer_of_interest_enabled,
					'maximum_installments_interest_free' => $this->maximum_installments_interest_free,
				),
			)
		);

		parent::tokenization_script();
	}

	/**
	 * Get installments.
	 */
	public function get_installments(): void {
		$nonce                              = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : null;
		$maximum_installments               = (int) $this->maximum_installments;
		$maximum_installments_interest_free = min( (int) $this->maximum_installments_interest_free, $maximum_installments );
		$card_bin                           = isset( $_GET['card_bin'] ) ? sanitize_text_field( wp_unslash( $_GET['card_bin'] ) ) : null;
		$payment_token                      = isset( $_GET['payment_token'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_token'] ) ) : null;
		$amount                             = isset( $_GET['amount'] ) ? (float) sanitize_text_field( wp_unslash( $_GET['amount'] ) ) : null;
		$amount_in_cents                    = format_money_cents( $amount );

		if ( ! wp_verify_nonce( $nonce, 'pagbank_get_installments' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid nonce', 'pagbank-woocommerce' ),
				),
				400
			);
			return;
		}

		if ( false === $this->installments_enabled ) {
			wp_send_json_error(
				array(
					'message' => __( 'Installments is not enabled.', 'pagbank-woocommerce' ),
				),
				400
			);
			return;
		}

		if ( false === $this->transfer_of_interest_enabled ) {
			wp_send_json_error(
				array(
					'message' => __( 'Transfer of interest is not enabled.', 'pagbank-woocommerce' ),
				),
				400
			);
			return;
		}

		if ( empty( $card_bin ) && empty( $payment_token ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'card_bin or payment_token is required.', 'pagbank-woocommerce' ),
				),
				400
			);
			return;
		} elseif ( $card_bin && $payment_token ) {
			wp_send_json_error(
				array(
					'message' => __( 'You cannot send card_bin or payment_token at the same time.', 'pagbank-woocommerce' ),
				),
				400
			);
			return;
		}

		if ( $payment_token && ! $card_bin ) {

			/**
			 * Convert to PaymentToken.
			 *
			 * @var PaymentToken
			 */
			$token = WC_Payment_Tokens::get( $payment_token );

			if ( null === $token || get_current_user_id() !== $token->get_user_id() ) {
				wp_send_json_error(
					array(
						'message' => __( 'Invalid payment token.', 'pagbank-woocommerce' ),
					),
					400
				);

				return;
			}

			if ( $token->get_type() !== 'PagBank_CC' ) {
				wp_send_json_error(
					array(
						'message' => __( 'Ops, something wrong in the token.', 'pagbank-woocommerce' ),
					),
					400
				);

				return;
			}

			$card_bin = $token->get_bin();
		}

		// TODO: cache this API request.
		$charge_fees = $this->api->charge_fees( $amount_in_cents, $maximum_installments, $maximum_installments_interest_free, $card_bin );

		if ( is_wp_error( $charge_fees ) ) {
			wp_send_json_error(
				array(
					'message' => $charge_fees->get_error_message(),
				),
				500
			);
			return;
		}

		$key                = array_key_first( $charge_fees['payment_methods']['credit_card'] );
		$installments_plans = $charge_fees['payment_methods']['credit_card'][ $key ]['installment_plans'];

		$mapped_installments = array_map(
			function( $plan ) {
				return array(
					'installments'      => $plan['installments'],
					'installment_value' => $plan['installment_value'],
					'interest_free'     => $plan['interest_free'],
					'title'             => $plan['interest_free']
												// translators: 1: installments, 2: installment value.
												? sprintf( __( '%1$dx de %2$s sem juros', 'pagbank-woocommerce' ), $plan['installments'], format_money( $plan['installment_value'] / 100 ) )
												// translators: 1: installments, 2: installment value, 3: installment total.
												: sprintf( __( '%1$dx de %2$s com juros (%3$s)', 'pagbank-woocommerce' ), $plan['installments'], format_money( $plan['installment_value'] / 100 ), format_money( $plan['amount']['value'] / 100 ) ),
					'amount'            => $plan['amount']['value'],
				);
			},
			$installments_plans
		);

		wp_send_json_success(
			$mapped_installments
		);
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
				'encrypted-card-field' => '<input id="' . esc_attr( $this->id ) . '-encrypted-card" type="hidden" name="' . esc_attr( $this->id ) . '-encrypted-card" />',
				'card-bin-field'       => '<input id="' . esc_attr( $this->id ) . '-card-bin" type="hidden" name="' . esc_attr( $this->id ) . '-card-bin" />',
				'card-holder-field'    => '<p class="form-row form-row-wide">
					<label for="' . esc_attr( $this->id ) . '-card-holder">' . esc_html__( 'Card holder', 'pagbank-woocommerce' ) . '&nbsp;<span class="required">*</span></label>
					<input id="' . esc_attr( $this->id ) . '-card-holder" class="input-text wc-credit-card-form-card-holder" autocomplete="cc-name" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" ' . $this->field_name( 'card-holder' ) . ' />
				</p>',
			);

			return array_merge( $new_fields, $default_fields );
		}

		return $default_fields;
	}

	/**
	 * Checkout payment fields.
	 */
	public function payment_fields() {
		if ( ! is_checkout() ) {
			echo '<p>' . esc_html( __( 'Só é possível adicionar um cartão de crédito através do checkout.', 'pagbank-woocommerce' ) ) . '</p>';
			return;
		}

		$this->tokenization_script();
		$this->saved_payment_methods();
		$this->form();
		$this->save_payment_method_checkbox();
		$this->installments_fields();
	}

	/**
	 * Installments fields on checkout.
	 */
	public function installments_fields() {
		wc_get_template(
			'checkout-installments-fields.php',
			array(
				'gateway' => $this,
			),
			'woocommerce/pagbank/',
			PAGBANK_WOOCOMMERCE_TEMPLATES_PATH
		);
	}

	/**
	 * Validate fields.
	 *
	 * TODO: validate fields.
	 */
	public function validate_fields() {
		// Disable outside checkout.
		if ( ! is_checkout() ) {
			wc_add_notice( __( 'Só é possível adicionar um cartão de crédito através do checkout.', 'pagbank-woocommerce' ), 'error' );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payment_token = isset( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) ? wc_clean( wp_unslash( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) ) : null;

		$is_new_credit_card = null === $payment_token || 'new' === $payment_token;

		// Validation for new credit cards.
		if ( $is_new_credit_card ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$has_encrypted_card = isset( $_POST['pagbank_credit_card-encrypted-card'] ) && ! empty( $_POST['pagbank_credit_card-encrypted-card'] );
			if ( ! $has_encrypted_card ) {
				wc_add_notice( __( 'The card was not encrypted. Please contact support.', 'pagbank-woocommerce' ), 'error' );
				return false;
			}

			// phpcs:ignore WordPress.Security.NonceVerification
			$has_card_bin = isset( $_POST['pagbank_credit_card-card-bin'] ) && ! empty( $_POST['pagbank_credit_card-card-bin'] );
			if ( ! $has_card_bin ) {
				wc_add_notice( __( 'Missing card bin. Please contact support.', 'pagbank-woocommerce' ), 'error' );
				return false;
			}

			// phpcs:ignore WordPress.Security.NonceVerification
			$card_bin          = wc_clean( wp_unslash( $_POST['pagbank_credit_card-card-bin'] ) );
			$is_valid_card_bin = strlen( $card_bin ) === 6;
			if ( ! $is_valid_card_bin ) {
				wc_add_notice( __( 'Invalid card bin.', 'pagbank-woocommerce' ), 'error' );
				return false;
			}
		}

		// Validation for saved credit cards.
		if ( ! $is_new_credit_card ) {
			$token = WC_Payment_Tokens::get( $payment_token );

			$is_token_from_same_user = $token->get_user_id() === get_current_user_id();
			if ( ! $is_token_from_same_user ) {
				wc_add_notice( __( 'Payment token not found.', 'pagbank-woocommerce' ), 'error' );
				return false;
			}
		}

		// Validation for installments.
		if ( $this->installments_enabled ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$has_installments = isset( $_POST['pagbank_credit_card-installments'] ) && ! empty( $_POST['pagbank_credit_card-installments'] );
			if ( ! $has_installments ) {
				wc_add_notice( __( 'Missing installments.', 'pagbank-woocommerce' ), 'error' );
				return false;
			}

			// phpcs:ignore WordPress.Security.NonceVerification
			$installments = (int) wc_clean( wp_unslash( $_POST['pagbank_credit_card-installments'] ) );
			if ( $installments > $this->maximum_installments ) {
				wc_add_notice( __( 'Invalid installments.', 'pagbank-woocommerce' ), 'error' );
				return false;
			}
		}

		return true;
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
			$card_bin = isset( $_POST['pagbank_credit_card-card-bin'] ) ? wc_clean( wp_unslash( $_POST['pagbank_credit_card-card-bin'] ) ) : null;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$save_card = ( $payment_token === null || $payment_token === 'new' ) && isset( $_POST[ 'wc-' . $this->id . '-new-payment-method' ] ) && $_POST[ 'wc-' . $this->id . '-new-payment-method' ] === 'true';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$installments = isset( $_POST['pagbank_credit_card-installments'] ) ? (int) wc_clean( wp_unslash( $_POST['pagbank_credit_card-installments'] ) ) : 1;

			if ( $this->installments_enabled && $this->transfer_of_interest_enabled ) {
				$amount_in_cents    = format_money_cents( $order->get_total() );
				$is_new_credit_card = null === $payment_token || 'new' === $payment_token;

				if ( ! $is_new_credit_card ) {
					/**
					 * Convert to PaymentToken.
					 *
					 * @var PaymentToken
					 */
					$token = WC_Payment_Tokens::get( $payment_token );

					if ( $token === null ) {
						wc_add_notice( __( 'Invalid payment token.', 'pagbank-woocommerce' ), 'error' );
						return;
					}

					$card_bin = $token->get_bin();
				}

				$charge_fees = $this->api->charge_fees( $amount_in_cents, $this->maximum_installments, $this->maximum_installments_interest_free, $card_bin );

				if ( is_wp_error( $charge_fees ) ) {
					wc_add_notice( __( 'Error getting installments.', 'pagbank-woocommerce' ), 'error' );
					return;
				}

				$key                = array_key_first( $charge_fees['payment_methods']['credit_card'] );
				$installments_plans = $charge_fees['payment_methods']['credit_card'][ $key ]['installment_plans'];
				$matched_plan       = null;

				foreach ( $installments_plans as $installments_plan ) {
					if ( $installments_plan['installments'] === $installments ) {
						$matched_plan = $installments_plan;
						break;
					}
				}

				if ( $matched_plan === null ) {
					wc_add_notice( __( 'Installment plan not found.', 'pagbank-woocommerce' ) );
					return;
				}

				$transfer_of_interest_fee = $matched_plan['amount'];
			}

			$data = get_credit_card_payment_data(
				$order,
				$payment_token,
				$encrypted_card,
				$save_card,
				$installments,
				$transfer_of_interest_fee
			);

			$response = $this->api->create_charge( $data );

			if ( is_wp_error( $response ) ) {
				wc_add_notice( __( 'Houve um erro no pagamento.', 'pagbank-woocommerce' ), 'error' );
				return;
			}

			if ( $response['status'] === 'IN_ANALYSIS' ) {
				$order->update_status( 'on-hold', __( 'O PagBank está analisando o risco da transação.', 'pagbank-woocommerce' ) );
			} elseif ( $response['status'] === 'DECLINED' ) {
				wc_add_notice( __( 'O pagamento foi recusado.', 'pagbank-woocommerce' ), 'error' );
				return;
			} elseif ( $response['status'] !== 'PAID' ) {
				wc_add_notice( __( 'Invalid order status from API.', 'pagbank-woocommerce' ), 'error' );
				return;
			}

			$this->save_order_meta_data( $order, $response );

			$order->payment_complete();

			if ( $save_card && isset( $response['payment_method']['card']['id'] ) ) {
				$this->save_credit_card( $order, $response['payment_method']['card'] );
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
	 * Save credit card token.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Credit card data.
	 */
	private function save_credit_card( WC_Order $order, array $data ): void {
		$token = new PaymentToken();

		$token->set_holder( $data['holder']['name'] );
		$token->set_bin( $data['first_digits'] );
		$token->set_token( $data['id'] );
		$token->set_card_type( $data['brand'] );
		$token->set_last4( $data['last_digits'] );
		$token->set_expiry_month( $data['exp_month'] );
		$token->set_expiry_year( $data['exp_year'] );
		$token->set_gateway_id( $this->id );
		$token->set_user_id( $order->get_user_id() );

		$token->save();
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

		if ( isset( $response['amount']['fees'] ) ) {
			$interest_fee = new WC_Order_Item_Fee();
			$amount       = $response['amount']['fees']['buyer']['interest']['total'] / 100;

			$interest_fee->set_name( __( 'Interest', 'pagbank-woocommerce' ) );
			$interest_fee->set_amount( $amount );
			$interest_fee->set_tax_class( '' );
			$interest_fee->set_tax_status( 'none' );
			$interest_fee->set_total( $amount );

			$order->add_item( $interest_fee );
			$order->calculate_totals();
			$order->save();
		}
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
