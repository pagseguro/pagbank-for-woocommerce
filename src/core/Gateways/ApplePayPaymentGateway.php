<?php
/**
 * Apple Pay payment gateway.
 *
 * @package PagBank_WooCommerce\Gateways
 */

namespace PagBank_WooCommerce\Gateways;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use PagBank_WooCommerce\Gateways\Traits\ReactSettingsTrait;
use PagBank_WooCommerce\Presentation\Api;
use PagBank_WooCommerce\Presentation\ApiHelpers;
use PagBank_WooCommerce\Presentation\Connect;
use PagBank_WooCommerce\Presentation\Helpers;
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;

/**
 * Class ApplePayPaymentGateway.
 */
class ApplePayPaymentGateway extends WC_Payment_Gateway {

	use ReactSettingsTrait;

	/**
	 * Api instance.
	 */
	private Api $api;

	/**
	 * Connect instance.
	 */
	public Connect $connect;

	/**
	 * Environment.
	 */
	public string $environment;

	/**
	 * Logs enabled.
	 */
	private bool $logs_enabled;

	/**
	 * ApplePayPaymentGateway constructor.
	 */
	public function __construct() {
		$this->id                 = 'pagbank_apple_pay';
		$this->icon               = plugins_url( 'dist/images/icons/apple-pay.png', PAGBANK_WOOCOMMERCE_FILE_PATH );
		$this->method_title       = __( 'PagBank Apple Pay', 'pagbank-for-woocommerce' );
		$this->method_description = __( 'Aceite pagamentos via Apple Pay através do PagBank.', 'pagbank-for-woocommerce' );
		$this->description        = $this->get_option( 'description' );
		$this->has_fields         = true;
		$this->supports           = array(
			'products',
			'refunds',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->title        = $this->get_option( 'title' );
		$this->environment  = $this->get_option( 'environment' );
		$this->logs_enabled = 'yes' === $this->get_option( 'logs_enabled' );
		$this->connect      = new Connect( $this->environment );
		$this->api          = new Api( $this->environment, $this->logs_enabled ? $this->id : null );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		$this->is_available_validation();
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Habilitar/Desabilitar', 'pagbank-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar Apple Pay', 'pagbank-for-woocommerce' ),
				'default' => 'no',
			),
			'environment'     => array(
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
			'pagbank_connect' => array(
				'title'       => __( 'Conta PagBank', 'pagbank-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Conecte a sua conta PagBank para aceitar pagamentos.', 'pagbank-for-woocommerce' ),
			),
			'title'           => array(
				'title'       => __( 'Título', 'pagbank-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Isso irá controlar o título que o cliente verá durante o checkout.', 'pagbank-for-woocommerce' ),
				'default'     => __( 'Apple Pay', 'pagbank-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => __( 'Descrição', 'pagbank-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Isso irá controlar a descrição que o cliente verá durante o checkout.', 'pagbank-for-woocommerce' ),
				'default'     => __( 'Pague de forma rápida e segura com o Apple Pay.', 'pagbank-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'logs_enabled'    => array(
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
	 * Get payment method title.
	 *
	 * @return string The title.
	 */
	public function get_title(): string {
		if ( is_admin() ) {
			$screen = get_current_screen();

			if ( $screen && $screen->id === 'woocommerce_page_wc-orders' ) {
				return $this->method_title;
			}
		}

		return apply_filters( 'woocommerce_gateway_title', $this->title, $this->id );
	}

	/**
	 * Process order payment.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @throws Exception When an error occurs.
	 */
	public function process_payment( $order_id ): array {
		try {
			$order = wc_get_order( $order_id );

			// Get Apple Pay token from POST data.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$apple_pay_token = isset( $_POST['pagbank_apple_pay-token'] ) ? sanitize_text_field( wp_unslash( $_POST['pagbank_apple_pay-token'] ) ) : null;

			if ( empty( $apple_pay_token ) ) {
				throw new Exception( __( 'O token do Apple Pay não foi encontrado. Por favor, tente novamente.', 'pagbank-for-woocommerce' ) );
			}

			$data     = ApiHelpers::get_apple_pay_payment_api_data( $this, $order, $apple_pay_token );
			$response = $this->api->create_order( $data );

			if ( is_wp_error( $response ) ) {
				wc_add_notice( __( 'Houve um erro ao processar o pagamento. Tente novamente.', 'pagbank-for-woocommerce' ), 'error' );

				return array(
					'result'  => 'failure',
					'message' => __( 'Houve um erro ao processar o pagamento. Tente novamente.', 'pagbank-for-woocommerce' ),
				);
			}

			// Add order details.
			$this->save_order_meta_data( $order, $response, $data );

			$charge = $response['charges'][0];

			// Handle payment status.
			switch ( $charge['status'] ) {
				case 'PAID':
					$order->payment_complete( $charge['id'] );
					break;
				case 'IN_ANALYSIS':
					$order->update_status( 'on-hold', __( 'Pagamento em análise pelo PagBank.', 'pagbank-for-woocommerce' ) );
					break;
				case 'DECLINED':
					$decline_message = $charge['payment_response']['message'] ?? __( 'Pagamento recusado.', 'pagbank-for-woocommerce' );
					throw new Exception( $decline_message );
			}

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );

			return array(
				'result'  => 'failure',
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Process a refund.
	 *
	 * @param int         $order_id Order ID.
	 * @param string|null $amount   Refund amount.
	 * @param string      $reason   Refund reason.
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order                       = wc_get_order( $order_id );
		$should_process_order_refund = apply_filters( 'pagbank_should_process_order_refund', true, $order );

		if ( is_wp_error( $should_process_order_refund ) ) {
			return $should_process_order_refund;
		}

		if ( $should_process_order_refund === true ) {
			return ApiHelpers::process_order_refund( $this->api, $order, $amount, $reason );
		}

		return new WP_Error( 'error', __( 'Houve um erro desconhecido ao tentar realizar o reembolso.', 'pagbank-for-woocommerce' ) );
	}

	/**
	 * Save order meta data.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $response Response data.
	 * @param array    $request Request data.
	 */
	private function save_order_meta_data( WC_Order $order, array $response, array $request ): void {
		$charge = $response['charges'][0];

		$order->update_meta_data( '_pagbank_order_id', $response['id'] );
		$order->update_meta_data( '_pagbank_charge_id', $charge['id'] );
		$order->update_meta_data( '_pagbank_password', $request['metadata']['password'] );
		$order->update_meta_data( '_pagbank_environment', $this->environment );
		$order->update_meta_data( '_pagbank_payment_method', 'apple_pay' );

		$order->save_meta_data();
	}

	/**
	 * Check if gateway needs setup.
	 */
	public function needs_setup(): bool {
		$is_connected = (bool) $this->connect->get_data();

		return ! $is_connected;
	}

	/**
	 * Check if gateway is available for use.
	 */
	public function is_available(): bool {
		$is_available = ( 'yes' === $this->enabled );

		if ( ! $is_available ) {
			return false;
		}

		// Apple Pay is only available on Blocks checkout.
		if ( ! Helpers::is_checkout_block() ) {
			return false;
		}

		if ( WC()->cart && 0 < $this->get_order_total() && 0 < $this->max_amount && $this->max_amount < $this->get_order_total() ) {
			return false;
		}

		$is_connected          = (bool) $this->connect->get_data();
		$is_brazilian_currency = get_woocommerce_currency() === 'BRL';

		if ( ! $is_connected || ! $is_brazilian_currency ) {
			return false;
		}

		if ( ! is_ssl() ) {
			return false;
		}

		return true;
	}

	/**
	 * Add errors in case of some validation error that will appear during the checkout.
	 */
	public function is_available_validation(): void {
		$is_enabled            = ( 'yes' === $this->enabled );
		$is_connected          = (bool) $this->connect->get_data();
		$is_brazilian_currency = get_woocommerce_currency() === 'BRL';

		$errors = array();

		if ( ! $is_enabled ) {
			$errors[] = __( '- O método de pagamento está desabilitado.', 'pagbank-for-woocommerce' );
		}

		if ( ! $is_connected ) {
			$errors[] = __( '- A sua conta PagBank não está conectada.', 'pagbank-for-woocommerce' );
		}

		if ( ! $is_brazilian_currency ) {
			$errors[] = __( '- A moeda da loja não é BRL.', 'pagbank-for-woocommerce' );
		}

		if ( ! is_ssl() ) {
			$errors[] = __( '- A loja precisa usar HTTPS (SSL) para aceitar pagamentos via Apple Pay.', 'pagbank-for-woocommerce' );
		}

		if ( $errors ) {
			array_unshift( $errors, __( 'Alguns errors podem estar impedindo o método de pagamento de ser exibido durante o checkout:', 'pagbank-for-woocommerce' ) );

			$this->add_error( implode( '<br />', $errors ) );
		}
	}

	/**
	 * Generate HTML settings HTML with errors.
	 *
	 * @param array $form_fields The form fields to display.
	 * @param bool  $echo_output Should echo or return.
	 *
	 * @return string If $echo = false, return the HTML content.
	 */
	public function generate_settings_html( $form_fields = array(), $echo_output = true ): string {
		ob_start();
		$this->display_errors();
		$html = ob_get_clean();

		if ( $echo_output ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XSS ok.
			echo $html . parent::generate_settings_html( $form_fields, $echo_output );

			return '';
		} else {
			return $html . parent::generate_settings_html( $form_fields, $echo_output );
		}
	}

	/**
	 * Get the gateway merchant ID for Apple Pay.
	 */
	public function get_gateway_merchant_id(): ?string {
		$connect_data = $this->connect->get_data();

		return $connect_data['account_id'] ?? null;
	}
}
