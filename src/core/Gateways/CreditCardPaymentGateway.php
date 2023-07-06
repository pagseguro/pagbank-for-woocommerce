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
use function PagBank_WooCommerce\Presentation\process_order_refund;

/**
 * Class CreditCardPaymentGateway.
 */
class CreditCardPaymentGateway extends WC_Payment_Gateway_CC {

	/**
	 * Api instance.
	 *
	 * @var Api
	 */
	public $api;

	/**
	 * Api instance.
	 *
	 * @var Connect
	 */
	public $connect;

	/**
	 * Environment.
	 *
	 * @var string
	 */
	public $environment;

	/**
	 * Logs enabled.
	 *
	 * @var bool.
	 */
	public $logs_enabled;

	/**
	 * Installments enabled.
	 *
	 * @var bool
	 */
	public $installments_enabled;

	/**
	 * Maximum installments.
	 *
	 * @var int
	 */
	public $maximum_installments;

	/**
	 * Transfer of interest enabled.
	 *
	 * @var bool
	 */
	public $transfer_of_interest_enabled;

	/**
	 * Maximum installments interest free.
	 *
	 * @var int
	 */
	private $maximum_installments_interest_free;

	/**
	 * CreditCardPaymentGateway constructor.
	 */
	public function __construct() {
		$this->id                 = 'pagbank_credit_card';
		$this->method_title       = __( 'PagBank Cartão de Crédito', 'pagbank-for-woocommerce' );
		$this->method_description = __( 'Aceite pagamentos via cartão de crédito através do PagBank.', 'pagbank-for-woocommerce' );
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
		$this->logs_enabled = 'yes' === $this->get_option( 'logs_enabled' );
		$this->connect      = new Connect( $this->environment );
		$this->api          = new Api( $this->environment, $this->logs_enabled ? $this->id : null );

		$this->installments_enabled               = 'yes' === $this->get_option( 'installments_enabled' );
		$this->maximum_installments               = (int) $this->get_option( 'maximum_installments' );
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
				'title'   => __( 'Habilitar/Desabilitar', 'pagbank-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar cartão de crédito', 'pagbank-for-woocommerce' ),
				'default' => 'no',
			),
			'environment'                        => array(
				'title'       => __( 'Ambiente', 'pagbank-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Isso irá definir o ambiente de testes ou produção.', 'pagbank-for-woocommerce' ),
				'default'     => 'sandbox',
				'options'     => array(
					'sandbox'    => __( 'Ambiente de testes', 'pagbank-for-woocommerce' ),
					'production' => __( 'Produção', 'pagbank-for-woocommerce' ),
				),
				'desc_tip'    => true,
			),
			'pagbank_connect'                    => array(
				'title'       => __( 'Conta PagBank', 'pagbank-for-woocommerce' ),
				'type'        => 'pagbank_connect',
				'description' => __( 'Conecte a sua conta PagBank para aceitar pagamentos.', 'pagbank-for-woocommerce' ),
			),
			'title'                              => array(
				'title'       => __( 'Título', 'pagbank-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Isso irá controlar o título que o cliente verá durante o checkout.', 'pagbank-for-woocommerce' ),
				'default'     => __( 'Cartão de crédito', 'pagbank-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'                        => array(
				'title'       => __( 'Descrição', 'pagbank-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Isso irá controlar a descrição que o cliente verá durante o checkout.', 'pagbank-for-woocommerce' ),
				'default'     => __( 'Escolha um cartão de crédito salvo ou preencha os dados do seu cartão de crédito no formulário abaixo:', 'pagbank-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'installments_enabled'               => array(
				'title'             => __( 'Parcelamento', 'pagbank-for-woocommerce' ),
				'type'              => 'checkbox',
				'label'             => __( 'Ativar parcelamento', 'pagbank-for-woocommerce' ),
				'description'       => __( 'Isso irá habilitar o parcelamento durante o checkout.', 'pagbank-for-woocommerce' ),
				'default'           => 'no',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-toggle' => implode(
						',',
						array(
							'#' . $this->get_field_key( 'maximum_installments' ),
							'#' . $this->get_field_key( 'transfer_of_interest_enabled' ),
						)
					),
				),
			),
			'maximum_installments'               => array(
				'title'       => __( 'Máximo de parcelas', 'pagbank-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Isso irá definir o número máximo de parcelas durante o checkout.', 'pagbank-for-woocommerce' ),
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
			'transfer_of_interest_enabled'       => array(
				'title'             => __( 'Repasse de juros', 'pagbank-for-woocommerce' ),
				'type'              => 'checkbox',
				'label'             => __( 'Ativar repasse de juros', 'pagbank-for-woocommerce' ),
				'description'       => __( 'Isso irá ativar o repasse de juros durante o checkout. Por padrão as parcelas serão sem juros. Isso irá liberar a opção de definir a quantidade de parcelas sem juros.', 'pagbank-for-woocommerce' ),
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
				'title'       => __( 'Máximo de parcelas sem juros', 'pagbank-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Isso irá definir a quantidade de parcelas que serão sem juros.', 'pagbank-for-woocommerce' ),
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
				'title'       => __( 'Logs para depuração', 'pagbank-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Ativar logs', 'pagbank-for-woocommerce' ),
				'description' => __( 'Isso irá ativar os logs para depuração para auxiliar em caso de suporte.', 'pagbank-for-woocommerce' ),
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
		wp_enqueue_script( 'pagbank-sdk', 'https://assets.pagseguro.com.br/checkout-sdk-js/rc/dist/browser/pagseguro.min.js', array(), PAGBANK_WOOCOMMERCE_VERSION, true );

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

		$connect_data = $this->connect->get_data();

		wp_localize_script(
			'pagbank-checkout-credit-card',
			'PagBankCheckoutCreditCardVariables',
			array(
				'messages' => array(
					'inputs_not_found'         => __( 'Campos não encontrado.', 'pagbank-for-woocommerce' ),
					'invalid_public_key'       => __( 'Chave pública inválida.', 'pagbank-for-woocommerce' ),
					'invalid_holder_name'      => __( 'Nome do titular do cartão inválido.', 'pagbank-for-woocommerce' ),
					'invalid_card_number'      => __( 'Número do cartão inválido.', 'pagbank-for-woocommerce' ),
					'invalid_card_expiry_date' => __( 'Data de expiração do cartão inválida.', 'pagbank-for-woocommerce' ),
					'invalid_security_code'    => __( 'Código de segurança do cartão inválido.', 'pagbank-for-woocommerce' ),
					'invalid_encrypted_card'   => __( 'O cartão de crédito criptografado não foi encontrado.', 'pagbank-for-woocommerce' ),
					'invalid_card_bin'         => __( 'O bin do cartão de crédito não foi encontrado.', 'pagbank-for-woocommerce' ),
				),
				'settings' => array(
					'installments_enabled'               => $this->installments_enabled,
					'maximum_installments'               => $this->maximum_installments,
					'transfer_of_interest_enabled'       => $this->transfer_of_interest_enabled,
					'maximum_installments_interest_free' => $this->maximum_installments_interest_free,
					'card_public_key'                    => isset( $connect_data['public_key'] ) ? $connect_data['public_key'] : null,
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
					'message' => __( 'Nonce inválido.', 'pagbank-for-woocommerce' ),
				),
				400
			);
			return;
		}

		if ( false === $this->installments_enabled ) {
			wp_send_json_error(
				array(
					'message' => __( 'O parcelamento não está ativado.', 'pagbank-for-woocommerce' ),
				),
				400
			);
			return;
		}

		if ( false === $this->transfer_of_interest_enabled ) {
			wp_send_json_error(
				array(
					'message' => __( 'O repasse de juros não está ativado.', 'pagbank-for-woocommerce' ),
				),
				400
			);
			return;
		}

		if ( empty( $card_bin ) && empty( $payment_token ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'O "card_bin" ou "payment_token" é obrigatório.', 'pagbank-for-woocommerce' ),
				),
				400
			);
			return;
		} elseif ( $card_bin && $payment_token ) {
			wp_send_json_error(
				array(
					'message' => __( 'Você não pode enviar o "card_bin" e o "payment_token" ao mesmo tempo.', 'pagbank-for-woocommerce' ),
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
						'message' => __( 'Token de pagamento inválido.', 'pagbank-for-woocommerce' ),
					),
					400
				);

				return;
			}

			if ( $token->get_type() !== 'PagBank_CC' ) {
				wp_send_json_error(
					array(
						'message' => __( 'Ops, houve um erro com o token de pagamento.', 'pagbank-for-woocommerce' ),
					),
					400
				);

				return;
			}

			$card_bin = $token->get_bin();
		}

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
												? sprintf( __( '%1$dx de %2$s sem juros', 'pagbank-for-woocommerce' ), $plan['installments'], format_money( $plan['installment_value'] / 100 ) )
												// translators: 1: installments, 2: installment value, 3: installment total.
												: sprintf( __( '%1$dx de %2$s com juros (%3$s)', 'pagbank-for-woocommerce' ), $plan['installments'], format_money( $plan['installment_value'] / 100 ), format_money( $plan['amount']['value'] / 100 ) ),
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
					<label for="' . esc_attr( $this->id ) . '-card-holder">' . esc_html__( 'Titular do cartão', 'pagbank-for-woocommerce' ) . '&nbsp;<span class="required">*</span></label>
					<input id="' . esc_attr( $this->id ) . '-card-holder" name="' . esc_attr( $this->id ) . '-card-holder" class="input-text wc-credit-card-form-card-holder" autocomplete="cc-name" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" ' . $this->field_name( 'card-holder' ) . ' />
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
			echo '<p>' . esc_html( __( 'Você só pode adicionar um cartão de crédito durante o checkout.', 'pagbank-for-woocommerce' ) ) . '</p>';
			return;
		}

		$this->tokenization_script();
		$this->saved_payment_methods();
		$this->form();
		$this->save_payment_method_checkbox();
		if ( $this->installments_enabled ) {
			$this->installments_fields();
		}
	}

	/**
	 * Installments fields on checkout.
	 */
	public function installments_fields() {
		if ( $this->transfer_of_interest_enabled ) {
			wc_get_template(
				'checkout-installments-fields-transfer-of-interest.php',
				array(
					'gateway' => $this,
				),
				'woocommerce/pagbank/',
				PAGBANK_WOOCOMMERCE_TEMPLATES_PATH
			);
		} else {
			wc_get_template(
				'checkout-installments-fields-no-interest.php',
				array(
					'gateway' => $this,
				),
				'woocommerce/pagbank/',
				PAGBANK_WOOCOMMERCE_TEMPLATES_PATH
			);
		}
	}

	/**
	 * Validate fields.
	 */
	public function validate_fields() {
		// Disable outside checkout.
		if ( ! is_checkout() ) {
			wc_add_notice( __( 'Você só pode adicionar um cartão de crédito durante o checkout.', 'pagbank-for-woocommerce' ), 'error' );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payment_token = isset( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) ? wc_clean( wp_unslash( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) ) : null;

		$is_new_credit_card = null === $payment_token || 'new' === $payment_token;

		// Validation for new credit cards.
		if ( $is_new_credit_card ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$has_holder = isset( $_POST['pagbank_credit_card-card-holder'] ) && ! empty( $_POST['pagbank_credit_card-card-holder'] );
			if ( ! $has_holder ) {
				wc_add_notice( __( 'O titular do cartão de crédito é obrigatório.', 'pagbank-for-woocommerce' ), 'error' );
				return false;
			}

			// phpcs:ignore WordPress.Security.NonceVerification
			$has_encrypted_card = isset( $_POST['pagbank_credit_card-encrypted-card'] ) && ! empty( $_POST['pagbank_credit_card-encrypted-card'] );
			if ( ! $has_encrypted_card ) {
				wc_add_notice( __( 'O cartão de crédito criptografado não foi identificado. Por favor, contate o suporte.', 'pagbank-for-woocommerce' ), 'error' );
				return false;
			}

			// phpcs:ignore WordPress.Security.NonceVerification
			$has_card_bin = isset( $_POST['pagbank_credit_card-card-bin'] ) && ! empty( $_POST['pagbank_credit_card-card-bin'] );
			if ( ! $has_card_bin ) {
				wc_add_notice( __( 'O bin do cartão de crédito não foi identificado. Por favor, contate o suporte.', 'pagbank-for-woocommerce' ), 'error' );
				return false;
			}

			// phpcs:ignore WordPress.Security.NonceVerification
			$card_bin          = wc_clean( wp_unslash( $_POST['pagbank_credit_card-card-bin'] ) );
			$is_valid_card_bin = strlen( $card_bin ) === 6;
			if ( ! $is_valid_card_bin ) {
				wc_add_notice( __( 'Bin do cartão de crédito inválido. Por favor, contate o suporte.', 'pagbank-for-woocommerce' ), 'error' );
				return false;
			}
		}

		// Validation for saved credit cards.
		if ( ! $is_new_credit_card ) {
			$token = WC_Payment_Tokens::get( $payment_token );

			$is_token_from_same_user = $token->get_user_id() === get_current_user_id();
			if ( ! $is_token_from_same_user ) {
				wc_add_notice( __( 'O token de pagamento é inválido.', 'pagbank-for-woocommerce' ), 'error' );
				return false;
			}
		}

		// Validation for installments.
		if ( $this->installments_enabled ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$has_installments = isset( $_POST['pagbank_credit_card-installments'] ) && ! empty( $_POST['pagbank_credit_card-installments'] );
			if ( ! $has_installments ) {
				wc_add_notice( __( 'É necessário selecionar a quantidade de parcelas.', 'pagbank-for-woocommerce' ), 'error' );
				return false;
			}

			// phpcs:ignore WordPress.Security.NonceVerification
			$installments = (int) wc_clean( wp_unslash( $_POST['pagbank_credit_card-installments'] ) );
			if ( $installments > $this->maximum_installments || $installments < 1 ) {
				wc_add_notice( __( 'A quantidade de parcelas é inválida.', 'pagbank-for-woocommerce' ), 'error' );
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
			$card_holder = isset( $_POST['pagbank_credit_card-card-holder'] ) ? wc_clean( wp_unslash( $_POST['pagbank_credit_card-card-holder'] ) ) : null;
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
						wc_add_notice( __( 'O token de pagamento é inválido.', 'pagbank-for-woocommerce' ), 'error' );
						return;
					}

					$card_bin    = $token->get_bin();
					$card_holder = $token->get_holder();
				}

				$charge_fees = $this->api->charge_fees( $amount_in_cents, $this->maximum_installments, $this->maximum_installments_interest_free, $card_bin );

				if ( is_wp_error( $charge_fees ) ) {
					wc_add_notice( __( 'Erro ao obter o plano de parcelamento.', 'pagbank-for-woocommerce' ), 'error' );
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
					wc_add_notice( __( 'O plano de parcelamento não foi encontrado.', 'pagbank-for-woocommerce' ) );
					return;
				}

				$transfer_of_interest_fee = $matched_plan['amount'];
			}

			$data = get_credit_card_payment_data(
				$order,
				$payment_token,
				$encrypted_card,
				$card_holder,
				$save_card,
				$installments,
				$transfer_of_interest_fee
			);

			$response = $this->api->create_order( $data );

			if ( is_wp_error( $response ) ) {
				wc_add_notice( __( 'Houve um erro durante o pagamento. Tente novamente.', 'pagbank-for-woocommerce' ), 'error' );
				return;
			}

			$charge = $response['charges'][0];

			if ( $charge['status'] === 'IN_ANALYSIS' ) {
				$order->update_status( 'on-hold', __( 'O PagBank está analisando a transação.', 'pagbank-for-woocommerce' ) );
			} elseif ( $charge['status'] === 'DECLINED' ) {
				wc_add_notice( __( 'O pagamento foi recusado.', 'pagbank-for-woocommerce' ), 'error' );
				return;
			} elseif ( $charge['status'] !== 'PAID' ) {
				wc_add_notice( __( 'Houve um erro no pagamento. Por favor, entre em contato com o suporte.', 'pagbank-for-woocommerce' ), 'error' );
				return;
			}

			$this->save_order_meta_data( $order, $response, $data );

			$order->payment_complete();

			if ( $save_card && isset( $charge['payment_method']['card']['id'] ) ) {
				$this->save_credit_card( $order, $charge['payment_method']['card'] );
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
	 * @param array    $request Request data.
	 *
	 * @return void
	 */
	private function save_order_meta_data( WC_Order $order, array $response, array $request ) {
		$charge = $response['charges'][0];

		$order->update_meta_data( '_pagbank_order_id', $response['id'] );
		$order->update_meta_data( '_pagbank_charge_id', $charge['id'] );
		$order->update_meta_data( '_pagbank_password', $request['metadata']['password'] );

		$order->update_meta_data( '_pagbank_credit_card_brand', $charge['payment_method']['card']['brand'] );
		$order->update_meta_data( '_pagbank_credit_card_installments', $charge['payment_method']['installments'] );
		$order->update_meta_data( '_pagbank_environment', $this->environment );
		$order->save_meta_data();

		if ( isset( $charge['amount']['fees'] ) ) {
			$interest_fee = new WC_Order_Item_Fee();
			$amount       = $charge['amount']['fees']['buyer']['interest']['total'] / 100;

			$interest_fee->set_name( __( 'Parcelamento', 'pagbank-for-woocommerce' ) );
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
		return process_order_refund( $this->api, $order_id, $amount, $reason );
	}

	/**
	 * Check if gateway needs setup.
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
